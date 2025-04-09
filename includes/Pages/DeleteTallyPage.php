<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Linker\LinkRenderer;
use OOUI\MessageWidget;
use Wikimedia\Rdbms\ILoadBalancer;

class DeleteTallyPage extends ActionPage {

	private LinkRenderer $linkRenderer;

	private JobQueueGroup $jobQueueGroup;

	private ILoadBalancer $loadBalancer;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LinkRenderer $linkRenderer
	 * @param JobQueueGroup $jobQueueGroup
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LinkRenderer $linkRenderer,
		JobQueueGroup $jobQueueGroup,
		ILoadBalancer $loadBalancer
	) {
		parent::__construct( $specialPage );

		$this->linkRenderer = $linkRenderer;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->loadBalancer = $loadBalancer;
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
		$tallyId = intval( $params[2] );

		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}

		$subtitleLink = $this->linkRenderer->makeKnownLink(
			$this->specialPage->getPageTitle( "tallies/{$this->election->getId()}" ),
			$this->msg(
				'securepoll-tally-list-title',
				$this->election->getMessage( 'title' )
			)->text()
		);
		$out->setSubtitle( '&lt; ' . $subtitleLink );

		$user = $this->specialPage->getUser();
		$this->initLanguage( $user, $this->election );
		$out->setPageTitleMsg(
			$this->msg( 'securepoll-delete-tally-title', $this->election->getMessage( 'title' ) )
		);

		if ( !$this->election->isAdmin( $user ) ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-need-admin' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-not-finished' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		$token = $this->specialPage->getContext()->getCsrfTokenSet()->getToken();
		$request = $this->specialPage->getRequest();
		$tokenMatch = $token->match( $request->getVal( 'token' ) );
		if ( !$tokenMatch ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-deletetally-token-error' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		$this->jobQueueGroup->push(
			new JobSpecification(
				'securePollDeleteTally',
				[ 'electionId' => $this->election->getId(), 'tallyId' => $tallyId ],
				[]
			)
		);
		$out->prependHTML( ( new MessageWidget( [
			'label' => $this->msg( 'securepoll-delete-in-progress' )->text(),
			'type' => 'success',
		] ) ) );
	}
}
