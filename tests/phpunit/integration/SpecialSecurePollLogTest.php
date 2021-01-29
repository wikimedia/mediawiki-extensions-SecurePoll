<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use MediaWiki\Extensions\SecurePoll\SpecialSecurePollLog;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group SpecialPage
 * @covers MediaWiki\Extensions\SecurePoll\SpecialSecurePollLog
 */
class SpecialSecurePollLogTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialSecurePollLog();
	}

	public function testUserWrongPermissions() {
		$this->expectException( PermissionsError::class );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, [] );
		$this->executeSpecialPage( '', null, null, $user );
	}
}
