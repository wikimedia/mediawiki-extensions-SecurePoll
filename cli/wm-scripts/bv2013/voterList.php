<?php

require __DIR__ . '/../../cli.inc';
$dbr = wfGetDB( DB_REPLICA );
$dbw = wfGetDB( DB_PRIMARY );
$fname = 'voterList.php';
$listName = 'board-vote-2013';

if ( !$wgCentralAuthDatabase ) {
	echo WikiMap::getCurrentWikiId() . ": CentralAuth not active, skipping\n";
	exit( 0 );
}

$dbw->delete( 'securepoll_lists', [ 'li_name' => $listName ], $fname );

$userId = 0;
$numQualified = 0;
while ( true ) {
	$res = $dbr->select( 'user', [ 'user_id', 'user_name' ],
		[ 'user_id > ' . $dbr->addQuotes( $userId ) ],
		__METHOD__,
		[ 'LIMIT' => 1000, 'ORDER BY' => 'user_id' ] );
	if ( !$res->numRows() ) {
		break;
	}

	$users = [];
	foreach ( $res as $row ) {
		$users[$row->user_id] = $row->user_name;
		$userId = (int)$row->user_id;
	}
	$qualifieds = spGetQualifiedUsers( $users );
	$insertBatch = [];
	foreach ( $qualifieds as $id => $name ) {
		$insertBatch[] = [
			'li_name' => $listName,
			'li_member' => $id
		];
	}
	if ( $insertBatch ) {
		doInsert( $dbw, $insertBatch, $fname );
		$numQualified += count( $insertBatch );
	}
}
echo WikiMap::getCurrentWikiId() . " qualified \t$numQualified\n";

/**
 * @param array $users
 * @return array
 */
function spGetQualifiedUsers( $users ) {
	global $wgCentralAuthDatabase, $wgLocalDatabases;
	$dbc = wfGetDB( DB_REPLICA, [], $wgCentralAuthDatabase );
	$editCounts = [];

	# Check local attachment
	$res = $dbc->select( 'localuser', [ 'lu_name' ],
		[
			'lu_wiki' => WikiMap::getCurrentWikiId(),
			'lu_name' => array_values( $users )
		], __METHOD__ );

	$attached = [];
	foreach ( $res as $row ) {
		$attached[] = $row->lu_name;
		$editCounts[$row->lu_name] = [ 0, 0 ];
	}
	$nonLocalUsers = [];

	$localEditCounts = spGetEditCounts( wfGetDB( DB_REPLICA ), $users );
	foreach ( $localEditCounts as $user => $counts ) {
		if ( $counts[0] == 0 ) {
			// No recent local edits, remove from consideration
			// This is just for efficiency, the user can vote somewhere else
			$nonLocalUsers[] = $user;
		}
		$editCounts[$user] = $counts;
	}
	$attached = array_diff( $attached, $nonLocalUsers );

	# Check all global accounts
	$localWiki = WikiMap::getCurrentWikiId();
	if ( $attached ) {
		$res = $dbc->select( 'localuser',
			[ 'lu_name', 'lu_wiki' ],
			[ 'lu_name' => $attached ],
			__METHOD__ );
		$foreignUsers = [];
		foreach ( $res as $row ) {
			if ( $row->lu_wiki != $localWiki ) {
				$foreignUsers[$row->lu_wiki][] = $row->lu_name;
			}
		}

		$lbFactory = MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		foreach ( $foreignUsers as $wiki => $wikiUsers ) {
			if ( !in_array( $wiki, $wgLocalDatabases ) ) {
				continue;
			}
			$lb = $lbFactory->getMainLB( $wiki );
			$db = $lb->getConnection( DB_REPLICA, [], $wiki );
			$foreignEditCounts = spGetEditCounts( $db, $wikiUsers );
			$lb->reuseConnection( $db );
			foreach ( $foreignEditCounts as $name => $count ) {
				$editCounts[$name][0] += $count[0];
				$editCounts[$name][1] += $count[1];
			}
		}
	}

	$idsByUser = array_flip( $users );
	$qualifiedUsers = [];
	foreach ( $editCounts as $user => $count ) {
		if ( spIsQualified( $count[0], $count[1] ) ) {
			$id = $idsByUser[$user];
			$qualifiedUsers[$id] = $user;
		}
	}

	return $qualifiedUsers;
}

/**
 * @param \Wikimedia\Rdbms\IDatabase $db
 * @param string[] $userNames
 * @return array
 */
function spGetEditCounts( $db, $userNames ) {
	$res = $db->select(
		[ 'user', 'bv2013_edits' ],
		[ 'user_name', 'bv_long_edits', 'bv_short_edits' ],
		[ 'bv_user=user_id', 'user_name' => $userNames ],
		__METHOD__
	);
	$editCounts = [];
	foreach ( $res as $row ) {
		$editCounts[$row->user_name] = [ $row->bv_short_edits, $row->bv_long_edits ];
	}
	foreach ( $userNames as $user ) {
		if ( !isset( $editCounts[$user] ) ) {
			$editCounts[$user] = [ 0, 0 ];
		}
	}
	return $editCounts;
}

/**
 * Returns whether a user "is qualified" to vote based on edit count
 * Short is 20 edits in a period between 15 December 2012 and 30 April 2013
 * Long is 300 edits before 15 April 2013
 *
 * @param int $short
 * @param int $long
 * @return bool
 */
function spIsQualified( $short, $long ) {
	return $short >= 20 && $long >= 300;
}

/**
 * phan-taint-check gets confused with the other cli scripts due to globals
 *
 * @suppress SecurityCheck-SQLInjection
 * @param IDatabase $dbw
 * @param array $insertBatch
 * @param string $fname
 */
function doInsert( $dbw, $insertBatch, $fname ) {
	$dbw->insert( 'securepoll_lists', $insertBatch, $fname );
}
