<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Context;
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
}
