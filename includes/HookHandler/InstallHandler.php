<?php

namespace MediaWiki\Extensions\SecurePoll\HookHandler;

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
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.sql" );
				$updater->modifyExtensionField(
					'securepoll_votes',
					'vote_ip',
					"$base/patches/patch-vote_ip-extend.sql"
				);
				$updater->addExtensionIndex(
					'securepoll_options',
					'spop_election',
					"$base/patches/patch-op_election-index.sql"
				);
				$updater->addExtensionField(
					'securepoll_elections',
					'el_owner',
					"$base/patches/patch-el_owner.sql"
				);
				break;
			case 'postgres':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.pg.sql" );
				break;
			case 'sqlite':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.sql" );
				break;
		}
	}
}
