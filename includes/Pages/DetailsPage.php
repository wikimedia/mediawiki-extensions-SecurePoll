<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\Title\Title;
use MediaWiki\Xml\Xml;
use Wikimedia\IPUtils;

/**
 * Special:SecurePoll subpage for showing the details of a given vote to an administrator.
 */
class DetailsPage extends ActionPage {
	/** @var int|null */
	public $voteId;

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

		$this->voteId = intval( $params[0] );

		$db = $this->context->getDB();
		$row = $db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'securepoll_votes' )
			->join( 'securepoll_elections', null, 'vote_election=el_entity' )
			->join( 'securepoll_voters', null, 'vote_voter=voter_id' )
			->where( [ 'vote_id' => $this->voteId ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			$out->addWikiMsg( 'securepoll-invalid-vote', $this->voteId );

			return;
		}

		$this->election = $this->context->newElectionFromRow( $row );
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$vote_ip = '';
		$vote_xff = '';
		$vote_ua = '';
		if ( $row->el_end_date >= wfTimestamp(
				TS_MW,
				time() - ( $this->specialPage->getConfig()->get( 'SecurePollKeepPrivateInfoDays' ) * 24 * 60 * 60 )
			)
		) {
			$vote_ip = IPUtils::formatHex( $row->vote_ip );
			$vote_xff = $row->vote_xff;
			$vote_ua = $row->vote_ua;
		}

		$this->specialPage->setSubtitle(
			[
				$this->specialPage->getPageTitle( 'list/' . $this->election->getId() ),
				$this->msg( 'securepoll-list-title', $this->election->getMessage( 'title' ) )->text()
			]
		);

		if ( !$this->election->isAdmin( $this->specialPage->getUser() ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );

			return;
		}
		// Show vote properties
		$out->setPageTitleMsg( $this->msg( 'securepoll-details-title', $this->voteId ) );

		$out->addHTML(
			'<table class="mw-datatable TablePager">' . $this->detailEntry(
				'securepoll-header-id',
				$row->vote_id
			) . $this->detailEntry(
				'securepoll-header-timestamp',
				$row->vote_timestamp
			) . $this->detailEntry(
				'securepoll-header-voter-name',
				$row->voter_name
			) . $this->detailEntry(
				'securepoll-header-voter-type',
				$row->voter_type
			) . $this->detailEntry(
				'securepoll-header-voter-domain',
				$row->voter_domain
			) . $this->detailEntry( 'securepoll-header-url', $row->voter_url ) . $this->detailEntry(
				'securepoll-header-ip',
				$vote_ip
			) . $this->detailEntry( 'securepoll-header-xff', $vote_xff ) . $this->detailEntry(
				'securepoll-header-ua',
				$vote_ua
			) . $this->detailEntry(
				'securepoll-header-token-match',
				$row->vote_token_match
			) . '</table>'
		);

		// Show voter properties
		$out->addHTML(
			'<h2>' . $this->msg( 'securepoll-voter-properties' )->escaped() . "</h2>\n"
		);
		$out->addHTML( '<table class="mw-datatable TablePager">' );
		$props = Voter::decodeProperties( $row->voter_properties );
		foreach ( $props as $name => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			$out->addHTML(
				'<td class="securepoll-detail-header">' . htmlspecialchars(
					$name
				) . "</td>\n" . '<td>' . htmlspecialchars( (string)$value ) . "</td></tr>\n"
			);
		}
		$out->addHTML( '</table>' );

		// Show cookie dups
		$voterId = intval( $row->voter_id );
		$res = $db->newUnionQueryBuilder()
			->add(
				$db->newSelectQueryBuilder()
					->select( [
						'voter' => 'cm_voter_2',
						'cm_timestamp',
					] )
					->from( 'securepoll_cookie_match' )
					->where( [
						'cm_voter_1' => $voterId,
					] )
			)
			->add(
				$db->newSelectQueryBuilder()
					->select( [
						'voter' => 'cm_voter_1',
						'cm_timestamp',
					] )
					->from( 'securepoll_cookie_match' )
					->where( [
						'cm_voter_2' => $voterId,
					] )
			)
			->caller( __METHOD__ )
			->fetchResultSet();
		if ( $res->numRows() ) {
			$lang = $this->specialPage->getLanguage();
			$out->addHTML(
				'<h2>' . $this->msg( 'securepoll-cookie-dup-list' )->escaped() . '</h2>'
			);
			$out->addHTML( '<table class="mw-datatable TablePager">' );
			foreach ( $res as $row ) {
				$voter = $this->context->getVoter( $row->voter, DB_REPLICA );
				$out->addHTML(
					'<tr>' . '<td>' . htmlspecialchars(
						$lang->timeanddate( $row->cm_timestamp )
					) . '</td>' . '<td>' . Xml::element(
						'a',
						[ 'href' => $voter->getUrl() ],
						$voter->getName() . '@' . $voter->getDomain()
					) . '</td></tr>'
				);
			}
			$out->addHTML( '</table>' );
		}

		// Show strike log
		$out->addHTML( '<h2>' . $this->msg( 'securepoll-strike-log' )->escaped() . "</h2>\n" );
		$pager = new StrikePager( $this, $this->voteId );
		$out->addParserOutputContent(
			$pager->getFullOutput()
		);
	}

	/**
	 * Get a table row with a given header message and value
	 * @param string $header
	 * @param string $value
	 * @return string
	 */
	public function detailEntry( $header, $value ) {
		return "<tr>\n" . "<td class=\"securepoll-detail-header\">" . $this->msg(
				$header
			)->escaped() . "</td>\n" . '<td>' . htmlspecialchars( $value ) . "</td></tr>\n";
	}

	/**
	 * Get a Title object for the current subpage.
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'details/' . $this->voteId );
	}
}
