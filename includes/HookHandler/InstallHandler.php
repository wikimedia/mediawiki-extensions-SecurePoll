<?php

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

		// 1.40
		$updater->dropExtensionIndex(
			'securepoll_msgs', 'spmsg_entity', "$base/sql/$type/patch-securepoll_msgs-unique-to-pk.sql"
		);
		$updater->dropExtensionIndex(
			'securepoll_properties', 'sppr_entity', "$base/sql/$type/patch-securepoll_properties-unique-to-pk.sql"
		);

		$updater->addPostDatabaseUpdateMaintenance( MigrateTallies::class );
	}
}
