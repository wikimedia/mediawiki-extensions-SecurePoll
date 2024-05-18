<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\IndexPager;
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
		$subquery = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA )->newSelectQueryBuilder()
			->select( 'pr_entity' )
			->from( 'securepoll_properties' )
			->where( [ 'pr_key' => 'is-archived' ] )
			->caller( __METHOD__ );

		return [
			'tables' => 'securepoll_elections',
			'fields' => '*',
			'conds' => [
				'el_entity IN (' . $subquery->getSQL() . ')',
			],
		];
	}

	/**
	 * @return string HTML
	 */
	public function getLinks() {
		$id = $this->mCurrentRow->el_entity;

		$s = '';
		$sep = $this->msg( 'pipe-separator' )->escaped();
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
				$s .= Html::element(
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
