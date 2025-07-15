<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use MediaWiki\Extension\SecurePoll\Maintenance\UpdateNotBlockedKey;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Maintenance\UpdateNotBlockedKey
 */
class UpdateNotBlockedKeyTest extends MaintenanceBaseTestCase {

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return UpdateNotBlockedKey::class;
	}

	/**
	 * Make sure this key name doesn't change if we change the class's namespace.
	 * Else this creates a bug where this gets run twice during updates.
	 */
	public function testGetUpdateKey() {
		/** @var TestingAccessWrapper $maintenance */
		$maintenance = $this->maintenance;
		$this->assertSame( 'UpdateNotBlockedKey', $maintenance->getUpdateKey() );
	}
}
