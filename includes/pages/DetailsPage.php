<?php

/**
 * Special:SecurePoll subpage for showing the details of a given vote to an administrator.
 */
class SecurePoll_DetailsPage extends SecurePoll_ActionPage {
	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		global $wgSecurePollKeepPrivateInfoDays;

		$out = $this->specialPage->getOutput();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		$this->voteId = intval( $params[0] );

		$db = $this->context->getDB();
		$row = $db->selectRow(
			[ 'securepoll_votes', 'securepoll_elections', 'securepoll_voters' ],
			'*',
			[
				'vote_id' => $this->voteId,
				'vote_election=el_entity',
				'vote_voter=voter_id',
			],
			__METHOD__
		);
		if ( !$row ) {
			$out->addWikiMsg( 'securepoll-invalid-vote', $this->voteId );
			return;
		}

		$this->election = $this->context->newElectionFromRow( $row );
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$vote_ip = '';
		$vote_xff = '';
		$vote_ua = '';
		if ( $row->el_end_date >=
			wfTimestamp( TS_MW, time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 ) )
		) {
			$vote_ip = IP::formatHex( $row->vote_ip );
			$vote_xff = $row->vote_xff;
			$vote_ua = $row->vote_ua;
		}

		$this->specialPage->setSubtitle( [
			$this->specialPage->getPageTitle( 'list/' . $this->election->getId() ),
			$this->msg( 'securepoll-list-title', $this->election->getMessage( 'title' ) )->text()
		] );

		if ( !$this->election->isAdmin( $this->specialPage->getUser() ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}
		# Show vote properties
		$out->setPageTitle( $this->msg(
			'securepoll-details-title', $this->voteId )->text() );

		$out->addHTML(
			'<table class="mw-datatable TablePager">' .
			$this->detailEntry( 'securepoll-header-id', $row->vote_id ) .
			$this->detailEntry( 'securepoll-header-timestamp', $row->vote_timestamp ) .
			$this->detailEntry( 'securepoll-header-voter-name', $row->voter_name ) .
			$this->detailEntry( 'securepoll-header-voter-type', $row->voter_type ) .
			$this->detailEntry( 'securepoll-header-voter-domain', $row->voter_domain ) .
			$this->detailEntry( 'securepoll-header-url', $row->voter_url ) .
			$this->detailEntry( 'securepoll-header-ip', $vote_ip ) .
			$this->detailEntry( 'securepoll-header-xff', $vote_xff ) .
			$this->detailEntry( 'securepoll-header-ua', $vote_ua ) .
			$this->detailEntry( 'securepoll-header-token-match', $row->vote_token_match ) .
			'</table>'
		);

		# Show voter properties
		$out->addHTML(
			'<h2>' . $this->msg( 'securepoll-voter-properties' )->escaped() . "</h2>\n"
		);
		$out->addHTML( '<table class="mw-datatable TablePager">' );
		$props = SecurePoll_Voter::decodeProperties( $row->voter_properties );
		foreach ( $props as $name => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			$out->addHTML(
				'<td class="securepoll-detail-header">' .
				htmlspecialchars( $name ) . "</td>\n" .
				'<td>' . htmlspecialchars( $value ) . "</td></tr>\n"
			);
		}
		$out->addHTML( '</table>' );

		# Show cookie dups
		$cmTable = $db->tableName( 'securepoll_cookie_match' );
		$voterId = intval( $row->voter_id );
		$sql = "(SELECT cm_voter_2 as voter, cm_timestamp FROM $cmTable WHERE cm_voter_1=$voterId)" .
			" UNION " .
			"(SELECT cm_voter_1 as voter, cm_timestamp FROM $cmTable WHERE cm_voter_2=$voterId)";
		$res = $db->query( $sql, __METHOD__ );
		if ( $res->numRows() ) {
			$lang = $this->specialPage->getLanguage();
			$out->addHTML( '<h2>' . $this->msg( 'securepoll-cookie-dup-list' )->escaped . '</h2>' );
			$out->addHTML( '<table class="mw-datatable TablePager">' );
			foreach ( $res as $row ) {
				$voter = $this->context->getVoter( $row->voter );
				$out->addHTML(
					'<tr>' .
					'<td>' . htmlspecialchars( $lang->timeanddate( $row->cm_timestamp ) ) . '</td>' .
					'<td>' .
					Xml::element(
						'a',
						[ 'href' => $voter->getUrl() ],
						$voter->getName() . '@' . $voter->getDomain()
					) .
					'</td></tr>'
				);
			}
			$out->addHTML( '</table>' );
		}

		# Show strike log
		$out->addHTML( '<h2>' . $this->msg( 'securepoll-strike-log' )->escaped() . "</h2>\n" );
		$pager = new SecurePoll_StrikePager( $this, $this->voteId );
		$out->addHTML(
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * Get a table row with a given header message and value
	 * @param string $header
	 * @param string $value
	 * @return string
	 */
	public function detailEntry( $header, $value ) {
		return "<tr>\n" .
			"<td class=\"securepoll-detail-header\">" .	$this->msg( $header )->escaped() . "</td>\n" .
			'<td>' . htmlspecialchars( $value ) . "</td></tr>\n";
	}

	/**
	 * Get a Title object for the current subpage.
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'details/' . $this->voteId );
	}
}

/**
 * Pager for the strike log. See TablePager documentation.
 */
class SecurePoll_StrikePager extends TablePager {
	/**
	 * @var SecurePoll_DetailsPage
	 */
	public $detailsPage;

	public $voteId;

	public function __construct( $detailsPage, $voteId ) {
		$this->detailsPage = $detailsPage;
		$this->voteId = $voteId;
		parent::__construct();
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'user', 'securepoll_strike' ],
			'fields' => '*',
			'conds' => [
				'st_vote' => $this->voteId,
				'st_user=user_id',
			],
			'options' => []
		];
	}

	public function formatValue( $name, $value ) {
		switch ( $name ) {
		case 'st_user':
			return Linker::userLink( $value, $this->mCurrentRow->user_name );
		case 'st_timestamp':
			return $this->getLanguage()->timeanddate( $value );
		default:
			return htmlspecialchars( $value );
		}
	}

	public function getDefaultSort() {
		return 'st_timestamp';
	}

	public function getFieldNames() {
		return [
			'st_timestamp' => $this->msg( 'securepoll-header-timestamp' )->escaped(),
			'st_user' => $this->msg( 'securepoll-header-admin' )->escaped(),
			'st_action' => $this->msg( 'securepoll-header-action' )->escaped(),
			'st_reason' => $this->msg( 'securepoll-header-reason' )->escaped(),
		];
	}

	public function getTitle() {
		return $this->detailsPage->getTitle();
	}

	public function isFieldSortable( $name ) {
		return $name == 'st_timestamp';
	}
}
