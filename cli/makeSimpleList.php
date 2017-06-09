<?php

/**
 * Generate a list of users with some number of edits before some date.
 *
 * Usage: php makeSimpleList.php [OPTIONS] LIST_NAME
 *   --replace          If list exists, delete it and recreate.
 *   --ignore-existing  Leave existing list items in place.
 *   --edits=COUNT      Edit count required for eligibility.
 *   --before=DATE      Consider edits made before DATE (strtotime format).
 *   --mainspace-only   Consider only NS_MAIN edits.
 *   --start-from=UID   Start from user ID UID. Allows crashed invocations
 *                      to be resumed.
 */

$optionsWithArgs = [ 'before', 'edits', 'start-from' ];
require __DIR__ . '/cli.inc';

$dbr = wfGetDB( DB_SLAVE );
$dbw = wfGetDB( DB_MASTER );
$fname = 'makeSimpleList.php';
$before = isset( $options['before'] ) ? $dbr->timestamp( strtotime( $options['before'] ) ) : false;
$minEdits = isset( $options['edits'] ) ? intval( $options['edits'] ) : false;

if ( !isset( $args[0] ) ) {
	echo <<<EOD
Generate a list of users with some number of edits before some date.

Usage: php makeSimpleList.php [OPTIONS] LIST_NAME
  --replace          If list exists, delete it and recreate.
  --ignore-existing  Leave existing list items in place.
  --edits=COUNT      Edit count required for eligibility.
  --before=DATE      Consider edits made before DATE (strtotime format).
  --mainspace-only   Consider only NS_MAIN edits.
  --start-from=UID   Start from user ID UID. Allows crashed invocations
                     to be resumed.

EOD;
	exit( 1 );
}
$listName = $args[0];
$startBatch = isset( $options['start-from'] ) ? $options['start-from'] : 0;
$batchSize = 100;
$insertOptions = [];

$listExists = $dbr->selectField( 'securepoll_lists', '1',
	[ 'li_name' => $listName ], $fname );
if ( $listExists ) {
	if ( isset( $options['replace'] ) ) {
		echo "Deleting existing list...\n";
		$dbw->delete( 'securepoll_lists', [ 'li_name' => $listName ], $fname );
	} elseif ( isset( $options['ignore-existing'] ) ) {
		$insertOptions[] = 'IGNORE';
	} else {
		echo "Error: list exists. Use --replace to replace it.\n";
		exit( 1 );
	}
}

while ( true ) {
	echo "user_id > $startBatch\n";
	$res = $dbr->select( 'user', 'user_id',
		[ 'user_id > ' . $dbr->addQuotes( $startBatch ) ],
		$fname,
		[ 'LIMIT' => $batchSize ] );

	if ( !$res->numRows() ) {
		break;
	}

	$insertBatch = [];
	foreach ( $res as $row ) {
		$startBatch = $userId = $row->user_id;
		$insertRow = [ 'li_name' => $listName, 'li_member' => $userId ];
		if ( $minEdits === false ) {
			$insertBatch[] = $insertRow;
			continue;
		}

		# Count edits
		$conds = [ 'rev_user' => $userId ];
		if ( $before !== false ) {
			$conds[] = 'rev_timestamp < ' . $dbr->addQuotes( $before );
		}
		if ( isset( $options['mainspace-only'] ) ) {
			$conds['page_namespace'] = 0;
		}

		$edits = $dbr->selectRowCount(
			[ 'revision', 'page' ],
			'1',
			$conds,
			$fname,
			[ 'LIMIT' => $minEdits ],
			[ 'page' => [ 'INNER JOIN', 'rev_page = page_id' ] ]
		);

		if ( $edits >= $minEdits ) {
			$insertBatch[] = $insertRow;
		}
	}
	if ( $insertBatch ) {
		$dbw->insert( 'securepoll_lists', $insertBatch, $fname, $insertOptions );
		wfWaitForSlaves();
	}
}
