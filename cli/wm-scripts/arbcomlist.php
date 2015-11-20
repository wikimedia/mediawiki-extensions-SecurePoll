<?php

/**
 * Like makeSimpleList.php except with edits limited to the main namespace
 */

$optionsWithArgs = array( 'before', 'edits', 'start-from' );
require( dirname(__FILE__).'/../cli.inc' );

$dbr = wfGetDB( DB_SLAVE );
$dbw = wfGetDB( DB_MASTER );
$fname = 'arbcomlist.php';
$before = isset( $options['before'] ) ? $dbr->timestamp( strtotime( $options['before'] ) ) : false;
$minEdits = isset( $options['edits'] ) ? intval( $options['edits'] ) : false;

if ( !isset( $args[0] ) ) {
	echo <<<EOD
Usage: php arbcomlist.php [--ignore-existing|--replace] [--before=<date>]
                          [--edits=num] [--start-from=<user_id>] <name>
EOD;
	exit( 1 );
}
$listName = $args[0];
$startBatch = isset( $options['start-from'] ) ? $options['start-from'] : 0;
$batchSize = 100;
$insertOptions = array();

$listExists = $dbr->selectField( 'securepoll_lists', '1',
	array( 'li_name' => $listName ), $fname );
if ( $listExists ) {
	if ( isset( $options['replace'] ) ) {
		echo "Deleting existing list...\n";
		$dbw->delete( 'securepoll_lists', array( 'li_name' => $listName ), $fname );
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
		array( 'user_id > ' . $dbr->addQuotes( $startBatch ) ),
		$fname,
		array( 'LIMIT' => $batchSize ) );

	if ( !$res->numRows() ) {
		break;
	}

	$insertBatch = array();
	foreach ( $res as $row ) {
		$startBatch = $userId = $row->user_id;
		$insertRow = array( 'li_name' => $listName, 'li_member' => $userId );
		if ( $minEdits === false ) {
			$insertBatch[] = $insertRow;
			continue;
		}

		# Count edits
		$conds = array( 'rev_user' => $userId );
		if ( $before !== false ) {
			$conds[] = 'rev_timestamp < ' . $dbr->addQuotes( $before );
		}
		$conds['page_namespace'] = 0;

		$edits = $dbr->selectRowCount(
			array( 'revision', 'page' ),
			'1',
			$conds,
			$fname,
			array( 'LIMIT' => $minEdits ),
			array( 'page' => array( 'INNER JOIN', 'rev_page = page_id' ) )
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
