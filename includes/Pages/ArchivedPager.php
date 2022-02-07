<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Html;
use IndexPager;
use MediaWiki\Linker\LinkRenderer;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Pager for an archived election list. See TablePager documentation.
 */
class ArchivedPager extends ElectionPager {
	/** @var array[] */
	private $subpages = [
		'unarchive' => [
			'public' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
		]
	];

	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ArchivedPage $specialPage
	 * @param LinkRenderer $linkRenderer
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		ArchivedPage $specialPage,
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer
	) {
		$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		parent::__construct();
		$this->page = $specialPage;
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Return query paramters in array form to get archived elections
	 * @return array
	 */
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
				'el_entity IN ' . $subquery,
			],
		];
	}

	public function getLinks() {
		$id = $this->mCurrentRow->el_entity;

		$s = '';
		$sep = $this->msg( 'pipe-separator' )->text();
		foreach ( $this->subpages as $subpage => $props ) {
			// Message keys used here:
			// securepoll-subpage-unarchive
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
}
