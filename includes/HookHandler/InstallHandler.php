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
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.sql" );
				// 1.39
				$updater->modifyExtensionField(
					'securepoll_elections',
					'el_end_date',
					"$base/sql/$type/patch-securepoll_elections-timestamps.sql"
				);
				$updater->modifyExtensionField(
					'securepoll_votes',
					'vote_timestamp',
					"$base/sql/$type/patch-securepoll_votes-timestamp.sql"
				);
				$updater->modifyExtensionField(
					'securepoll_strike',
					'st_timestamp',
					"$base/sql/$type/patch-securepoll_strike-timestamp.sql"
				);
				$updater->modifyExtensionField(
					'securepoll_cookie_match',
					'cm_timestamp',
					"$base/sql/$type/patch-securepoll_cookie_match-timestamp.sql"
				);
				break;
			case 'sqlite':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.sql" );
				break;
			case 'postgres':
				$updater->addExtensionTable( 'securepoll_msgs', "$base/SecurePoll.pg.sql" );
				// 1.39
				$updater->addExtensionUpdate( [
					'changeField',
					'securepoll_votes', 'vote_timestamp', 'TIMESTAMPTZ', 'th_timestamp::timestamp with time zone'
				] );
				break;
		}

		$updater->addPostDatabaseUpdateMaintenance( 'UpdateNotBlockedKey' );
	}
}
