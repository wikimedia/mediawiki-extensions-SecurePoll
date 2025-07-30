<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Pages;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\CsrfTokenSet;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\VoterEligibilityPage
 */
class VoterEligibilityPageTest extends SpecialPageTestBase {
	private Election $election;

	protected function setUp(): void {
		parent::setUp();

		// Create an election to test against
		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );
		$performer = $this->getTestSysop()->getUser();

		$now = wfTimestamp();
		$tomorrow = wfTimestamp( TS_ISO_8601, $now + 86400 );
		$twoDaysLater = wfTimestamp( TS_ISO_8601, $now + 2 * 86400 );
		$params = [
			'wpelection_title' => 'Test Election',
			'wpquestions' => [
				[ 'text' => [ 'Question 1' ], 'options' => [ [ 'text' => 'Option 1' ] ] ]
			],
			'wpelection_startdate' => $tomorrow,
			'wpelection_enddate' => $twoDaysLater,
			'wpelection_type' => 'approval+plurality',
			'wpelection_crypt' => 'none',
			'wpreturn-url' => '',
		];

		$request = new FauxRequest( $params, true, );
		$request->setVal( 'wpproperty_admins', $performer->getName() );
		[ $html, $page ] = $this->executeSpecialPage(
			'create', $request, null, $this->getTestSysop()->getAuthority()
		);

		$this->election = ( new Context() )->getElectionByTitle( $params['wpelection_title'] );
		$questions = $this->election->getQuestions();

		$this->assertStringContainsString( '(securepoll-create-created-text)', $html );
		$this->assertSame( $params['wpelection_title'], $this->election->title );
		$this->assertCount( 1, $questions );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	public function testVoterEligibilityClearNoToken(): void {
		// Add voters to the voter eligibility list
		$voterEligibilityAddRequest = new FauxRequest( [
			'wpnames' => $this->getTestSysop()->getUser()->getName(),
		], true );
		[ $html ] = $this->executeSpecialPage(
			'votereligibility/' . $this->election->getId() . '/edit/voter',
			$voterEligibilityAddRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( 'securepoll-votereligibility-saved-text', $html );

		// Assert that clearing the voter eligibility list fails if no token is present
		$voterEligibilityClearRequest = new FauxRequest( [], false );
		[ $html ] = $this->executeSpecialPage(
			'votereligibility/' . $this->election->getId() . '/clear/voter',
			$voterEligibilityClearRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( 'securepoll-votereligibility-token-mismatch', $html );
	}

	public function testVoterEligibilityClear(): void {
		// Add voters to the voter eligibility list
		$voterEligibilityAddRequest = new FauxRequest( [
			'wpnames' => $this->getTestSysop()->getUser()->getName(),
		], true );
		[ $html ] = $this->executeSpecialPage(
			'votereligibility/' . $this->election->getId() . '/edit/voter',
			$voterEligibilityAddRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( 'securepoll-votereligibility-saved-text', $html );

		// Assert that the voter eligibility list is cleared
		$voterEligibilityClearRequest = new FauxRequest( [], false );
		$tokenRepo = new CsrfTokenSet( $voterEligibilityClearRequest );
		$token = $tokenRepo->getToken()->toString();
		$voterEligibilityClearRequest->setVal( 'token', $token );
		[ $html ] = $this->executeSpecialPage(
			'votereligibility/' . $this->election->getId() . '/clear/voter',
			$voterEligibilityClearRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringNotContainsString( 'securepoll-votereligibility-token-mismatch', $html );
		$this->assertStringContainsString( 'securepoll-votereligibility-cleared-text', $html );
	}
}
