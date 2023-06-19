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

		$updater->addExtensionTable( 'securepoll_entity', "$base/sql/$type/tables-generated.sql" );

		switch ( $type ) {
			case 'mysql':
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
			case 'postgres':
				// 1.39
				$updater->addExtensionUpdate( [
					'changeField',
					'securepoll_votes', 'vote_timestamp', 'TIMESTAMPTZ', 'th_timestamp::timestamp with time zone'
				] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_msgs', 'msg_entity' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_elections', 'msg_entel_entityity' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_voters', 'voter_election' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_votes', 'vote_election' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_votes', 'vote_voter' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_strike', 'st_vote' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_cookie_match', 'cm_election' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_cookie_match', 'cm_voter_1' ] );
				$updater->addExtensionUpdate( [ 'dropFkey', 'securepoll_cookie_match', 'cm_voter_2' ] );
				$updater->dropExtensionIndex(
					'securepoll_msgs', 'securepoll_msgs_pkey', "$base/sql/$type/patch-securepoll_msgs-drop-pk.sql"
				);
				break;
		}

		// 1.40
		$updater->dropExtensionIndex(
			'securepoll_msgs', 'spmsg_entity', "$base/sql/$type/patch-securepoll_msgs-unique-to-pk.sql"
		);
		$updater->dropExtensionIndex(
			'securepoll_properties', 'sppr_entity', "$base/sql/$type/patch-securepoll_properties-unique-to-pk.sql"
		);

		$updater->addPostDatabaseUpdateMaintenance( \UpdateNotBlockedKey::class );
	}
}
