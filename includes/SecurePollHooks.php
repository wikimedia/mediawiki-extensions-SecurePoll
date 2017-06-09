<?php

class SecurePollHooks {

	/**
	 * @param $user User
	 * @return bool
	 */
	public static function onUserLogout( $user ) {
		$_SESSION['securepoll_voter'] = null;
		return true;
	}

	/**
	 * @param $updater DatabaseUpdater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( __DIR__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
				$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.sql" );
				$updater->modifyExtensionField( 'securepoll_votes', 'vote_ip',
					"$base/patches/patch-vote_ip-extend.sql", true );
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
		}
		return true;
	}

	/**
	 * @param $namespaces array
	 */
	public static function onCanonicalNamespaces( &$namespaces ) {
		global $wgSecurePollUseNamespace;
		if ( $wgSecurePollUseNamespace ) {
			$namespaces[NS_SECUREPOLL] = 'SecurePoll';
			$namespaces[NS_SECUREPOLL_TALK] = 'SecurePoll_talk';
		}
	}

	/**
	 * @param $title Title
	 * @param $user User
	 * @param $action string
	 * @param $errors array
	 * @param $doExpensiveQueries bool
	 * @param $short
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

	/**
	 * @param $title Title
	 * @param $model string
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {
		global $wgSecurePollUseNamespace;
		if( $wgSecurePollUseNamespace && $title->getNamespace() == NS_SECUREPOLL ) {
			$model = 'SecurePoll';
			return false;
		}
		return true;
	}

	public static function onRegistration() {
		define( 'NS_SECUREPOLL', 830 );
		define( 'NS_SECUREPOLL_TALK', 831 );
	}
}
