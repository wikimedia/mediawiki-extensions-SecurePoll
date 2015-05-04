<?php

require( dirname( __FILE__ ) . '/../../cli.inc' );
$dbcr = CentralAuthUser::getCentralSlaveDB();
$dbcw = CentralAuthUser::getCentralDB();

$fname = 'voterList.php';
$listName = 'board-vote-2015a';

$dbcw->delete( 'securepoll_lists', array( 'li_name' => $listName ), $fname );

$totalUsers = $dbcr->selectField( 'globaluser', 'MAX(gu_id)', false, $fname );

$userName = '';
$numUsers = 0;
$numQualified = 0;
while ( true ) {
	$res = $dbcr->select( 'globaluser',
		array( 'gu_id', 'gu_name' ),
		array( 'gu_name > ' . $dbcr->addQuotes( $userName ) ),
		$fname,
		array( 'LIMIT' => 1000, 'ORDER BY' => 'gu_name' ) );
	if ( !$res->numRows() ) {
		break;
	}

	$users = array();
	foreach ( $res as $row ) {
		$users[$row->gu_id] = $row->gu_name;
		$userName = $row->gu_name;
		$numUsers++;
	}

	$qualifieds = spGetQualifiedUsers( $users );
	$insertBatch = array();
	foreach ( $qualifieds as $id => $name ) {
		$insertBatch[] = array(
			'li_name' => $listName,
			'li_member' => $id
		);
	}
	if ( $insertBatch ) {
		$dbcw->insert( 'securepoll_lists', $insertBatch, $fname );
		$numQualified += count( $insertBatch );
	}
	spReportProgress( $numUsers, $totalUsers );
}
echo wfWikiID() . " qualified \t$numQualified\n";

/**
 * @param $users array
 * @return array
 */
function spGetQualifiedUsers( $users ) {
	global $wgLocalDatabases;
	$dbcr = CentralAuthUser::getCentralSlaveDB();

	$res = $dbcr->select( 'localuser',
		array( 'lu_name', 'lu_wiki' ),
		array( 'lu_name' => $users ),
		__METHOD__ );

	$editCounts = array();
	$foreignUsers = array();
	foreach ( $res as $row ) {
		$foreignUsers[$row->lu_wiki][] = $row->lu_name;
		$editCounts[$row->lu_name] = array( 0, 0 );
	}

	foreach ( $foreignUsers as $wiki => $wikiUsers ) {
		if ( !in_array( $wiki, $wgLocalDatabases ) ) {
			continue;
		}
		$lb = wfGetLB( $wiki );
		$db = $lb->getConnection( DB_SLAVE, array(), $wiki );
		$foreignEditCounts = spGetEditCounts( $db, $wikiUsers );
		$lb->reuseConnection( $db );
		foreach ( $foreignEditCounts as $name => $count ) {
			$editCounts[$name][0] += $count[0];
			$editCounts[$name][1] += $count[1];
		}
	}

	$idsByUser = array_flip( $users );
	$qualifiedUsers = array();
	foreach ( $editCounts as $user => $count ) {
		if ( spIsQualified( $count[0], $count[1] ) ) {
			$id = $idsByUser[$user];
			$qualifiedUsers[$id] = $user;
		}
	}

	return $qualifiedUsers;
}

/**
 * @param $db DatabaseBase
 * @param $userNames
 * @return array
 */
function spGetEditCounts( $db, $userNames ) {
	$res = $db->select(
		array( 'user', 'bv2015_edits' ),
		array( 'user_name', 'bv_long_edits', 'bv_short_edits' ),
		array( 'bv_user=user_id', 'user_name' => $userNames ),
		__METHOD__
	);
	$editCounts = array();
	foreach ( $res as $row ) {
		$editCounts[$row->user_name] = array( $row->bv_short_edits, $row->bv_long_edits );
	}
	foreach ( $userNames as $user ) {
		if ( !isset( $editCounts[$user] ) ) {
			$editCounts[$user] = array( 0, 0 );
		}
	}
	return $editCounts;
}

/**
 * Returns whether a user "is qualified" to vote based on edit count
 *
 * @param $short
 * @param $long
 * @return bool
 */
function spIsQualified( $short, $long ) {
	return $short >= 20 && $long >= 300;
}

/**
 * Report progress
 */
function spReportProgress( $current, $total ) {
	static $lastReportTime, $startTime;

	$now = time();
	if ( !$startTime ) {
		$startTime = $now;
	}
	if ( $now - $lastReportTime < 10 ) {
		return;
	}
	$lastReportTime = $now;
	$lang = Language::factory( 'en' );
	$estTotalDuration = ( $now - $startTime ) * $total / $current;
	$estRemaining = $estTotalDuration - ( $now - $startTime );

	print $lang->commafy( $current ) . " of " .
		$lang->commafy( $total ) . " ; " .
		number_format( $current / $total * 100, 2 ) .  '% ; estimated time remaining: ' .
		$lang->formatDuration( $estRemaining ) .
		"\n";
}

