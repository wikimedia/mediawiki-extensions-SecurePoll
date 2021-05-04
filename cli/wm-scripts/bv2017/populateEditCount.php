<?php

/**
 * have made at least 300 edits before 01 April 2017 across Wikimedia wikis
 * (edits on several wikis can be combined if your accounts are unified into a global account); and
 * have made at least 20 edits between 01 October 2016 and 01 April 2017.
 */

require __DIR__ . '/../../cli.inc';

$dbr = wfGetDB( DB_REPLICA );
$dbw = wfGetDB( DB_PRIMARY );

$maxUser = $dbr->selectField( 'user', 'MAX(user_id)', false );
$beforeTime = $dbr->addQuotes( '20170401000000' );
$betweenTime = [
	$dbr->addQuotes( '20161001000000' ),
	$dbr->addQuotes( '20170401000000' )
];
$fname = 'populateEditCount';

$numUsers = 0;

for ( $userId = 1; $userId <= $maxUser; $userId++ ) {
	$user = User::newFromId( $userId );
	if ( $user->isAnon() ) {
		continue;
	}

	$longEdits = 0;
	$shortEdits = 0;

	$revWhere = ActorMigration::newMigration()
		->getWhere( $dbr, 'rev_user', $user );

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
		$dbw->insert( 'bv2017_edits',
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

echo wfWikiID() . ": $numUsers users added\n";
