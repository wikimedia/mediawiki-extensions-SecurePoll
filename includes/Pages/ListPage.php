<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use JobQueueGroup;
use JobSpecification;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;

/**
 * SecurePoll subpage to show a list of votes for a given election.
 * Provides an administrator interface for striking fraudulent votes.
 */
class ListPage extends ActionPage {
	/** @var Election */
	public $election;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $specialPage );
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );

			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

			return;
		}
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$out->setPageTitleMsg( $this->msg( 'securepoll-list-title', $this->election->getMessage( 'title' ) ) );

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );

		if ( $this->election->getProperty( 'voter-privacy' ) && !$isAdmin ) {
			$out->addWikiMsg( 'securepoll-list-private' );

			return;
		}

		$dbr = $this->election->context->getDB( DB_REPLICA );

		$res = $dbr->select(
			'securepoll_votes',
			[ 'DISTINCT vote_voter' ],
			[
				'vote_election' => $this->election->getId(),
				'vote_struck' => 0
			],
			__METHOD__
		);
		$distinct_voters = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getId()
			],
			__METHOD__
		);
		$all_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getId(),
				'vote_current' => 0
			],
			__METHOD__
		);
		$not_current_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getId(),
				'vote_struck' => 1
			],
			__METHOD__
		);
		$struck_votes = $res->numRows();

		$out->addHTML(
			'<div id="mw-poll-stats">' . $this->msg( 'securepoll-voter-stats' )->numParams(
				$distinct_voters
			)->parseAsBlock() . $this->msg( 'securepoll-vote-stats' )->numParams(
					$all_votes,
					$not_current_votes,
					$struck_votes
				)->parseAsBlock() . '</div>'
		);

		$pager = new ListPager( $this );

		// If an admin is viewing the votes, log it
		$securePollUseLogging = $this->specialPage->getConfig()->get( 'SecurePollUseLogging' );
		if ( $isAdmin && $securePollUseLogging ) {
			$fields = [
				'spl_election_id' => $electionId,
				'spl_user' => $this->specialPage->getUser()->getId(),
				'spl_type' => self::LOG_TYPE_VIEWVOTES,

			];
			$this->jobQueueGroup->push(
				new JobSpecification(
					'securePollLogAdminAction',
					[ 'fields' => $fields ],
					[],
					$this->getTitle()
				)
			);
		}

		$out->addHTML( $pager->getLimitForm() . $pager->getNavigationBar() );
		$out->addParserOutputContent( $pager->getBodyOutput() );
		$out->addHTML( $pager->getNavigationBar() );
		if ( $isAdmin ) {
			$out->addJsConfigVars( 'SecurePollSubPage', 'list' );
			$out->addModules( 'ext.securepoll.htmlform' );
		}
	}

	/**
	 * The strike/unstrike backend.
	 * @param string $action strike or unstrike
	 * @param int $voteId The vote ID
	 * @param string $reason The reason
	 * @return Status
	 */
	public function strike( $action, $voteId, $reason ) {
		$dbw = $this->context->getDB();
		// this still gives the securepoll-need-admin error when an admin tries to
		// delete a nonexistent vote.
		if ( !$this->election->isAdmin( $this->specialPage->getUser() ) ) {
			return Status::newFatal( 'securepoll-need-admin' );
		}
		if ( $action != 'strike' ) {
			$action = 'unstrike';
		}

		$dbw->startAtomic( __METHOD__ );
		// Add it to the strike log
		$dbw->insert(
			'securepoll_strike',
			[
				'st_vote' => $voteId,
				'st_timestamp' => wfTimestampNow(),
				'st_action' => $action,
				'st_reason' => $reason,
				'st_user' => $this->specialPage->getUser()->getId()
			],
			__METHOD__
		);
		// Update the status cache
		$dbw->update(
			'securepoll_votes',
			[ 'vote_struck' => intval( $action == 'strike' ) ],
			[ 'vote_id' => $voteId ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		return Status::newGood();
	}

	/**
	 * @return Title object
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'list/' . $this->election->getId() );
	}
}
