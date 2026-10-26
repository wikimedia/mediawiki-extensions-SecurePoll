<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\SecurePoll\HookHandler;

use MediaWiki\Extension\SecurePoll\Maintenance\MigrateTallies;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class InstallHandler implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( dirname( __DIR__ ) );
		$type = $updater->getDB()->getType();

		$updater->addExtensionTable( 'securepoll_elections', "$base/sql/$type/tables-generated.sql" );

		// 1.45
		$updater->addPostDatabaseUpdateMaintenance( MigrateTallies::class );
	}
}
