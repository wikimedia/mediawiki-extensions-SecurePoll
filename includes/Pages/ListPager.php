<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\TablePager;
use MediaWiki\Xml\Xml;
use OOUI\ButtonWidget;
use Wikimedia\IPUtils;

/**
 * A TablePager for showing a list of votes in a given election.
 * Shows much more information, including voter's Personally Identifiable Information, and a strike/unstrike interface,
 * if the global user is an admin.
 */
class ListPager extends TablePager {
	/** @var ListPage */
	public $listPage;
	/** @var bool */
	public $isAdmin;
	/** @var Election */
	public $election;

	/** @var string[] */
	public static $publicFields = [
		// See T298434
		'vote_id' => 'securepoll-header-date',
		'vote_voter_name' => 'securepoll-header-voter-name',
		'vote_voter_domain' => 'securepoll-header-voter-domain',
	];

	/** @var string[] */
	public static $adminFields = [
		// See T298434
		'details' => 'securepoll-header-details',
		'strike' => 'securepoll-header-strike',
		'vote_id' => 'securepoll-header-timestamp',
		'vote_voter_name' => 'securepoll-header-voter-name',
		'vote_voter_domain' => 'securepoll-header-voter-domain',
		'vote_token_match' => 'securepoll-header-token-match',
		'vote_cookie_dup' => 'securepoll-header-cookie-dup',
	];

	/** @var string[] */
	public static $piiFields = [
		'vote_ip' => 'securepoll-header-ip',
		'vote_xff' => 'securepoll-header-xff',
		'vote_ua' => 'securepoll-header-ua',
	];

	/**
	 * Whether to include voter's Personally Identifiable Information.
	 *
	 * @var bool
	 */
	private $includeVoterPii;

	public function __construct( $listPage ) {
		$this->listPage = $listPage;
		$this->election = $listPage->election;

		$user = $this->getUser();

		$this->isAdmin = $this->election->isAdmin( $user );

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$this->includeVoterPii =
			$this->isAdmin && $permissionManager->userHasRight( $user, 'securepoll-view-voter-pii' );

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

	protected function isFieldSortable( $field ) {
		return in_array(
			$field,
			[
				'vote_voter_name',
				'vote_voter_domain',
				'vote_id',
				'vote_ip'
			]
		);
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return string HTML
	 */
	public function formatValue( $name, $value ) {
		$config = $this->listPage->specialPage->getConfig();
		$securePollKeepPrivateInfoDays = $config->get( 'SecurePollKeepPrivateInfoDays' );

		switch ( $name ) {
			case 'vote_timestamp':
				return 'Should be impossible (T298434)';
			case 'vote_id':
				if ( $this->isAdmin ) {
					return htmlspecialchars( $this->getLanguage()->timeanddate( $this->mCurrentRow->vote_timestamp ) );
				} else {
					return htmlspecialchars( $this->getLanguage()->date( $this->mCurrentRow->vote_timestamp ) );
				}
			case 'vote_ip':
				if ( $this->election->endDate < wfTimestamp(
						TS_MW,
						time() - ( $securePollKeepPrivateInfoDays * 24 * 60 * 60 )
					)
				) {
					return '';
				} else {
					return htmlspecialchars( IPUtils::formatHex( $value ) );
				}
			case 'vote_ua':
			case 'vote_xff':
				if ( $this->election->endDate < wfTimestamp(
						TS_MW,
						time() - ( $securePollKeepPrivateInfoDays * 24 * 60 * 60 )
					)
				) {
					return '';
				} else {
					return htmlspecialchars( $value );
				}
			case 'vote_cookie_dup':
				$value = !$value;
				if ( $value ) {
					return '';
				} else {
					return $this->msg( 'securepoll-vote-duplicate' )->escaped();
				}
			case 'vote_token_match':
				if ( $value ) {
					return '';
				} else {
					return $this->msg( 'securepoll-vote-csrf' )->escaped();
				}
			case 'details':
				$voteId = intval( $this->mCurrentRow->vote_id );
				$title = $this->listPage->specialPage->getPageTitle( "details/$voteId" );

				return Xml::element(
					'a',
					[ 'href' => $title->getLocalURL() ],
					$this->msg( 'securepoll-details-link' )->text()
				);
			case 'strike':
				$voteId = intval( $this->mCurrentRow->vote_id );
				if ( $this->mCurrentRow->vote_struck ) {
					$label = $this->msg( 'securepoll-unstrike-button' )->text();
					$action = "unstrike";
				} else {
					$label = $this->msg( 'securepoll-strike-button' )->text();
					$action = "strike";
				}
				$id = 'securepoll-popup-' . $voteId;

				return ( new ButtonWidget( [
					'id' => $id,
					'label' => $label,
				] ) )->setAttributes( [
					'data-action' => $action,
					'data-voteId' => $voteId,
				] );
			case 'vote_voter_name':
				$msg = Voter::newFromId(
					$this->listPage->context,
					$this->mCurrentRow->vote_voter,
					DB_REPLICA
				)->isRemote()
					? 'securepoll-voter-name-remote'
					: 'securepoll-voter-name-local';

				return $this->msg(
					$msg,
					[ wfEscapeWikitext( $value ), $value ]
				)->parse();

			default:
				return htmlspecialchars( $value );
		}
	}

	public function getDefaultSort() {
		// See T298434
		return 'vote_id';
	}

	protected function getFieldNames() {
		$names = [];
		if ( $this->isAdmin ) {
			$fields = self::$adminFields;

			if ( $this->includeVoterPii ) {
				$fields += self::$piiFields;
			}
		} else {
			$fields = self::$publicFields;
		}

		foreach ( $fields as $field => $headerMessageName ) {
			$names[$field] = $this->msg( $headerMessageName )->text();
		}

		return $names;
	}

	protected function getRowClass( $row ) {
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
