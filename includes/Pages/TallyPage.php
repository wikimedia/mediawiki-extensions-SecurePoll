<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Linker\LinkRenderer;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A subpage for tallying votes and producing results
 */
class TallyPage extends ActionPage {

	/** @var int */
	private $tallyId;

	public function __construct(
		SpecialSecurePoll $specialPage,
		private readonly LinkRenderer $linkRenderer,
		private readonly ILoadBalancer $loadBalancer,
	) {
		parent::__construct( $specialPage );
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}

		$this->tallyId = intval( $params[2] );

		$subtitleLink = $this->linkRenderer->makeKnownLink(
			$this->specialPage->getPageTitle( "tallies/{$this->election->getId()}" ),
			$this->msg( 'securepoll-tally-list-title', $this->election->getMessage( 'title' ) )->text()
		);
		$out->setSubtitle( '&lt; ' . $subtitleLink );

		$user = $this->specialPage->getUser();
		$this->initLanguage( $user, $this->election );
		$out->setPageTitleMsg( $this->msg( 'securepoll-tally-title', $this->election->getMessage( 'title' ) ) );

		if ( !$this->election->isAdmin( $user ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		$this->showTallyResult();
	}

	/**
	 * Show the tally result if one has previously been calculated
	 */
	private function showTallyResult(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$tally = $this->election->getTallyFromDb( $dbr, $this->tallyId );
		if ( !$tally ) {
			$out->addWikiMsg( 'securepoll-invalid-tally', $this->tallyId );
			return;
		}

		$time = $tally['resultTime'];
		$tallier = $this->context->newElectionTallier( $this->election );
		$tallier->loadJSONResult( $tally['result'] );

		$out->addHTML(
			$out->msg( 'securepoll-tally-result' )
				->rawParams( $tallier->getHtmlResult() )
				->dateTimeParams( wfTimestamp( TS_UNIX, $time ) )
		);
	}
}
