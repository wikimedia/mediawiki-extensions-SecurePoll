<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\SpecialSecurePollLog;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group SpecialPage
 * @covers \MediaWiki\Extension\SecurePoll\SpecialSecurePollLog
 */
class SpecialSecurePollLogTest extends SpecialPageTestBase {
	use MockAuthorityTrait;

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialSecurePollLog(
			$this->createMock( UserFactory::class )
		);
	}

	public function testUserWrongPermissions() {
		$this->setService( 'UserGroupManager', $this->createMock( UserGroupManager::class ) );
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', null, null, $this->mockRegisteredNullAuthority() );
	}
}
