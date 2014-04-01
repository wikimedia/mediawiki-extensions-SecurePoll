<?php

/**
 * Job for populating the voter list for an election.
 */
class SecurePoll_PopulateVoterListJob extends Job {
	public static function pushJobsForElection( SecurePoll_Election $election ) {
		static $props = array(
			'need-list', 'list_populate',
			'list_edits-before', 'list_edits-before-count', 'list_edits-before-date',
			'list_edits-between', 'list_edits-between-count', 'list_edits-between-dates',
			'list_exclude-groups',
			'list_include-groups',
		);
		static $listProps = array(
			'list_edits-between-dates', 'list_exclude-groups', 'list_include-groups',
		);

		$dbw = $election->context->getDB();

		// First, fetch the current config and calculate a hash of it for
		// detecting changes
		$params = array(
			'electionWiki' => wfWikiID(),
			'electionId' => $election->getID(),
			'list_populate' => '0',
			'need-list' => '',
			'list_edits-before' => '',
			'list_edits-between' => '',
			'list_exclude-groups' => '',
			'list_include-groups' => '',
		);

		$res = $dbw->select(
			'securepoll_properties',
			array( 'pr_key', 'pr_value' ),
			array(
				'pr_entity' => $election->getID(),
				'pr_key' => $props,
			)
		);
		foreach ( $res as $row ) {
			$params[$row->pr_key] = $row->pr_value;
		}

		if ( !$params['list_populate'] ||
			$params['need-list'] === ''
		) {
			// No need for a job, bail out
			return;
		}

		foreach ( $listProps as $prop ) {
			if ( $params[$prop] === '' ) {
				$params[$prop] = array();
			} else {
				$params[$prop] = explode( '|', $params[$prop] );
			}
		}

		ksort( $params );
		$key = sha1( serialize( $params ) );

		// Now fill in the remaining params
		$params += array(
			'jobKey' => $key,
			'nextUserId' => 1,
		);

		// Get the list of wikis we need jobs on
		$wikis = $election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = array( wfWikiID() );
		}

		// Find the max user_id for each wiki, both to know when we're done
		// with that wiki's job and for the special page to calculate progress.
		$maxIds = array();
		$total = 0;
		foreach ( $wikis as $wiki ) {
			$dbr = wfGetDB( DB_SLAVE, array(), $wiki );
			$max = $dbr->selectField( 'user', 'MAX(user_id)' );
			if ( !$max ) {
				$max = 0;
			}
			$maxIds[$wiki] = $max;
			$total += $max;
		}

