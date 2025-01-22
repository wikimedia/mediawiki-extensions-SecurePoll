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

	public function testAllowPartiallyBlockedVoters(): void {
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

	public function testDisallowPartiallyBlockedVoters(): void {
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
	}
}
