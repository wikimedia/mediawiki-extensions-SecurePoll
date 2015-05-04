<?php

/** Fix for populateEditCount.php which used a cutoff date of
 *  20150401500000 instead of 20150415000000 for bv_long_edits
 */
require( dirname(__FILE__) . '/../../cli.inc' );

$dbr = wfGetDB( DB_SLAVE );
$dbw = wfGetDB( DB_MASTER );

$maxUser = $dbr->selectField( 'user', 'MAX(user_id)', false );
$betweenTime = array( '20150401500000', '20150415000000' );
$fname = 'populateEditCount';

$numUsers = 0;

for ( $userId = 1; $userId <= $maxUser; $userId++ ) {
	$exists = $dbr->selectField( 'user', '1', array( 'user_id' => $userId ) );
	if ( !$exists ) {
		continue;
	}
	$adjust = $dbr->selectField( 'revision', 'COUNT(*)',
		array(
			'rev_user' => $userId,
			'rev_timestamp BETWEEN ' . $dbr->addQuotes( $betweenTime[0] ) .
				' AND ' . $dbr->addQuotes( $betweenTime[1] )
		),
		$fname
	);

	if ( $adjust != 0 ) {
		echo "$userId\t$adjust\n";
		$dbw->update( 'bv2015_edits',
			// SET
			array( 'bv_long_edits=bv_long_edits + ' . $dbr->addQuotes( $adjust ) ),
			// WHERE
			array( 'bv_user' => $userId ),
			$fname
		);
		if ( $dbw->affectedRows() < 1 ) {
			echo "ERROR: no bv2015_edits row for user $userId\n";
		}

		$numUsers++;
	}
}

echo wfWikiID() . ": $numUsers users added\n";


