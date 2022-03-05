<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\SpecialSecurePollLog;
use MediaWiki\User\UserFactory;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group SpecialPage
 * @covers \MediaWiki\Extension\SecurePoll\SpecialSecurePollLog
 */
class SpecialSecurePollLogTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialSecurePollLog(
			$this->createMock( UserFactory::class )
		);
	}

	public function testUserWrongPermissions() {
		$this->expectException( PermissionsError::class );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, [] );
		$this->executeSpecialPage( '', null, null, $user );
	}
}
