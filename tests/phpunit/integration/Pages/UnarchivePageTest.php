<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Pages;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\CsrfTokenSet;
use SpecialPageTestBase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\UnarchivePage
 */
class UnarchivePageTest extends SpecialPageTestBase {
	private Election $election;

	protected function setUp(): void {
		parent::setUp();

		// Create an election to archive
		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );

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
		$request->setVal( 'wpproperty_admins', $this->getTestSysop()->getUser()->getName() );
		[ $html, $page ] = $this->executeSpecialPage(
			'create', $request, null, $this->getTestSysop()->getAuthority()
		);

		$this->election = ( new Context() )->getElectionByTitle( $params['wpelection_title'] );
		$questions = $this->election->getQuestions();

		$this->assertStringContainsString( '(securepoll-create-created-text)', $html );
		$this->assertSame( $params['wpelection_title'], $this->election->title );
		$this->assertCount( 1, $questions );

		// Set time to after the election ends
		$threeDaysLater = wfTimestamp( TS_ISO_8601, $now + 3 * 86400 );
		ConvertibleTimestamp::setFakeTime( $threeDaysLater );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	public function testUnarchiveElectionNoToken(): void {
		// Archive the election
		$webRequest = new WebRequest();
		$tokenRepo = new CsrfTokenSet( $webRequest );
		$token = $tokenRepo->getToken()->toString();
		$webRequest->setVal( 'token', $token );
		[ $html ] = $this->executeSpecialPage(
			'archive/' . $this->election->getId(), $webRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->runJobs( [ 'minJobs' => 1 ], [ 'type' => 'securePollArchiveElection' ] );

		// Attempt to unarchive the page without a token and assert that it fails
		$archiveRequest = new WebRequest();
		[ $html ] = $this->executeSpecialPage(
			'unarchive/' . $this->election->getId(), $archiveRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( 'securepoll-unarchive-token-error', $html );
	}

	public function testUnarchiveElection(): void {
		// Archive the election
		$webRequest = new WebRequest();
		$tokenRepo = new CsrfTokenSet( $webRequest );
		$token = $tokenRepo->getToken()->toString();
		$webRequest->setVal( 'token', $token );
		[ $html ] = $this->executeSpecialPage(
			'archive/' . $this->election->getId(), $webRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->runJobs( [ 'minJobs' => 1 ], [ 'type' => 'securePollArchiveElection' ] );

		// Unarchive the election
		$webRequest = new WebRequest();
		$tokenRepo = new CsrfTokenSet( $webRequest );
		$token = $tokenRepo->getToken()->toString();
		$webRequest->setVal( 'token', $token );
		[ $html ] = $this->executeSpecialPage(
			'unarchive/' . $this->election->getId(), $webRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->runJobs( [ 'minJobs' => 1 ], [ 'type' => 'securePollUnarchiveElection' ] );

		// Assert that the page was unarchived
		$this->assertStringNotContainsString( 'securepoll-unarchive-token-error', $html );
		$this->assertStringContainsString( 'securepoll-unarchive-in-progress', $html );

		// Attempt to re-unarchive the election and assert that this fails
		[ $html ] = $this->executeSpecialPage(
			'unarchive/' . $this->election->getId(), $webRequest, null, $this->getTestSysop()->getAuthority()
		);
		$this->assertStringContainsString( 'securepoll-already-unarchived-error', $html );
	}
}
