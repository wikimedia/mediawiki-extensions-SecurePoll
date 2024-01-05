<?php

namespace MediaWiki\Extension\SecurePoll\Jobs;

use Exception;
use Job;
use JobSpecification;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\ActorMigration;
use MediaWiki\WikiMap\WikiMap;
use MWExceptionHandler;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

/**
 * Job for populating the voter list for an election.
 */
class PopulateVoterListJob extends Job {
	public static function pushJobsForElection( Election $election ) {
		static $props = [
			'need-list',
			'list_populate',
			'list_edits-before',
			'list_edits-before-count',
			'list_edits-before-date',
			'list_edits-between',
			'list_edits-between-count',
			'list_edits-startdate',
			'list_edits-enddate',
			'list_exclude-groups',
			'list_include-groups',
		];
		static $listProps = [
			'list_exclude-groups',
			'list_include-groups',
		];

		$dbw = $election->context->getDB();
		$services = MediaWikiServices::getInstance();
		$lbFactory = $services->getDBLoadBalancerFactory();

		// First, fetch the current config and calculate a hash of it for
		// detecting changes
		$params = [
			'electionWiki' => WikiMap::getCurrentWikiId(),
			'electionId' => $election->getId(),
			'list_populate' => '0',
			'need-list' => '',
			'list_edits-before' => '',
			'list_edits-between' => '',
			'list_exclude-groups' => '',
			'list_include-groups' => '',
		];

		$res = $dbw->select(
			'securepoll_properties',
			[
				'pr_key',
				'pr_value'
			],
			[
				'pr_entity' => $election->getId(),
				'pr_key' => $props,
			],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$params[$row->pr_key] = $row->pr_value;
		}

		if ( !$params['list_populate'] || $params['need-list'] === '' ) {
			// No need for a job, bail out
			return;
		}

		foreach ( $listProps as $prop ) {
			if ( $params[$prop] === '' ) {
				$params[$prop] = [];
			} else {
				$params[$prop] = explode( '|', $params[$prop] );
			}
		}

		ksort( $params );
		$key = sha1( serialize( $params ) );

		// Now fill in the remaining params
		$params += [
			'jobKey' => $key,
			'nextUserId' => 1,
		];

		// Get the list of wikis we need jobs on
		$wikis = $election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( WikiMap::getCurrentWikiId(), $wikis ) ) {
				$wikis[] = WikiMap::getCurrentWikiId();
			}
		} else {
			$wikis = [ WikiMap::getCurrentWikiId() ];
		}

		// Find the max user_id for each wiki, both to know when we're done
		// with that wiki's job and for the special page to calculate progress.
		$maxIds = [];
		$total = 0;
		foreach ( $wikis as $wiki ) {
			$dbr = $lbFactory->getMainLB( $wiki )->getConnection( DB_REPLICA, [], $wiki );
			$max = $dbr->selectField( 'user', 'MAX(user_id)', [], __METHOD__ );
			if ( !$max ) {
				$max = 0;
			}
			$maxIds[$wiki] = $max;
			$total += $max;

			// reuse connection
			unset( $dbr );
		}

		// Start the jobs!
		$title = SpecialPage::getTitleFor( 'SecurePoll' );
		$lockKey = "SecurePoll_PopulateVoterListJob-{$election->getId()}";
		$lockMethod = __METHOD__;

		// Clear any transaction snapshots, acquire a mutex, and start a new transaction
		$lbFactory->commitPrimaryChanges( __METHOD__ );
		$dbw->lock( $lockKey, $lockMethod );
		$dbw->startAtomic( __METHOD__ );
		$dbw->onTransactionResolution(
			static function () use ( $dbw, $lockKey, $lockMethod ) {
				$dbw->unlock( $lockKey, $lockMethod );
			},
			__METHOD__
		);

		// If the same job is (supposed to be) already running, don't restart it
		$jobKey = self::fetchJobKey( $dbw, $election->getId() );
		if ( $params['jobKey'] === $jobKey ) {
			$dbw->endAtomic( __METHOD__ );

			return;
		}

		// Record the new job key (which will cause any outdated jobs to
		// abort) and the progress figures.
		$dbw->replace(
			'securepoll_properties',
			[
				[
					'pr_entity',
					'pr_key'
				]
			],
			[
				[
					'pr_entity' => $election->getId(),
					'pr_key' => 'list_job-key',
					'pr_value' => $params['jobKey'],
				],
				[
					'pr_entity' => $election->getId(),
					'pr_key' => 'list_total-count',
					'pr_value' => $total,
				],
				[
					'pr_entity' => $election->getId(),
					'pr_key' => 'list_complete-count',
					'pr_value' => 0,
				],
			],
			__METHOD__
		);

		$jobQueueGroupFactory = $services->getJobQueueGroupFactory();
		foreach ( $wikis as $wiki ) {
			$params['maxUserId'] = $maxIds[$wiki];
			$params['thisWiki'] = $wiki;

			$jobQueueGroup = $jobQueueGroupFactory->makeJobQueueGroup( $wiki );

			// If possible, delay the job execution in case the user
			// immediately re-edits.
			$jobQueue = $jobQueueGroup->get( 'securePollPopulateVoterList' );
			if ( $jobQueue->delayedJobsEnabled() ) {
				$params['jobReleaseTimestamp'] = time() + 3600;
			} else {
				unset( $params['jobReleaseTimestamp'] );
			}

			$jobQueueGroup->push(
				new JobSpecification(
					'securePollPopulateVoterList', $params, [], $title
				)
			);
		}

		$dbw->endAtomic( __METHOD__ );
		$lbFactory->commitPrimaryChanges( __METHOD__ );
	}

	public function __construct( $title, $params ) {
		parent::__construct( 'securePollPopulateVoterList', $title, $params );
	}

	public function run() {
		$min = (int)$this->params['nextUserId'];
		$max = min( $min + 500, $this->params['maxUserId'] + 1 );
		$next = $min;

		$services = MediaWikiServices::getInstance();
		$lbFactory = $services->getDBLoadBalancerFactory();
		try {
			// Check if the job key changed, and abort if so.
			$dbwElection = $lbFactory->getPrimaryDatabase( $this->params['electionWiki'] );
			$dbwLocal = $lbFactory->getPrimaryDatabase();
			$jobKey = self::fetchJobKey( $dbwElection, $this->params['electionId'] );
			if ( $jobKey !== $this->params['jobKey'] ) {
				return true;
			}

			$dbr = $lbFactory->getReplicaDatabase();

			$actorQuery = ActorMigration::newMigration()->getJoin( 'rev_user' );
			$field = $actorQuery['fields']['rev_user'];

			// Construct the list of user_ids in our range that pass the criteria
			$users = null;

			// Criterion 1: $NUM edits before $DATE
			if ( $this->params['list_edits-before'] ) {
				$timestamp = $dbr->addQuotes(
					$dbr->timestamp( $this->params['list_edits-before-date'] )
				);

				$res = $dbr->select(
					[ 'revision' ] + $actorQuery['tables'],
					[ 'rev_user' => $field ],
					[
						"$field >= $min",
						"$field < $max",
						"rev_timestamp < $timestamp",
					],
					__METHOD__,
					[
						'GROUP BY' => $field,
						'HAVING' => [
							'COUNT(*) >= ' . $dbr->addQuotes(
								$this->params['list_edits-before-count']
							),
						]
					]
				);

				$list = [];
				foreach ( $res as $row ) {
					$list[] = $row->rev_user;
				}

				// @phan-suppress-next-line PhanSuspiciousValueComparison Same as in next if
				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_intersect( $users, $list );
				}
			}

			// Criterion 2: $NUM edits bewteen $DATE1 and $DATE2
			if ( $this->params['list_edits-between'] ) {
				$timestamp1 = $dbr->addQuotes(
					$dbr->timestamp( $this->params['list_edits-startdate'] )
				);
				$timestamp2 = $dbr->addQuotes(
					$dbr->timestamp( $this->params['list_edits-enddate'] )
				);

				$res = $dbr->select(
					[ 'revision' ] + $actorQuery['tables'],
					[ 'rev_user' => $field ],
					[
						"$field >= $min",
						"$field < $max",
						"rev_timestamp >= $timestamp1",
						"rev_timestamp < $timestamp2",
					],
					__METHOD__,
					[
						'GROUP BY' => $field,
						'HAVING' => [
							'COUNT(*) >= ' . $dbr->addQuotes(
								$this->params['list_edits-between-count']
							),
						]
					]
				);
				$list = [];
				foreach ( $res as $row ) {
					$list[] = $row->rev_user;
				}

				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_intersect( $users, $list );
				}
			}

			// Criterion 3: Not in a listed group
			global $wgDisableUserGroupExpiry;
			if ( $this->params['list_exclude-groups'] ) {
				$res = $dbr->select(
					[
						'user',
						'user_groups'
					],
					'user_id',
					[
						"user_id >= $min",
						"user_id < $max",
						'ug_user IS NULL',
					],
					__METHOD__,
					[],
					[
						'user_groups' => [
							'LEFT OUTER JOIN',
							[
								'ug_user = user_id',
								'ug_group' => $this->params['list_exclude-groups'],
								( !isset( $wgDisableUserGroupExpiry ) || $wgDisableUserGroupExpiry )
									? '1'
									: 'ug_expiry IS NULL OR ug_expiry >= ' . $dbr->addQuotes(
										$dbr->timestamp()
									),
							]
						],
					]
				);
				$list = [];
				foreach ( $res as $row ) {
					$list[] = $row->user_id;
				}

				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_intersect( $users, $list );
				}
			}

			// Criterion 4: In a listed group (overrides 1-3)
			if ( $this->params['list_include-groups'] ) {
				$res = $dbr->select(
					'user_groups',
					'ug_user',
					[
						"ug_user >= $min",
						"ug_user < $max",
						'ug_group' => $this->params['list_include-groups'],
						( !isset( $wgDisableUserGroupExpiry ) || $wgDisableUserGroupExpiry )
							? '1'
							: 'ug_expiry IS NULL OR ug_expiry >= ' . $dbr->addQuotes(
								$dbr->timestamp()
							),
					],
					__METHOD__
				);
				$list = [];
				foreach ( $res as $row ) {
					$list[] = $row->ug_user;
				}

				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_values( array_unique( array_merge( $users, $list ) ) );
				}
			}

			$ins = [];
			foreach ( $users as $user_id ) {
				$ins[] = [
					'li_name' => $this->params['need-list'],
					'li_member' => $user_id,
				];
			}

			// Flush any prior REPEATABLE-READ snapshots so the locking below works
			$lbFactory->commitPrimaryChanges( __METHOD__ );

			// Check again that the jobKey didn't change, holding a lock this time...
			$lockKey = "SecurePoll_PopulateVoterListJob-{$this->params['electionId']}";
			$lockMethod = __METHOD__;
			if ( !$dbwElection->lock( $lockKey, $lockMethod, 30 ) ) {
				throw new RuntimeException( "Could not acquire '$lockKey'." );
			}
			$dbwElection->startAtomic( __METHOD__ );
			$dbwElection->onTransactionResolution(
				static function () use ( $dbwElection, $lockKey, $lockMethod ) {
					$dbwElection->unlock( $lockKey, $lockMethod );
				},
				__METHOD__
			);
			$dbwLocal->startAtomic( __METHOD__ );

			$jobKey = self::fetchJobKey( $dbwElection, $this->params['electionId'] );
			if ( $jobKey === $this->params['jobKey'] ) {
				$dbwLocal->delete(
					'securepoll_lists',
					[
						'li_name' => $this->params['need-list'],
						"li_member >= $min",
						"li_member < $max",
					],
					__METHOD__
				);
				$dbwLocal->insert( 'securepoll_lists', $ins, __METHOD__ );

				$count = $dbwElection->selectField(
					'securepoll_properties',
					'pr_value',
					[
						'pr_entity' => $this->params['electionId'],
						'pr_key' => 'list_complete-count',
					],
					__METHOD__
				);
				$dbwElection->update(
					'securepoll_properties',
					[
						'pr_value' => $count + $max - $min,
					],
					[
						'pr_entity' => $this->params['electionId'],
						'pr_key' => 'list_complete-count',
					],
					__METHOD__
				);
			}

			$dbwLocal->endAtomic( __METHOD__ );
			$dbwElection->endAtomic( __METHOD__ );
			// Commit now so the jobs pushed below see any changes from above
			$lbFactory->commitPrimaryChanges( __METHOD__ );

			$next = $max;
		} catch ( Exception $exception ) {
			MWExceptionHandler::rollbackPrimaryChangesAndLog( $exception );
		}

		// Schedule the next run of this job, if necessary
		if ( $next <= $this->params['maxUserId'] ) {
			$params = $this->params;
			$params['nextUserId'] = $next;
			unset( $params['jobReleaseTimestamp'] );

			$services->getJobQueueGroup()->push(
				new JobSpecification(
					'securePollPopulateVoterList', $params, [], $this->title
				)
			);
		}

		return true;
	}

	private static function fetchJobKey( IDatabase $db, $electionId ) {
		return $db->selectField(
			'securepoll_properties',
			'pr_value',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'list_job-key',
			],
			__METHOD__
		);
	}
}
