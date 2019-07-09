<?php

/**
 * SecurePoll subpage to show a list of votes for a given election.
 * Provides an administrator interface for striking fraudulent votes.
 */
class SecurePoll_ListPage extends SecurePoll_ActionPage {
	/** @var SecurePoll_Election */
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

		$out->setPageTitle( $this->msg(
			'securepoll-list-title', $this->election->getMessage( 'title' ) )->text() );

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
			]
		);
		$distinct_voters = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID()
			]
		);
		$all_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID(),
				'vote_current' => 0
			]
		);
		$not_current_votes = $res->numRows();

		$res = $dbr->select(
			'securepoll_votes',
			[ 'vote_id' ],
			[
				'vote_election' => $this->election->getID(),
				'vote_struck' => 1
			]
		);
		$struck_votes = $res->numRows();

		$out->addHTML( '<div id="mw-poll-stats"><p>' .
			$this->msg( 'securepoll-voter-stats' )->numParams( $distinct_voters ) .
			'</p><p>' .
			$this->msg( 'securepoll-vote-stats' )
				->numParams( $all_votes, $not_current_votes, $struck_votes ) .
			'</p></div>' );

		$pager = new SecurePoll_ListPager( $this );
		$out->addHTML(
			$pager->getLimitForm() .
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
		if ( $isAdmin ) {
			$msgStrike = $this->msg( 'securepoll-strike-button' )->escaped();
			$msgUnstrike = $this->msg( 'securepoll-unstrike-button' )->escaped();
			$msgCancel = $this->msg( 'securepoll-strike-cancel' )->escaped();
			$msgReason = $this->msg( 'securepoll-strike-reason' )->escaped();
			$encAction = htmlspecialchars( $this->getTitle()->getLocalUrl() );
			$script = Skin::makeVariablesScript( [
				'securepoll_strike_button' => $this->msg( 'securepoll-strike-button' )->text(),
				'securepoll_unstrike_button' => $this->msg( 'securepoll-unstrike-button' )->text()
			] );

			// @codingStandardsIgnoreStart
			$out->addHTML( <<<EOT
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
		$dbw->insert( 'securepoll_strike',
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
		$dbw->update( 'securepoll_votes',
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

/**
 * A TablePager for showing a list of votes in a given election.
 * Shows much more information, and a strike/unstrike interface, if the user
 * is an admin.
 */
class SecurePoll_ListPager extends TablePager {
	public $listPage, $isAdmin, $election;

	public static $publicFields = [
		'vote_timestamp',
		'vote_voter_name',
		'vote_voter_domain',
	];

	public static $adminFields = [
		'details',
		'strike',
		'vote_timestamp',
		'vote_voter_name',
		'vote_voter_domain',
		'vote_ip',
		'vote_xff',
		'vote_ua',
		'vote_token_match',
		'vote_cookie_dup',
	];

	public function __construct( $listPage ) {
		$this->listPage = $listPage;
		$this->election = $listPage->election;
		$this->isAdmin = $this->election->isAdmin( $this->getUser() );
		parent::__construct();
	}

	public function getQueryInfo() {
		return [
			'tables' => 'securepoll_votes',
			'fields' => '*',
			'conds' => [
				'vote_election' => $this->listPage->election->getId()
			],
			'options' => []
		];
	}

	public function isFieldSortable( $field ) {
		return in_array( $field, [
			'vote_voter_name', 'vote_voter_domain', 'vote_timestamp', 'vote_ip'
		] );
	}

	public function formatValue( $name, $value ) {
		global $wgScriptPath, $wgSecurePollKeepPrivateInfoDays;
		$critical = Xml::element( 'img', [
			'src' => "$wgScriptPath/extensions/SecurePoll/resources/critical-32.png" ]
		);
		$voter = SecurePoll_Voter::newFromId(
			$this->listPage->context,
			$this->mCurrentRow->vote_voter
		);

		switch ( $name ) {
		case 'vote_timestamp':
			return $this->getLanguage()->timeanddate( $value );
		case 'vote_ip':
			if ( $this->election->endDate <
				wfTimestamp( TS_MW, time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 ) )
			) {
				return '';
			} else {
				return IP::formatHex( $value );
			}
		case 'vote_ua':
			if ( $this->election->endDate <
				wfTimestamp( TS_MW, time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 ) )
			) {
				return '';
			} else {
				return $value;
			}
		case 'vote_xff':
			if ( $this->election->endDate <
				wfTimestamp( TS_MW, time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 ) )
			) {
				return '';
			} else {
				return $value;
			}
		case 'vote_cookie_dup':
			$value = !$value;
			// fall through
		case 'vote_token_match':
			if ( $value ) {
				return '';
			} else {
				return $critical;
			}
		case 'details':
			$voteId = intval( $this->mCurrentRow->vote_id );
			$title = $this->listPage->specialPage->getPageTitle( "details/$voteId" );
			return Xml::element( 'a',
				[ 'href' => $title->getLocalUrl() ],
				$this->msg( 'securepoll-details-link' )->text()
			);
		case 'strike':
			$voteId = intval( $this->mCurrentRow->vote_id );
			if ( $this->mCurrentRow->vote_struck ) {
				$label = $this->msg( 'securepoll-unstrike-button' )->text();
				$action = "'unstrike'";
			} else {
				$label = $this->msg( 'securepoll-strike-button' )->text();
				$action = "'strike'";
			}
			$id = 'securepoll-popup-' . $voteId;
			return Xml::element( 'input',
				[
					'type' => 'button',
					'id' => $id,
					'value' => $label,
					'onclick' => "securepoll_strike_popup(event, $action, $voteId)"
				] );
		case 'vote_voter_name':
			$msg = $voter->isRemote()
				? 'securepoll-voter-name-remote'
				: 'securepoll-voter-name-local';
			return $this->msg(
				$msg,
				[ $value ]
			)->parse();
		default:
			return htmlspecialchars( $value );
		}
	}

	public function getDefaultSort() {
		return 'vote_timestamp';
	}

	public function getFieldNames() {
		$names = [];
		if ( $this->isAdmin ) {
			$fields = self::$adminFields;
		} else {
			$fields = self::$publicFields;
		}
		// Give grep a chance to find the usages:
		// securepoll-header-details, securepoll-header-strike, securepoll-header-timestamp,
		// securepoll-header-voter-name, securepoll-header-voter-domain, securepoll-header-ip,
		// securepoll-header-xff, securepoll-header-ua, securepoll-header-token-match,
		// securepoll-header-cookie-dup
		foreach ( $fields as $field ) {
			$names[$field] = $this->msg( 'securepoll-header-' . strtr( $field,
				[ 'vote_' => '', '_' => '-' ] ) )->text();
		}
		return $names;
	}

	public function getRowClass( $row ) {
		$classes = [];
		if ( !$row->vote_current ) {
			$classes[] = 'securepoll-old-vote';
		}
		if ( $row->vote_struck ) {
			$classes[] = 'securepoll-struck-vote';
		}
		return implode( ' ', $classes );
	}

	public function getTitle() {
		return $this->listPage->getTitle();
	}
}
