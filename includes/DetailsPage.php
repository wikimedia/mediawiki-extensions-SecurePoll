<?php

class SecurePoll_DetailsPage extends SecurePoll_Page {
	function execute( $params ) {
		global $wgOut, $wgUser;

		if ( !count( $params ) ) {
			$wgOut->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}
		
		$this->voteId = intval( $params[0] );
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( 
			array( 'securepoll_votes', 'securepoll_elections', 'securepoll_voters' ),
			'*',
			array(
				'vote_id' => $this->voteId,
				'vote_election=el_entity',
				'vote_user=voter_id',
			),
			__METHOD__
		);
		if ( !$row ) {
			$wgOut->addWikiMsg( 'securepoll-invalid-vote', $this->voteId );
			return;
		}

		$this->election = SecurePoll_Election::newFromRow( $row );
		$this->initLanguage( $wgUser, $this->election );
		if ( !$this->election->isAdmin( $wgUser ) ) {
			$wgOut->addWikiMsg( 'securepoll-need-admin' );
			return;
		}
		$wgOut->setPageTitle( wfMsg( 
			'securepoll-details-title', $this->voteId ) );

		$wgOut->addHTML(
			'<table class="TablePager">' .
			$this->detailEntry( 'securepoll-header-id', $row->vote_id ) .
			$this->detailEntry( 'securepoll-header-timestamp', $row->vote_timestamp ) .
			$this->detailEntry( 'securepoll-header-user-name', $row->voter_name ) .
			$this->detailEntry( 'securepoll-header-user-type', $row->voter_type ) .
			$this->detailEntry( 'securepoll-header-user-domain', $row->voter_domain ) .
			$this->detailEntry( 'securepoll-header-authority', $row->voter_authority ) .
			$this->detailEntry( 'securepoll-header-ip', IP::formatHex( $row->vote_ip ) ) .
			$this->detailEntry( 'securepoll-header-xff', $row->vote_xff ) .
			$this->detailEntry( 'securepoll-header-ua', $row->vote_ua ) .
			$this->detailEntry( 'securepoll-header-token-match', $row->vote_token_match ) .
			'</table>'
		);
		$wgOut->addHTML( '<h2>' . wfMsgHTML( 'securepoll-voter-properties' ) . "</h2>\n" );
		$wgOut->addHTML( '<table class="TablePager">' );
		$props = SecurePoll_User::decodeProperties( $row->voter_properties );
		foreach ( $props as $name => $value ) {
			$wgOut->addHTML( 
				'<td class="securepoll-detail-header">' .
				htmlspecialchars( $name ) . "</td>\n" .
				'<td>' . htmlspecialchars( $value ) . "</td></tr>\n"
			);
		}
		$wgOut->addHTML( 
			'</table>' .
			'<h2>' . wfMsgHTML( 'securepoll-strike-log' ) . "</h2>\n" );
		$pager = new SecurePoll_StrikePager( $this, $this->voteId );
		$wgOut->addHTML(
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	function detailEntry( $header, $value ) {
		return "<tr>\n" .
			"<td class=\"securepoll-detail-header\">" .	wfMsgHTML( $header ) . "</td>\n" .
			'<td>' . htmlspecialchars( $value ) . "</td></tr>\n";
	}

	function getTitle() {
		return $this->parent->getTitle( 'details/' . $this->voteId );
	}
}

class SecurePoll_StrikePager extends TablePager {
	var $detailsPage, $voteId;
	function __construct( $detailsPage, $voteId ) {
		$this->detailsPage = $detailsPage;
		$this->voteId = $voteId;
		parent::__construct();
	}

	function getQueryInfo() {
		return array(
			'tables' => array( 'user', 'securepoll_strike' ),
			'fields' => '*',
			'conds' => array(
				'st_vote' => $this->voteId,
				'st_user=user_id',
			),
			'options' => array()
		);
	}

	function formatValue( $name, $value ) {
		global $wgUser, $wgLang;
		$skin = $wgUser->getSkin();
		switch ( $name ) {
		case 'st_user':
			return $skin->userLink( $value, $this->mCurrentRow->user_name );
		case 'st_timestamp':
			return $wgLang->timeanddate( $value );
		default:
			return htmlspecialchars( $value );
		}
	}

	function getDefaultSort() {
		return 'st_timestamp';
	}

	function getFieldNames() {
		return array(
			'st_timestamp' => wfMsgHtml( 'securepoll-header-timestamp' ),
			'st_user' => wfMsgHtml( 'securepoll-header-admin' ),
			'st_action' => wfMsgHtml( 'securepoll-header-action' ),
			'st_reason' => wfMsgHtml( 'securepoll-header-reason' ),
		);
	}

	function getTitle() {
		return $this->detailsPage->getTitle();
	}

	function isFieldSortable( $name ) {
		return $name == 'st_timestamp';
	}
}
