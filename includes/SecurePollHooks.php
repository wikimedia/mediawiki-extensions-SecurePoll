<?php

use MediaWiki\Session\SessionManager;

class SecurePollHooks {

	/**
	 * @param User $user
	 */
	public static function onUserLogout( $user ) {
		SessionManager::getGlobalSession()->remove( 'securepoll_voter' );
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$base = dirname( __DIR__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
				$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.sql" );
				$updater->modifyExtensionField( 'securepoll_votes', 'vote_ip',
					"$base/patches/patch-vote_ip-extend.sql" );
				$updater->addExtensionIndex( 'securepoll_options', 'spop_election',
					"$base/patches/patch-op_election-index.sql"
				);
				$updater->addExtensionField(
					'securepoll_elections',
					'el_owner',
					"$base/patches/patch-el_owner.sql"
				);
				break;
			case 'postgres':
				$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.pg.sql" );
				break;
			case 'sqlite':
				$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.sql" );
				break;
		}
	}

	/**
	 * @param array &$namespaces
	 */
	public static function onCanonicalNamespaces( &$namespaces ) {
		global $wgSecurePollUseNamespace;
		if ( $wgSecurePollUseNamespace ) {
			$namespaces[NS_SECUREPOLL] = 'SecurePoll';
			$namespaces[NS_SECUREPOLL_TALK] = 'SecurePoll_talk';
		}
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$errors
	 * @param bool $doExpensiveQueries
	 * @param bool $short
	 * @return bool
	 */
	public static function onTitleQuickPermissions(
		$title, $user, $action, &$errors, $doExpensiveQueries, $short
	) {
		global $wgSecurePollUseNamespace;
		if ( $wgSecurePollUseNamespace && $title->getNamespace() === NS_SECUREPOLL &&
			$action !== 'read'
		) {
			$errors[] = [ 'securepoll-ns-readonly' ];
			return false;
		}

		return true;
	}
}
