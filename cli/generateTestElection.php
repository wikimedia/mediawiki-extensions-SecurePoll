<?php

use MediaWiki\Extension\SecurePoll\Ballots\STVBallot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class GenerateTestElection extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generate an election and its results given the parameters' );

		$this->addOption( 'name', 'Name of the election' );
		$this->addOption( 'election', 'Type of election', true );
		$this->addOption( 'ballots', 'File with ballots', true );
		$this->addOption( 'admins', 'pipe delimited list of electionadmins', true );
		$this->addOption( 'reset', 'Delete votes if an election already exists' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$startTime = time();

		// Check if the election already exists
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->begin( __METHOD__ );
		$electionName = $this->getOption( 'name' );
		$electionId = $dbw->selectField(
			'securepoll_elections',
			'el_entity',
			[
				'el_title' => $electionName
			],
			__METHOD__
		);

		if ( $electionId ) {
			if ( $this->hasOption( 'reset' ) ) {
				$this->deleteVotes( $dbw, $electionId );
			} else {
				$this->fatalError(
					'A poll with this id already exists and has votes. ' .
					'Pass --reset to delete existing votes and continue ' .
					'or pass along an unused election name'
				);
			}
		}

		$fileName = $this->getOption( 'ballots' );
		if ( !file_exists( $fileName ) ) {
			$this->fatalError( "The specified file \"{$fileName}\" does not exist\n" );
		}

		// Check all users psased in admins param are members of electionadmin
		$electionAdmins = explode( '|', $this->getOption( 'admins' ) );
		$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
		foreach ( $electionAdmins as $admin ) {
			$user = $this->getServiceContainer()->getUserFactory()->newFromName( $admin );
			if (
				!$user ||
				!in_array(
					'electionadmin',
					$userGroupManager->getUserEffectiveGroups( $user )
				)
			) {
				$this->fatalError( $admin . ' is not a member of electionadmin' );
			}
		}

		// All users are eligible to be election admins. Implode with a newline for intake
		$electionAdmins = implode( "\n", $electionAdmins );

		// Supported election types:
		// - stv
		$electionType = $this->getOption( 'election' );
		switch ( $electionType ) {
			case 'stv':
				$ballotsFile = file_get_contents( $fileName );
				$ballotsFile = explode( "\n", $ballotsFile );

				// Get the candidate and seat count
				$stvParameters = explode( ' ', array_shift( $ballotsFile ) );

				// Check if anyone withdrew
				// (We currently do nothing with this information)
				$withdrew = array_shift( $ballotsFile );
				if ( is_numeric( trim( explode( ' ', $withdrew )[0] ) ) && (int)$withdrew[0] >= 0 ) {
					// It's actually a ballot. Put it back.
					array_unshift( $ballotsFile, $withdrew );
				}

				// If no $electionId, the election must be created
				if ( !$electionId ) {
					$electionId = $this->generateSTVElection(
						$electionName,
						$electionAdmins,
						$stvParameters[0],
						$stvParameters[1]
					);
					$this->output( "\n" );
					$this->output( 'Trying to create an election with id ' . $electionId );
				}

				// If no electionId is returned, election generation failed and should throw
				if ( !$electionId ) {
					$this->output( "\n" );
					$this->fatalError( 'Election not created. Aborting.' );
				}

				$ballots = $this->generateSTVBallots( $dbw, $electionId, $stvParameters, $ballotsFile );
				break;
			default:
				$this->fatalError( 'Election type not supported' );
		}

		$this->output( "\n" );
		$this->output( 'Inserting ballots' );
		$this->writeBallots( $dbw, $electionId, $ballots );
		$dbw->commit( __METHOD__ );

		$this->output( "\n" );
		$this->output( 'Finished! You can tally your election at ' .
			SpecialPage::getTitleFor( 'SecurePoll' )->getFullURL() . '/tally/' . $electionId
		);

		$endTime = time();
		$timeElapsed = $endTime - $startTime;
		$this->output( "\n" );
		$this->output( "Script finished in $timeElapsed s" );
		$this->output( "\n" );
	}

	private function deleteVotes( $dbw, $electionId ) {
		$dbw->delete(
			'securepoll_votes',
			[ 'vote_election' => $electionId ]
		);
	}

	private function writeBallots( $dbw, $electionId, $ballots ) {
		foreach ( $ballots as $ballot ) {
			$dbw->insert( 'securepoll_votes',
				[
					'vote_election' => $electionId,
					'vote_voter' => 1,
					'vote_voter_name' => 'Admin',
					'vote_voter_domain' => 'localhost:8081',
					'vote_struck' => 0,
					'vote_record' => $ballot,
					'vote_ip' => 'AC120001',
					'vote_xff' => "",
					'vote_ua' => 'Mozilla/5.0 (X11; Linux ppc64le; rv:78.0) Gecko/20100101 Firefox/78.0',
					'vote_timestamp' => date( 'YmdHis' ),
					'vote_current' => 1,
					'vote_token_match' => 1,
					'vote_cookie_dup' => 0
				],
				__METHOD__
			);
		}
	}

	private function generateSTVElection( $name, $admins, $candidateCount, $seatCount ) {
		// To avoid re-writing the insertion logic,
		// get the processInput() function from the CreatePage
		$services = MediaWikiServices::getInstance();
		$createPage = new CreatePage(
			new SpecialSecurePoll(
				$services->getService( 'SecurePoll.ActionPageFactory' )
			),
			$services->getDBLoadBalancerFactory(),
			$services->getUserGroupManager(),
			$services->getLanguageNameUtils(),
			$services->getWikiPageFactory(),
			$services->getUserFactory()
		);
		if ( !$name ) {
			$name = 'STV @' . time();
		}

		// Stub out form data
		$formData = [
			'election_id' => '-1',
			'election_title' => $name,
			'election_primaryLang' => 'en',
			'election_startdate' => ( new DateTime )->modify( '-1 days' )->format( 'Y-m-d' ),
			'election_enddate' => ( new DateTime )->format( 'Y-m-d' ),
			'return-url' => '',
			'election_type' => 'stv+droop-quota',
			'election_crypt' => 'none',
			'disallow-change' => false,
			'voter-privacy' => false,
			'property_admins' => $admins,
			'shuffle-questions' => false,
			'shuffle-options' => false,
			'must-rank-all' => false,
			'must-answer-all' => false,
			'gpg-encrypt-key' => null,
			'gpg-sign-key' => null,
			'questions' => [],
			'comment' => null,
		];

		// Add question and options
		$options = [];
		for ( $i = 1; $i <= $candidateCount; $i++ ) {
			$option = [
				'id' => '-1',
				'text' => 'Option ' . $i,
				'name' => null,
			];
			$options[] = $option;
		}
		$formData['questions'][] = [
			'id' => '-1',
			'text' => 'Question 1',
			'min-score' => null,
			'max-score' => null,
			'default-score' => null,
			'column-order' => null,
			'column-label-msgs' => false,
			'column-messages' => null,
			'min-seats' => $seatCount,
			'options' => $options,
		];

		// Create election
		return $createPage->processInput( $formData, null )->getValue();
	}

	private function generateSTVBallots( $dbw, $electionId, $stvParameters, $ballots ) {
		// Make sure the parameters match the election
		$context = new Context;
		$election = $context->getElection( $electionId );

		// STV expects only one question
		$question = $election->getQuestions()[0];
		$options = $question->getOptions();

		// Option count must match
		if ( count( $options ) !== (int)$stvParameters[0] ) {
			$this->fatalError(
				'Option count mismatch. ' .
				'Ballot options: ' . $stvParameters[0] .
				' Election options: ' . count( $options )
			);
		}

		// Seat count must match
		$seatCount = $question->getProperty( 'min-seats' );
		if ( (int)$seatCount !== (int)$stvParameters[1] ) {
			$this->fatalError(
				'Seat count mismatch. ' .
				'Ballot options: ' . $stvParameters[1] .
				' Election options: ' . $seatCount
			);
		}

		// Start generating ballots
		$formattedBallots = [];
		$candidates = [];
		$baseBallot = new STVBallot( $context, $election );
		// This is technically a list of ballots, candidates, and an election name
		$endOfBallots = false;
		foreach ( $ballots as $entity ) {
			// Signal the end of ballots
			if ( $entity === '0' ) {
				$endOfBallots = true;
			}

			if ( !$endOfBallots ) {
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
				$entity = explode( ' ', $entity );
				$ballotCount = array_shift( $entity );

				$record = '';
				foreach ( $entity as $rank => $choice ) {
					// Short circuit when we reach 0, the stop indicator
					if ( (int)$choice === 0 ) {
						break;
					}
					$choiceId = $options[ (int)$choice - 1 ]->getId();
					$record .= $baseBallot->packRecord( $question, $choiceId, $rank );
				}

				for ( $i = 1; $i <= $ballotCount; $i++ ) {
					$formattedBallots[] = $record;
				}
			} elseif ( $entity !== '0' ) {
				// Gather list of candidates after ballots have been recorded
				if ( count( $candidates ) < count( $options ) ) {
					$candidates[] = $entity;
				}
			}
		}

		// Map candidates to options
		foreach ( $candidates as $i => $candidate ) {
			$option = $options[$i];
			$optionId = $option->getId();

			$dbw->update(
				'securepoll_msgs',
				[
					'msg_entity' => $optionId,
					'msg_text' => $candidate
				],
				[
					'msg_entity' => $optionId
				],
				__METHOD__
			);
		}

		return $formattedBallots;
	}
}

$maintClass = GenerateTestElection::class;
require_once RUN_MAINTENANCE_IF_MAIN;
