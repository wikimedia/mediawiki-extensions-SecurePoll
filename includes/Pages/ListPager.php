<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use MediaWiki\Extensions\SecurePoll\User\Voter;
use TablePager;
use Wikimedia\IPUtils;
use Xml;

/**
 * A TablePager for showing a list of votes in a given election.
 * Shows much more information, and a strike/unstrike interface, if the user
 * is an admin.
 */
class ListPager extends TablePager {
	public $listPage, $isAdmin, $election;

	public static $publicFields = [
		'vote_timestamp' => 'securepoll-header-date',
		'vote_voter_name' => 'securepoll-header-voter-name',
		'vote_voter_domain' => 'securepoll-header-voter-domain',
	];

	public static $adminFields = [
		'details' => 'securepoll-header-details',
		'strike' => 'securepoll-header-strike',
		'vote_timestamp' => 'securepoll-header-timestamp',
		'vote_voter_name' => 'securepoll-header-voter-name',
		'vote_voter_domain' => 'securepoll-header-voter-domain',
		'vote_ip' => 'securepoll-header-ip',
		'vote_xff' => 'securepoll-header-xff',
		'vote_ua' => 'securepoll-header-ua',
		'vote_token_match' => 'securepoll-header-token-match',
		'vote_cookie_dup' => 'securepoll-header-cookie-dup',
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
		return in_array(
			$field,
			[
				'vote_voter_name',
				'vote_voter_domain',
				'vote_timestamp',
				'vote_ip'
			]
		);
	}

	public function formatValue( $name, $value ) {
		global $wgScriptPath, $wgSecurePollKeepPrivateInfoDays;
		$critical = Xml::element(
			'img',
			[
				'src' => "$wgScriptPath/extensions/SecurePoll/resources/critical-32.png"
			]
		);

		$voter = Voter::newFromId(
			$this->listPage->context,
			$this->mCurrentRow->vote_voter
		);

		switch ( $name ) {
			case 'vote_timestamp':
				if ( $this->isAdmin ) {
					return $this->getLanguage()->timeanddate( $value );
				} else {
					return $this->getLanguage()->date( $value );
				}
			case 'vote_ip':
				if ( $this->election->endDate < wfTimestamp(
						TS_MW,
						time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 )
					)
				) {
					return '';
				} else {
					return IPUtils::formatHex( $value );
				}
			case 'vote_ua':
				if ( $this->election->endDate < wfTimestamp(
						TS_MW,
						time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 )
					)
				) {
					return '';
				} else {
					return $value;
				}
			case 'vote_xff':
				if ( $this->election->endDate < wfTimestamp(
						TS_MW,
						time() - ( $wgSecurePollKeepPrivateInfoDays * 24 * 60 * 60 )
					)
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

				return Xml::element(
					'a',
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

				return Xml::element(
					'input',
					[
						'type' => 'button',
						'id' => $id,
						'value' => $label,
						'onclick' => "securepoll_strike_popup(event, $action, $voteId)"
					]
				);
			case 'vote_voter_name':
				$msg = $voter->isRemote(
				) ? 'securepoll-voter-name-remote' : 'securepoll-voter-name-local';

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

		foreach ( $fields as $field => $headerMessageName ) {
			$names[$field] = $this->msg( $headerMessageName )->text();
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
