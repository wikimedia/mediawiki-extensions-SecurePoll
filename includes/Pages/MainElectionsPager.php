<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use Html;
use IndexPager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Pager for an election list. See TablePager documentation.
 */
class MainElectionsPager extends ElectionPager {
	/** @var array[] */
	private $subpages = [
		'vote' => [
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => false,
		],
		'translate' => [
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'list' => [
			'public' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'edit' => [
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => false,
		],
		'votereligibility' => [
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'dump' => [
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'tally' => [
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'log' => [
			'public' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
			'link' => 'getLogLink'
		],
		'archive' => [
			'public' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
		]
	];
	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var ILoadBalancer */
	private $loadBalancer;

	public function __construct(
		EntryPage $specialPage,
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer
	) {
		$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		parent::__construct();
		$this->page = $specialPage;
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
	}

	public function getQueryInfo() {
		$subquery = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA )->buildSelectSubquery(
			'securepoll_properties',
			'pr_entity',
			[ 'pr_key' => 'is-archived' ],
			__METHOD__
		);

		return [
			'tables' => 'securepoll_elections',
			'fields' => '*',
			'conds' => [
				'el_entity NOT IN ' . $subquery,
			],
		];
	}

	public function getLinks() {
		$id = (int)$this->mCurrentRow->el_entity;

		$s = '';
		$sep = $this->msg( 'pipe-separator' )->text();
		foreach ( $this->subpages as $subpage => $props ) {
			// Message keys used here:
			// securepoll-subpage-vote, securepoll-subpage-translate,
			// securepoll-subpage-list, securepoll-subpage-dump,
			// securepoll-subpage-tally, securepoll-subpage-votereligibility
			// securepoll-subpage-log, securepoll-subpage-archive
			$linkText = $this->msg( "securepoll-subpage-$subpage" )->text();
			if ( $s !== '' ) {
				$s .= $sep;
			}
			if (
				( $this->isAdmin || $props['public'] ) &&
				( !$this->election->isStarted() ||
					( $this->election->isStarted() && $this->election->isFinished() ) ||
					$props['visible-after-start'] ) &&
				( !$this->election->isFinished() || $props['visible-after-close'] )
			) {
				if ( isset( $props['link'] ) ) {
					$s .= $this->{$props['link']}( $id );
				} else {
					$title = $this->page->specialPage->getPageTitle( "$subpage/$id" );
					$s .= $this->linkRenderer->makeKnownLink( $title, $linkText );
				}
			} else {
				$s .= Html::rawElement(
					'span',
					[
						'class' => 'securepoll-link-disabled',
					],
					$linkText
				);
			}
		}

		return $s;
	}

	/**
	 * Generate the link to the logs on SecurePollLog for an election
	 * @param int $id
	 * @return string
	 */
	public function getLogLink( $id ) {
		$services = MediaWikiServices::getInstance();
		$linkRenderer = $services->getLinkRenderer();
		return $linkRenderer->makeLink(
			SpecialPage::getTitleValueFor( 'SecurePollLog' ),
			$this->msg( 'securepoll-subpage-log' )->text(),
			[],
			[
				'type' => 'all',
				'election_name' => $this->page->context->getElection( $id )->title,
			]
		);
	}
}
