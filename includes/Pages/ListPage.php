<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use MediaWiki\Extensions\SecurePoll\Entities\Election;
use ResourceLoader;
use Status;
use Title;

/**
 * SecurePoll subpage to show a list of votes for a given election.
 * Provides an administrator interface for striking fraudulent votes.
 */
class ListPage extends ActionPage {
	/** @var Election */
	public $election;

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

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

		$out->setPageTitle(
			$this->msg(
				'securepoll-list-title',
				$this->election->getMessage( 'title' )
			)->text()
		);

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
				'vote_election' => $this->election->getID()
			],
			__METHOD__
		);
		$distinct_voters = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID()
			],
			__METHOD__
		);
		$all_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID(),
				'vote_current' => 0
			],
			__METHOD__
		);
		$not_current_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID(),
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
			$dbw = $this->context->getDB();
			$fields = [
				'spl_timestamp' => $dbw->timestamp( time() ),
				'spl_election_id' => $electionId,
				'spl_user' => $this->specialPage->getUser()->getId(),
				'spl_type' => self::LOG_TYPE_VIEWVOTES,

			];
			$dbw->insert( 'securepoll_log', $fields, __METHOD__ );
		}

		$out->addHTML( $pager->getLimitForm() . $pager->getNavigationBar() );
		$out->addParserOutputContent( $pager->getBodyOutput() );
		$out->addHTML( $pager->getNavigationBar() );
		if ( $isAdmin ) {
			$msgStrike = $this->msg( 'securepoll-strike-button' )->escaped();
			$msgUnstrike = $this->msg( 'securepoll-unstrike-button' )->escaped();
			$msgCancel = $this->msg( 'securepoll-strike-cancel' )->escaped();
			$msgReason = $this->msg( 'securepoll-strike-reason' )->escaped();
			$encAction = htmlspecialchars( $this->getTitle()->getLocalUrl() );
			$data = [
				'securepoll_strike_button' => $this->msg( 'securepoll-strike-button' )->text(),
				'securepoll_unstrike_button' => $this->msg( 'securepoll-unstrike-button' )->text()
			];
			$script = ResourceLoader::makeConfigSetScript( $data );
			$script = ResourceLoader::makeInlineScript( $script, $out->getCSP()->getNonce() );

			// @codingStandardsIgnoreStart
			$out->addHTML(
				<<<EOT
$script
<div class="securepoll-popup" id="securepoll-popup">
<form id="securepoll-strike-form" action="$encAction" method="post" onsubmit="securepoll_strike('submit');return false;">
<input type="hidden" id="securepoll-vote-id" name="vote_id" value=""/>
<input type="hidden" id="securepoll-action" name="action" value=""/>
<label for="securepoll-strike-reason">{$msgReason}</label>
<input type="text" size="45" id="securepoll-strike-reason"/>
<p>
<input class="securepoll-confirm-button" type="button" value="$msgCancel"
	onclick="securepoll_strike('cancel');"/>
<input class="securepoll-confirm-button" id="securepoll-strike-button"
	type="button" value="$msgStrike" onclick="securepoll_strike('strike');" />
<input class="securepoll-confirm-button" id="securepoll-unstrike-button"
	type="button" value="$msgUnstrike" onclick="securepoll_strike('unstrike');" />
</p>
</form>
<div id="securepoll-strike-result"></div>
<div id="securepoll-strike-spinner" class="mw-small-spinner"></div>
</div>
EOT
			// @codingStandardsIgnoreEnd
			);
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
		$strikeId = $dbw->nextSequenceValue( 'securepoll_strike_st_id' );
		$dbw->insert(
			'securepoll_strike',
			[
				'st_id' => $strikeId,
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
