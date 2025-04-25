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
	private array $subpages = [
		'unarchive' => [
			'public' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
			'token' => true
		]
	];
	private LinkRenderer $linkRenderer;
	private ILoadBalancer $loadBalancer;

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
	 */
	public function getQueryInfo(): array {
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
	public function getStatus(): string {
		return $this->msg( 'securepoll-status-archived' )->escaped();
	}

	/**
	 * @return string HTML
	 */
	public function getLinks(): string {
		$pollId = $this->mCurrentRow->el_entity;
		$html = '';
		$separator = $this->msg( 'pipe-separator' )->escaped();
		foreach ( $this->subpages as $subpage => $props ) {
			// Message keys used here:
			// securepoll-subpage-unarchive
			$linkText = $this->msg( "securepoll-subpage-$subpage" )->text();

			if ( $html !== '' ) {
				$html .= $separator;
			}

			$needsHyperlink =
				( $this->isAdmin || $props['public'] ) &&
				(
					!$this->election->isStarted() ||
					( $this->election->isStarted() && $this->election->isFinished() ) ||
					$props['visible-after-start']
				) &&
				( !$this->election->isFinished() || $props['visible-after-close'] );
			if ( $needsHyperlink ) {
				$queryParams = [];
				if ( isset( $props['token'] ) && $props['token'] ) {
					$queryParams[ 'token' ] = $this->page->specialPage->getContext()
						->getCsrfTokenSet()->getToken();
				}
				if ( isset( $props['link'] ) ) {
					$html .= $this->{$props['link']}( $pollId );
				} else {
					$title = $this->page->specialPage->getPageTitle( "$subpage/$pollId" );
					$html .= $this->linkRenderer->makeKnownLink( $title, $linkText, [], $queryParams );
				}
			} else {
				$html .= Html::element(
					'span',
					[ 'class' => 'securepoll-link-disabled' ],
					$linkText
				);
			}
		}

		return $html;
	}
}
