<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\IndexPager;
use MediaWiki\SpecialPage\SpecialPage;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Pager for an election list. See TablePager documentation.
 */
class MainElectionsPager extends ElectionPager {
	private array $subpages = [
		'vote' => [
			'public' => true,
			'visible-before-start' => true,
			'visible-after-start' => true,
			'visible-after-close' => false,
		],
		'translate' => [
			'public' => true,
			'visible-before-start' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'list' => [
			'public' => true,
			'visible-before-start' => false,
			'visible-after-start' => true,
			'visible-after-close' => true,
		],
		'edit' => [
			'public' => false,
			'visible-before-start' => true,
			'visible-after-start' => true,
			'visible-after-close' => false,
		],
		'votereligibility' => [
			'public' => false,
			'visible-before-start' => true,
			'visible-after-start' => true,
			'visible-after-close' => false,
		],
		'dump' => [
			'public' => false,
			'visible-before-start' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
		],
		'dump-blt' => [
			'public' => false,
			'visible-before-start' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
		],
		'tallies' => [
			'public' => false,
			'visible-before-start' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
		],
		'log' => [
			'public' => false,
			'visible-before-start' => true,
			'visible-after-start' => true,
			'visible-after-close' => true,
			'link' => 'getLogLink'
		],
		'archive' => [
			'public' => false,
			'visible-before-start' => false,
			'visible-after-start' => false,
			'visible-after-close' => true,
			'token' => true
		]
	];
	private LinkRenderer $linkRenderer;
	private ILoadBalancer $loadBalancer;

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

	/** @inheritDoc */
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
				'el_entity NOT IN (' . $subquery->getSQL() . ')',
			],
		];
	}

	/**
	 * @return string HTML
	 */
	public function getStatus(): string {
		if ( $this->election->isFinished() ) {
			return $this->msg( 'securepoll-status-completed' )->escaped();
		} elseif ( $this->election->isStarted() ) {
			return $this->msg( 'securepoll-status-in-progress' )->escaped();
		} else {
			return $this->msg( 'securepoll-status-not-started' )->escaped();
		}
	}

	/**
	 * @return string HTML
	 */
	public function getLinks(): string {
		$pollId = (int)$this->mCurrentRow->el_entity;
		$html = '';
		$separator = $this->msg( 'pipe-separator' )->escaped();

		// Only show "Logs" link if the page Special:SecurePollLog is enabled
		if ( !$this->getConfig()->get( 'SecurePollUseLogging' ) ) {
			unset( $this->subpages['log'] );
		}

		foreach ( $this->subpages as $subpage => $props ) {
			// Message keys used here:
			// securepoll-subpage-list, securepoll-subpage-dump,
			// securepoll-subpage-dump-blt securepoll-subpage-votereligibility
			// securepoll-subpage-log, securepoll-subpage-archive
			$linkText = $this->msg( "securepoll-subpage-$subpage" )->text();
			if ( $subpage === "dump" ) {
				$linkText .= ' (XML)';
			}
			if ( $subpage === "dump-blt" ) {
				$linkText = $this->msg( "securepoll-subpage-dump" )->text() . ' (BLT)';
			}

			if ( $html !== '' ) {
				$html .= $separator;
			}

			$isNotStarted = !$this->election->isStarted() && !$this->election->isFinished();
			$isStartedButNotFinished = $this->election->isStarted() && !$this->election->isFinished();
			$isFinished = $this->election->isFinished();
			$needsHyperlink =
				( $this->isAdmin || $props['public'] ) &&
				(
					( $isFinished && $props['visible-after-close'] ) ||
					( $isStartedButNotFinished && $props['visible-after-start'] ) ||
					( $isNotStarted && $props['visible-before-start'] )
				);
			if ( $needsHyperlink ) {
				$queryParams = [];
				if ( isset( $props['token'] ) && $props['token'] ) {
					$queryParams[ 'token' ] = $this->page->specialPage->getContext()
						->getCsrfTokenSet()->getToken();
				}
				if ( isset( $props['link'] ) ) {
					$html .= $this->{$props['link']}( $pollId );
				} else {
					if ( $subpage === "dump-blt" ) {
						$title = $this->page->specialPage->getPageTitle( "dump/$pollId" );
						$html .= $this->linkRenderer->makeKnownLink( $title, $linkText, [], [ 'format' => 'blt' ] );
					} else {
						$title = $this->page->specialPage->getPageTitle( "$subpage/$pollId" );
						$html .= $this->linkRenderer->makeKnownLink( $title, $linkText, [], $queryParams );
					}
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

	/**
	 * Generate the link to the logs on SecurePollLog for an election
	 * @return string HTML
	 */
	public function getLogLink( int $id ): string {
		return $this->linkRenderer->makeLink(
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
