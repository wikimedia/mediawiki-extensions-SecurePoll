<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Pages;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use SpecialPageTestBase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\ListPage
 */
class ListPageTest extends SpecialPageTestBase {
	private Election $election;
	private Context $context;

	protected function setUp(): void {
		parent::setUp();

		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );

		$this->context = new Context();

		$this->createElection();

		// Set time to after the election has started
		$twoDaysLater = wfTimestamp( TS_ISO_8601, wfTimestamp() + 2 * 86400 );
		ConvertibleTimestamp::setFakeTime( $twoDaysLater );

		$this->vote();

		// Set time to after the election ends
		$fourDaysLater = wfTimestamp( TS_ISO_8601, wfTimestamp() + 4 * 86400 );
		ConvertibleTimestamp::setFakeTime( $fourDaysLater );

		$this->clearSecurePollLogs();
	}

	private function createElection() {
		$now = wfTimestamp();
		$tomorrow = wfTimestamp( TS_ISO_8601, $now + 86400 );
		$threeDaysLater = wfTimestamp( TS_ISO_8601, $now + 3 * 86400 );
		$params = [
			'wpelection_title' => 'Test Election',
			'wpquestions' => [
				[ 'text' => [ 'Question 1' ], 'options' => [ [ 'text' => 'Option 1' ] ] ]
			],
			'wpelection_startdate' => $tomorrow,
			'wpelection_enddate' => $threeDaysLater,
			// Approval vote
			'wpelection_type' => 'approval+plurality',
			'wpelection_crypt' => 'none',
			'wpreturn-url' => '',
		];

		$request = new FauxRequest( $params, true );
		$request->setVal( 'wpproperty_admins', $this->getTestSysop()->getUser()->getName() );
		[ $html ] = $this->executeSpecialPage(
			'create', $request, null, $this->getTestSysop()->getAuthority()
		);

		$this->election = $this->context->getElectionByTitle( $params['wpelection_title'] );
		$questions = $this->election->getQuestions();

		$this->assertStringContainsString( '(securepoll-create-created-text)', $html );
		$this->assertSame( $params['wpelection_title'], $this->election->title );
		$this->assertCount( 1, $questions );
		$this->assertTrue( $this->election->isAdmin( $this->getTestSysop()->getUser() ) );
	}

	/**
	 * Put a vote in the database
	 */
	private function vote() {
		$testSysopUsername = $this->getTestSysop()->getUser()->getName();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'securepoll_voters' )
			->row( [
				'voter_election' => $this->election->getId(),
				'voter_name' => $testSysopUsername,
				'voter_type' => 'local',
				'voter_domain' => 'localhost:8080',
				'voter_url' => 'http://localhost:8080/wiki/User' . $testSysopUsername,
				// blank since this is complicated to generate and not needed for these tests
				'voter_properties' => '',
			] )
			->caller( __METHOD__ )->execute();
		$voterId = $this->getDb()->insertId();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'securepoll_votes' )
			->row( [
				'vote_election' => $this->election->getId(),
				'vote_voter' => $voterId,
				'vote_voter_name' => $testSysopUsername,
				'vote_voter_domain' => '',
				'vote_struck' => 0,
				// blank since this is complicated to generate and not needed for these tests
				'vote_record' => '',
				// AC120001 is hex for 172.18.0.1
				'vote_ip' => 'AC120001',
				'vote_xff' => '',
				'vote_ua' => '',
				'vote_timestamp' => wfTimestampNow(),
				'vote_current' => 1,
				'vote_token_match' => 1,
				'vote_cookie_dup' => 0,
			] )
			->caller( __METHOD__ )->execute();
	}

	/**
	 * Clear any existing Special:SecurePollLog entries for this election
	 */
	private function clearSecurePollLogs() {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_log' )
			->where( '1=1' )
			->caller( __METHOD__ )
			->execute();
		$log = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'securepoll_log' )
			->where( [
				'spl_election_id' => $this->election->getId(),
			] )
			->caller( __METHOD__ )->fetchResultSet();
		$this->assertSame( 0, $log->numRows(), 'securepoll_log table is empty' );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	private function provideVisitingListPageIsLogged() {
		return [
			'logging turned off, not logged in' => [
				'logConfigVarTurnedOn' => false,
				'isLoggedInAsAdmin' => false,
				'piiOnListPageExpected' => false,
				'logEntryExpected' => false,
			],
			'logging turned off, logged in, election admin for this election, non-scrutineer' => [
				'logConfigVarTurnedOn' => false,
				'isLoggedInAsAdmin' => true,
				'piiOnListPageExpected' => false,
				'logEntryExpected' => false,
			],
			'logging turned off, logged in, election admin for this election, scrutineer' => [
				'logConfigVarTurnedOn' => false,
				'isLoggedInAsAdmin' => true,
				'piiOnListPageExpected' => true,
				'logEntryExpected' => false,
			],
			'logging turned on, not logged in' => [
				'logConfigVarTurnedOn' => true,
				'isLoggedInAsAdmin' => false,
				'piiOnListPageExpected' => false,
				'logEntryExpected' => false,
			],
			'logging turned on, logged in, election admin for this election, non-scrutineer' => [
				'logConfigVarTurnedOn' => true,
				'isLoggedInAsAdmin' => true,
				'piiOnListPageExpected' => false,
				'logEntryExpected' => false,
			],
			'logging turned on, logged in, election admin for this election, scrutineer' => [
				'logConfigVarTurnedOn' => true,
				'isLoggedInAsAdmin' => true,
				'piiOnListPageExpected' => true,
				'logEntryExpected' => true,
			],
		];
	}

	/**
	 * @dataProvider provideVisitingListPageIsLogged
	 */
	public function testVisitingListPageIsLogged( $logConfigVarTurnedOn, $isLoggedInAsAdmin, $piiOnListPageExpected, $logEntryExpected ) {
		$this->setMwGlobals( 'wgSecurePollUseLogging', $logConfigVarTurnedOn );

		// set scrutineer user right
		$this->setGroupPermissions( 'sysop', 'securepoll-view-voter-pii', $piiOnListPageExpected );

		// Log in as admin using RequestContext. Passing an admin user to executeSpecialPage() is
		// not sufficient.
		if ( $isLoggedInAsAdmin ) {
			RequestContext::getMain()->setUser( $this->getTestSysop()->getUser() );
		}
		// This line must be after RequestContext::getMain()->setUser(). I guess setUser() wipes
		// the user language.
		RequestContext::getMain()->setLanguage( 'qqx' );

		// visit list page
		$webRequest = new WebRequest();
		$authority = $isLoggedInAsAdmin ? $this->getTestSysop()->getAuthority() : null;
		[ $html ] = $this->executeSpecialPage(
			'list/' . $this->election->getId(), $webRequest, null, $authority
		);
		$this->assertStringContainsString( 'securepoll-voter-name-local', $html,
			'list page contains voters' );
		if ( $piiOnListPageExpected ) {
			$this->assertStringContainsString( 'securepoll-header-ip', $html,
				'list page contains PII' );
		} else {
			$this->assertStringNotContainsString( 'securepoll-header-ip', $html,
				'list page did not contain PII' );
		}

		// check for a Special:SecurePollLog entry
		$this->runJobs( [ 'minJobs' => 0 ] );
		$log = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'securepoll_log' )
			->where( [
				'spl_election_id' => $this->election->getId(),
			] )
			->caller( __METHOD__ )->fetchResultSet();
		if ( $logEntryExpected ) {
			$this->assertSame( 1, $log->numRows(), 'log entry created' );
			$this->assertSame( ActionPage::LOG_TYPE_VIEWVOTES, (int)$log->current()->spl_type,
				'log entry is the correct type' );
		} else {
			$this->assertSame( 0, $log->numRows(), 'no log entry created' );
		}
	}
}
