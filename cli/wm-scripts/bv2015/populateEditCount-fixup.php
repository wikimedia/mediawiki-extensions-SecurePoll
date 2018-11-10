<?php

/** Fix for populateEditCount.php which used a cutoff date of
 *  20150401500000 instead of 20150415000000 for bv_long_edits
 */
require __DIR__ . '/../../cli.inc';

$dbr = wfGetDB( DB_REPLICA );
$dbw = wfGetDB( DB_MASTER );

$maxUser = $dbr->selectField( 'user', 'MAX(user_id)', false );
$betweenTime = [
	$dbr->addQuotes( '20150401500000' ),
	$dbr->addQuotes( '20150415000000' )
];
$fname = 'populateEditCount';

$numUsers = 0;

for ( $userId = 1; $userId <= $maxUser; $userId++ ) {
	$user = User::newFromId( $userId );
	if ( $user->isAnon() ) {
		continue;
	}

	$adjust = 0;
	if ( class_exists( 'ActorMigration' ) ) {
		$revWhere = ActorMigration::newMigration()
			->getWhere( $dbr, 'rev_user', $user );
	} else {
		$revWhere = [
			'tables' => [],
			'orconds' => [ 'userid' => 'rev_user = ' . (int)$userId ],
			'joins' => [],
		];
	}
	foreach ( $revWhere['orconds'] as $key => $cond ) {
		$tsField = $key === 'actor' ? 'revactor_timestamp' : 'rev_timestamp';

		$adjust += (int)$dbr->selectField(
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

	if ( $adjust != 0 ) {
		out( "$userId\t$adjust\n" );
		$dbw->update( 'bv2015_edits',
			// SET
			[ 'bv_long_edits=bv_long_edits + ' . $dbr->addQuotes( $adjust ) ],
			// WHERE
			[ 'bv_user' => $userId ],
			$fname
		);
		if ( $dbw->affectedRows() < 1 ) {
			out( "ERROR: no bv2015_edits row for user $userId\n" );
		}

		$numUsers++;
	}
}

out( wfWikiID() . ": $numUsers users added\n" );

/**
 * @suppress SecurityCheck-XSS
 * @param string $val
 */
function out( $val ) {
	echo $val;
}
