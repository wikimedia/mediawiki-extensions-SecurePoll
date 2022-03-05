<?php

namespace MediaWiki\Extension\SecurePoll\HookHandler;

use MediaWiki\Session\SessionManager;
use MediaWiki\User\Hook\UserLogoutHook;

class LogoutHandler implements UserLogoutHook {
	/**
	 * @inheritDoc
	 */
	public function onUserLogout( $user ) {
		SessionManager::getGlobalSession()->remove( 'securepoll_voter' );
	}
}
