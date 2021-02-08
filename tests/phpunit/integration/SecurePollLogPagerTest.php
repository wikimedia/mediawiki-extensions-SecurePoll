<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\SecurePollLogPager;
use MediaWikiIntegrationTestCase;

/**
 * @covers MediaWiki\Extensions\SecurePoll\SecurePollLogPager
 */
class SecurePollLogPagerTest extends MediaWikiIntegrationTestCase {
	public function testGetQueryInfoNoFilters() {
		$pager = new SecurePollLogPager(
			$this->createMock( Context::class ),
			'all',
			'',
			'',
			''
		);
		$conds = $pager->getQueryInfo()['conds'];
		$this->assertSame( $conds, [] );
	}
}
