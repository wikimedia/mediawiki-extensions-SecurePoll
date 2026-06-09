<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\SecurePollLogPager;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\SecurePollLogPager
 * @group Database
 */
class SecurePollLogPagerTest extends MediaWikiIntegrationTestCase {
	public function testGetQueryInfoNoFilters() {
		$pager = new SecurePollLogPager(
			$this->createMock( Context::class ),
			$this->createMock( UserFactory::class ),
			'all',
			'',
			'',
			'',
			0,
			0,
			0,
			[]
		);
		$conds = $pager->getQueryInfo()['conds'];
		$this->assertSame( [], $conds );
	}

	public function testGetBody(): void {
		$testUser = $this->getTestSysop()->getUserIdentity();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'securepoll_log' )
			->row( [
				'spl_election_id' => 123,
				'spl_user' => $testUser->getId(),
				'spl_type' => ActionPage::LOG_TYPE_VIEWVOTEDETAILS,
				'spl_timestamp' => $this->getDb()->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();

		$mockElection = $this->createMock( Election::class );
		$mockElection->title = 'Test election';

		$mockContext = $this->createMock( Context::class );
		$mockContext->method( 'getElection' )
			->with( 123 )
			->willReturn( $mockElection );

		$pager = new SecurePollLogPager(
			$mockContext,
			$this->getServiceContainer()->getUserFactory(),
			'all',
			'',
			'',
			'',
			0,
			0,
			0,
			[]
		);

		$requestContext = new DerivativeContext( RequestContext::getMain() );
		$requestContext->setLanguage( 'qqx' );
		$pager->setContext( $requestContext );

		$actualHtml = $pager->getBody();
		$this->assertStringContainsString( 'securepoll-log-action-type-3', $actualHtml );
		$this->assertStringContainsString( $testUser->getName(), $actualHtml );
		$this->assertStringContainsString( 'Test election', $actualHtml );
	}
}