		// Start the jobs!
		$title = SpecialPage::getTitleFor( 'SecurePoll' );
		try {
			$dbw->begin();
			$dbw->lock( "SecurePoll_PopulateVoterListJob-{$election->getID()}", __METHOD__ );

			// If the same job is (supposed to be) already running, don't restart it
			$jobKey = self::fetchJobKey( $dbw, $election->getID() );
			if ( $params['jobKey'] === $jobKey ) {
				$dbw->rollback();
				return;
			}

			// Record the new job key (which will cause any outdated jobs to
			// abort) and the progress figures.
			$dbw->replace(
				'securepoll_properties',
				array( 'pr_entity', 'pr_key' ),
				array(
					array(
						'pr_entity' => $election->getID(),
						'pr_key' => 'list_job-key',
						'pr_value' => $params['jobKey'],
					),
					array(
						'pr_entity' => $election->getID(),
						'pr_key' => 'list_total-count',
						'pr_value' => $total,
					),
					array(
						'pr_entity' => $election->getID(),
						'pr_key' => 'list_complete-count',
						'pr_value' => 0,
					),
				)
			);

			foreach ( $wikis as $wiki ) {
				$params['maxUserId'] = $maxIds[$wiki];
				$params['thisWiki'] = $wiki;

				$jobQueueGroup = JobQueueGroup::singleton( $wiki );

				// If possible, delay the job execution in case the user
				// immediately re-edits.
				$jobQueue = $jobQueueGroup->get( 'securePollPopulateVoterList' );
				if ( $jobQueue->delayedJobsEnabled() ) {
					$params['jobReleaseTimestamp'] = time() + 3600;
				} else {
					unset( $params['jobReleaseTimestamp'] );
				}

				$jobQueueGroup->push( new JobSpecification(
					'securePollPopulateVoterList',
					$params,
					array(),
					$title
				) );
			}

			$dbw->unlock( "SecurePoll_PopulateVoterListJob-{$election->getID()}", __METHOD__ );
			$dbw->commit();
		} catch ( Exception $ex ) {
			$dbw->unlock( "SecurePoll_PopulateVoterListJob-{$election->getID()}", __METHOD__ );
			$dbw->rollback();
			throw $ex;
		}
	}

	public function __construct( $title, $params ) {
		parent::__construct( 'securePollPopulateVoterList', $title, $params );
	}

	public function run() {
		$min = (int)$this->params['nextUserId'];
		$max = min( $min + 500, $this->params['maxUserId'] + 1 );
		$next = $min;

		$dbw = null;
		$dbwMaster = null;
		try {
			// Check if the job key changed, and abort if so.
			$dbwMaster = wfGetDB( DB_MASTER, array(), $this->params['electionWiki'] );
			$dbw = wfGetDB( DB_MASTER );
			$jobKey = self::fetchJobKey( $dbwMaster, $this->params['electionId'] );
			if ( $jobKey !== $this->params['jobKey'] ) {
				return true;
			}

			$dbr = wfGetDB( DB_SLAVE );

			// Construct the list of user_ids in our range that pass the criteria
			$users = null;

			// Criterion 1: $NUM edits before $DATE
			if ( $this->params['list_edits-before'] ) {
				$timestamp = $dbr->addQuotes( $dbr->timestamp( $this->params['list_edits-before-date'] ) );

				$res = $dbr->select(
					'revision',
					'rev_user',
					array(
						"rev_user >= $min",
						"rev_user < $max",
						"rev_timestamp < $timestamp",
					),
					__METHOD__,
					array(
						'GROUP BY' => 'rev_user',
						'HAVING' => array(
							'COUNT(*) >= ' . $dbr->addQuotes( $this->params['list_edits-before-count'] ),
						)
					)
				);

				$list = array();
				foreach ( $res as $row ) {
					$list[] = $row->rev_user;
				}

				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_intersect( $users, $list );
				}
			}

			// Criterion 2: $NUM edits bewteen $DATE1 and $DATE2
			if ( $this->params['list_edits-between'] ) {
				$timestamp1 = $dbr->addQuotes( $dbr->timestamp( $this->params['list_edits-between-dates'][0] ) );
				$timestamp2 = $dbr->addQuotes( $dbr->timestamp( $this->params['list_edits-between-dates'][1] ) );

				$res = $dbr->select(
					'revision',
					'rev_user',
					array(
						"rev_user >= $min",
						"rev_user < $max",
						"rev_timestamp >= $timestamp1",
						"rev_timestamp < $timestamp2",
					),
					__METHOD__,
					array(
						'GROUP BY' => 'rev_user',
						'HAVING' => array(
							'COUNT(*) >= ' . $dbr->addQuotes( $this->params['list_edits-between-count'] ),
						)
					)
				);
				$list = array();
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
			if ( $this->params['list_exclude-groups'] ) {
				$res = $dbr->select(
					array( 'user', 'user_groups' ),
					'user_id',
					array(
						"user_id >= $min",
						"user_id < $max",
						'ug_user IS NULL',
					),
					__METHOD__,
					array(),
					array(
						'user_groups' => array( 'LEFT OUTER JOIN', array(
								'ug_user = user_id',
								'ug_group' => $this->params['list_exclude-groups'],
							)
						),
					)
				);
				$list = array();
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
					array(
						"ug_user >= $min",
						"ug_user < $max",
						'ug_group' => $this->params['list_include-groups']
					)
				);
				$list = array();
				foreach ( $res as $row ) {
					$list[] = $row->ug_user;
				}

				if ( $users === null ) {
					$users = $list;
				} else {
					$users = array_values( array_unique( array_merge( $users, $list ) ) );
				}
			}

			$ins = array();
			foreach ( $users as $user_id ) {
				$ins[] = array(
					'li_name' => $this->params['need-list'],
					'li_member' => $user_id,
				);
			}

			// Check again that the jobKey didn't change, holding a lock this time.
			$dbwMaster->begin();
			$dbwMaster->lock( "SecurePoll_PopulateVoterListJob-{$this->params['electionId']}", __METHOD__ );
			$dbw->begin();

			$jobKey = self::fetchJobKey( $dbwMaster, $this->params['electionId'] );
			if ( $jobKey === $this->params['jobKey'] ) {
				$dbw->delete( 'securepoll_lists', array(
					'li_name' => $this->params['need-list'],
					"li_member >= $min",
					"li_member < $max",
				) );
				$dbw->insert( 'securepoll_lists', $ins );

				$count = $dbwMaster->selectField( 'securepoll_properties', 'pr_value', array(
					'pr_entity' => $this->params['electionId'],
					'pr_key' => 'list_complete-count',
				) );
				$dbwMaster->update(
					'securepoll_properties',
					array(
						'pr_value' => $count + $max - $min,
					),
					array(
						'pr_entity' => $this->params['electionId'],
						'pr_key' => 'list_complete-count',
					)
				);
			}

			$dbw->commit();
			$dbwMaster->unlock( "SecurePoll_PopulateVoterListJob-{$this->params['electionId']}", __METHOD__ );
			$dbwMaster->commit();

			$next = $max;
		} catch ( Exception $ex ) {
			if ( $ex instanceof MWException ) {
				MWExceptionHandler::logException( $ex );
			}
			if ( $dbwMaster ) {
				try {
					$dbwMaster->unlock( "SecurePoll_PopulateVoterListJob-{$this->params['electionId']}", __METHOD__ );
					$dbwMaster->rollback( __METHOD__, 'flush' );
				} catch ( Exception $ex2 ) {}
			}
			if ( $dbw ) {
				try {
					$dbw->rollback( __METHOD__, 'flush' );
				} catch ( Exception $ex2 ) {}
			}
		}

		// Schedule the next run of this job, if necessary
		if ( $next <= $this->params['maxUserId'] ) {
			$params = $this->params;
			$params['nextUserId'] = $next;
			unset( $params['jobReleaseTimestamp'] );

			JobQueueGroup::singleton()->push( new JobSpecification(
				'securePollPopulateVoterList',
				$params,
				array(),
				$this->title
			) );
		}

		return true;
	}

	private static function fetchJobKey( IDatabase $db, $electionId ) {
		return $db->selectField(
			'securepoll_properties',
			'pr_value',
			array(
				'pr_entity' => $electionId,
				'pr_key' => 'list_job-key',
			)
		);
	}
}
