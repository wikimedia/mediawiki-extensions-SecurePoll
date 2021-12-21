<?php

/**
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - blocked in no more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 12 September 2021 across Wikimedia wikis;
 *   - and have made at least 20 edits between 12 March 2021 and 12 September 2021.
 */

use MediaWiki\MediaWikiServices;

require __DIR__ . '/../../cli.inc';

$dbr = wfGetDB( DB_REPLICA );
$dbw = wfGetDB( DB_PRIMARY );

$maxUser = $dbr->selectField( 'user', 'MAX(user_id)', false );
$beforeTime = $dbr->addQuotes( '20210912000000' );
$betweenTime = [
	$dbr->addQuotes( '20210312000000' ),
	$dbr->addQuotes( '20210912000000' )
];
$fname = 'populateEditCount';

$numUsers = 0;
$services = MediaWikiServices::getInstance();
$actorMigration = $services->getActorMigration();
$actorNormalization = $services->getActorNormalization();

for ( $userId = 1; $userId <= $maxUser; $userId++ ) {
	// Find actor ID
	$row = $dbr->newSelectQueryBuilder()
		->select( [ 'actor_id', 'actor_name', 'actor_user' ] )
		->from( 'actor' )
		->where( [ 'actor_user' => $userId ] )
		->caller( __METHOD__ )
		->fetchRow();
	if ( !$row ) {
		continue;
	}
	$user = $actorNormalization->newActorFromRow( $row );

	$longEdits = 0;
	$shortEdits = 0;

	$revWhere = $actorMigration->getWhere( $dbr, 'rev_user', $user );

	foreach ( $revWhere['orconds'] as $key => $cond ) {
		$tsField = $key === 'actor' ? 'revactor_timestamp' : 'rev_timestamp';

		$longEdits += $dbr->selectField(
			[ 'revision' ] + $revWhere['tables'],
			'COUNT(*)',
			[
				$cond,
				$tsField . ' < ' . $beforeTime
			],
			$fname,
			[],
			$revWhere['joins']
		);

		$shortEdits += $dbr->selectField(
			[ 'revision' ] + $revWhere['tables'],
			'COUNT(*)',
			[
				$cond,
				$tsField . ' BETWEEN ' . $betweenTime[0] . ' AND ' . $betweenTime[1]
			],
			$fname,
			[],
			$revWhere['joins']
		);
	}

	if ( $longEdits != 0 || $shortEdits != 0 ) {
		$dbw->insert( 'mcdc2021_edits',
			[
				'bv_user' => $userId,
				'bv_long_edits' => $longEdits,
				'bv_short_edits' => $shortEdits
			],
			$fname
		);
		$numUsers++;
	}
}

echo WikiMap::getCurrentWikiId() . ": $numUsers users added\n";
