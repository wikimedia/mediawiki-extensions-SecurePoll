<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\User\User;
use MediaWiki\Utils\MWTimestamp;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election
 */
class ElectionTest extends MediaWikiIntegrationTestCase {
	private Context $context;

	private User $testAdmin;

	private User $testUser;

	protected function setUp(): void {
		$this->context = Context::newFromXmlFile( dirname( __DIR__ ) . '/data/election-tests.xml' );
		$this->testAdmin = $this->getTestSysop()->getUser();
		$this->testUser = $this->getTestUser()->getUser();

		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );
	}

	/**
	 * Creates and inserts an election into the database.
	 *
	 * @param array $options Configuration options for the election (optional):
	 *   - title: The title of the election
	 *   - ballot: The ballot type to use (e.g. approval)
	 *   - tally: The tally method to use (e.g. plurality)
	 *   - startDate: The start date in MediaWiki timestamp format (e.g. 20250624000000)
	 *   - endDate: The end date in MediaWiki timestamp format (e.g. 20250630000000)
	 * @return Election The created election entity
	 */
	private function createElection( array $options = [] ): Election {
		$context = new Context();
		$dbw = $this->getDb();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_entity' )
			->row( [ 'en_type' => 'election' ] )
			->caller( __METHOD__ )
			->execute();
		$electionId = $dbw->insertId();

		$now = MWTimestamp::now( TS_MW );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_elections' )
			->row( [
				'el_entity' => $electionId,
				'el_title' => $options['title'] ?? 'Test Election',
				'el_owner' => $this->testAdmin->getId(),
				'el_ballot' => $options['ballot'] ?? 'stv',
				'el_tally' => $options['tally'] ?? 'droop-quota',
				'el_primary_lang' => 'en',
				'el_start_date' => $options['startDate'] ?? $now,
				'el_end_date' => $options['endDate'] ?? $now,
				'el_auth_type' => 'local',
			] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_properties' )
			->row( [
				'pr_entity' => $electionId,
				'pr_key' => 'admins',
				'pr_value' => $this->testAdmin->getName(),
			] )
			->caller( __METHOD__ )
			->execute();

		return $context->getElection( $electionId );
	}

	/**
	 * Inserts a new voter into the database.
	 */
	private function createVoter( int $electionId ): Voter {
		$context = new Context();

		return Voter::createVoter( $context, [
			'electionId' => $electionId,
			'name' => 'Voter',
			'type' => 'local',
			'domain' => 'test.example.org',
			'url' => 'http://test.example.org/wiki/User:Voter',
			'properties' => [],
		] );
	}

	/**
	 * Inserts a vote into the database (only works with DB elections).
	 */
	private function addVote( int $electionId, int $voterId, bool $current, bool $struck ): void {
		$context = new Context();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'securepoll_votes' )
			->row( [
				'vote_election' => $electionId,
				'vote_voter' => $voterId,
				'vote_voter_name' => "TestVoter{$voterId}",
				'vote_voter_domain' => 'test.example.org',
				'vote_record' => 'test_vote_record',
				'vote_ip' => IPUtils::toHex( '127.0.0.1' ),
				'vote_xff' => '',
				'vote_ua' => 'TestUserAgent',
				'vote_timestamp' => MWTimestamp::now( TS_MW ),
				'vote_current' => $current ? 1 : 0,
				'vote_token_match' => 1,
				'vote_struck' => $struck ? 1 : 0,
				'vote_cookie_dup' => 0,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public function testAllowCrossWikiPartiallyBlockedVoters(): void {
		$election = $this->context->getElection( 100 );
		$params = [
			'properties' => [
				'groups' => [],
				'blocked' => false,
				'isSitewideBlocked' => false,
				'central-block-count' => 1,
				'central-sitewide-block-count' => 0,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowCrossWikiPartiallyBlockedVoters(): void {
		$election = $this->context->getElection( 101 );
		$params = [
			'properties' => [
				'groups' => [],
				'blocked' => false,
				'isSitewideBlocked' => false,
				'central-block-count' => 1,
				'central-sitewide-block-count' => 0,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-blocked-centrally', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testWithDefaults(): void {
		$election = $this->context->getElection( 102 );
		$params = [
			'properties' => [
				'groups' => [],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testExcludeList(): void {
		$election = $this->context->getElection( 103 );
		$params = [
			'properties' => [
				'groups' => [],
				'lists' => [ 'TestUser' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-in-exclude-list', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testIncludeList(): void {
		// This election has a min edit requirement of 100 that gets bypassed
		// by using the include list.
		$election = $this->context->getElection( 104 );
		$params = [
			'properties' => [
				'groups' => [],
				'lists' => [ 'TestUser' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testAllowAboveEditCount(): void {
		$election = $this->context->getElection( 105 );
		$params = [
			'properties' => [
				'groups' => [],
				'edit-count' => 100,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowBelowEditCount(): void {
		$election = $this->context->getElection( 105 );
		$params = [
			'properties' => [
				'groups' => [],
				'edit-count' => 99,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-too-few-edits', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testAllowBeforeMaxRegistration(): void {
		$election = $this->context->getElection( 106 );
		$params = [
			'properties' => [
				'groups' => [],
				'registration' => 1737414000,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowAfterMaxRegistration(): void {
		$election = $this->context->getElection( 106 );
		$params = [
			'properties' => [
				'groups' => [],
				'registration' => 1737415000,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-too-new', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testDisallowSitewideBlock(): void {
		$election = $this->context->getElection( 107 );
		$params = [
			'properties' => [
				'groups' => [],
				'blocked' => true,
				'isSitewideBlocked' => true,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-blocked', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testDisallowPartialBlock(): void {
		$election = $this->context->getElection( 108 );
		$params = [
			'properties' => [
				'groups' => [],
				'blocked' => true,
				'isSitewideBlocked' => false,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-blocked-partial', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testDisallowBots(): void {
		$election = $this->context->getElection( 109 );
		$params = [
			'properties' => [
				'groups' => [],
				'bot' => true,
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-bot', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testAllowIfInGroup(): void {
		$election = $this->context->getElection( 110 );
		$params = [
			'properties' => [
				'groups' => [ 'TestGroup' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowIfNotInGroup(): void {
		$election = $this->context->getElection( 110 );
		$params = [
			'properties' => [
				'groups' => [],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-not-in-group', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testAllowIfInEligibilityList(): void {
		$election = $this->context->getElection( 111 );
		$params = [
			'properties' => [
				'groups' => [],
				'lists' => [ 'TestUser' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowIfNotInEligibilityList(): void {
		$election = $this->context->getElection( 111 );
		$params = [
			'properties' => [
				'groups' => [],
				'lists' => [ 'TestUser2' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-not-in-list', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testAllowIfInCentralEligibilityList(): void {
		$election = $this->context->getElection( 112 );
		$params = [
			'properties' => [
				'groups' => [],
				'central-lists' => [ 'TestUser' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertTrue( $isQualified->isOK() );
	}

	public function testDisallowIfNotInCentralEligibilityList(): void {
		$election = $this->context->getElection( 112 );
		$params = [
			'properties' => [
				'groups' => [],
				'central-lists' => [ 'TestUser2' ],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );
		$this->assertEquals( 'securepoll-not-in-list', $isQualified->getMessages( 'error' )[0]->getKey() );
	}

	public function testCustomErrorMessage(): void {
		$election = $this->context->getElection( 113 );
		$params = [
			'properties' => [
				'groups' => [],
			],
		];
		$isQualified = $election->getQualifiedStatus( $params );
		$this->assertFalse( $isQualified->isOK() );

		$unqualifiedError = $isQualified->getMessages( 'error' )[0];
		$this->assertEquals( 'securepoll-not-in-list', $unqualifiedError->getKey() );

		$customError = $isQualified->getMessages( 'error' )[1];
		$this->assertEquals( 'securepoll-custom-unqualified', $customError->getKey() );
		$this->assertEquals( 'This is a custom message.', $customError->getParams()[0] );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isStarted
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isFinished
	 */
	public function testIsStartedAndNotFinished(): void {
		$election = $this->context->getElection( 100 );

		$yesterday = MWTimestamp::getInstance( strtotime( '-1 day' ) )->getTimestamp( TS_MW );
		$tomorrow = MWTimestamp::getInstance( strtotime( '+1 day' ) )->getTimestamp( TS_MW );

		$election->startDate = $yesterday;
		$election->endDate = $tomorrow;

		$this->assertTrue( $election->isStarted() );
		$this->assertFalse( $election->isFinished() );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isStarted
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isFinished
	 */
	public function testIsStartedAndFinished(): void {
		$election = $this->context->getElection( 100 );

		$yesterday = MWTimestamp::getInstance( strtotime( '-1 day' ) )->getTimestamp( TS_MW );
		$dayBeforeYesterday = MWTimestamp::getInstance( strtotime( '-2 day' ) )->getTimestamp( TS_MW );

		$election->startDate = $dayBeforeYesterday;
		$election->endDate = $yesterday;

		$this->assertTrue( $election->isStarted() );
		$this->assertTrue( $election->isFinished() );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isStarted
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isFinished
	 */
	public function testIsNotStartedAndNotFinished(): void {
		$election = $this->context->getElection( 100 );

		$tomorrow = MWTimestamp::getInstance( strtotime( '+1 day' ) )->getTimestamp( TS_MW );
		$dayAfterTomorrow = MWTimestamp::getInstance( strtotime( '+2 days' ) )->getTimestamp( TS_MW );

		$election->startDate = $tomorrow;
		$election->endDate = $dayAfterTomorrow;

		$this->assertFalse( $election->isStarted() );
		$this->assertFalse( $election->isFinished() );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::getVotesCount
	 */
	public function testGetVotesCount(): void {
		$election = $this->createElection();
		$electionId = $election->getId();

		// Add 2 non-current votes (should not be counted).
		$this->addVote( $electionId, 1, false, false );
		$this->addVote( $electionId, 2, false, false );

		// Add 2 current, non-struck votes (should be counted).
		$this->addVote( $electionId, 1, true, false );
		$this->addVote( $electionId, 2, true, false );

		// Add 1 struck vote (should not be counted).
		$this->addVote( $electionId, 3, true, true );

		// Test that getVotesCount only returns valid votes.
		$count = $election->getVotesCount();
		$this->assertEquals( 2, $count );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::isAdmin
	 */
	public function testIsAdmin(): void {
		$election = $this->createElection();

		$this->assertTrue( $election->isAdmin( $this->testAdmin ) );
		$this->assertFalse( $election->isAdmin( $this->testUser ) );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election::hasVoted
	 */
	public function testHasVoted(): void {
		$election = $this->createElection();
		$electionId = $election->getId();

		$voter1 = $this->createVoter( $electionId );
		$voter2 = $this->createVoter( $electionId );

		$this->addVote( $electionId, 1, true, false );

		$this->assertTrue( $election->hasVoted( $voter1 ) );
		$this->assertFalse( $election->hasVoted( $voter2 ) );
	}
}
