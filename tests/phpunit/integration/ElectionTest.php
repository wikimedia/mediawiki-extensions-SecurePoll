<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Entities\Election
 */
class ElectionTest extends MediaWikiIntegrationTestCase {
	private Context $context;

	protected function setUp(): void {
		$this->context = Context::newFromXmlFile( dirname( __DIR__ ) . '/data/election-tests.xml' );
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
}
