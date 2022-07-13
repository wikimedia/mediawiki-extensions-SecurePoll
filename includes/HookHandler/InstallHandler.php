<?php

namespace MediaWiki\Extension\SecurePoll\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class InstallHandler implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( dirname( __DIR__ ) );
		$type = $updater->getDB()->getType();

		$updater->addExtensionTable( 'securepoll_entity', "$base/sql/$type/tables.sql" );

		switch ( $type ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.sql" );
				break;
			case 'postgres':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.pg.sql" );
				break;
		}

		$updater->addPostDatabaseUpdateMaintenance( \UpdateNotBlockedKey::class );
	}
}
