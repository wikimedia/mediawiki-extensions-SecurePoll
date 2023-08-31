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
		// The CentralAuth handler for UserGetRights would access the DB. Disable it, together with
		// all other hook handlers, since they're irrelevant here.
		$this->clearHook( 'UserGetRights' );
		$this->setService( 'UserGroupManager', $this->createMock( UserGroupManager::class ) );
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', null, null, $this->mockRegisteredNullAuthority() );
	}
}
