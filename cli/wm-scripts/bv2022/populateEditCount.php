<?php

/**
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 5 July 2022 across Wikimedia wikis;
 *   - and have made at least 20 edits between 5 January 2022 and 5 July 2022.
 */

use Flow\DbFactory;
use Flow\Model\UUID;
use MediaWiki\MediaWikiServices;

require __DIR__ . '/../../cli.inc';

$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
$lb = $lbFactory->getMainLB();
$dbr = $lb->getConnection( DB_REPLICA );
$dbw = $lb->getConnection( DB_PRIMARY );

$fname = 'populateEditCount';
$maxUser = (int)$dbr->newSelectQueryBuilder()
	->select( 'MAX(user_id)' )
	->from( 'user' )
	->caller( $fname )
	->fetchField();

$flowInstalled = ExtensionRegistry::getInstance()->isLoaded( 'Flow' );

// $beforeTime is exclusive, so specify the start of the day AFTER the last day
// of edits that should count toward the "edits before" eligibility requirement.
const BEFORE_TIME = '20220705000000';
$beforeTime = $dbr->addQuotes( BEFORE_TIME );
$beforeEditsToCount = 300;

// $betweenEndTime is exclusive, so specify the start of the day AFTER the last day
// of edits that should count toward the "edits between" eligibility requirement.
const BETWEEN_START = '20220105000000';
$betweenStartTime = $dbr->addQuotes( BETWEEN_START );
const BETWEEN_END = '20220706000000';
$betweenEndTime = $dbr->addQuotes( BETWEEN_END );
$betweenEditsToCount = 20;

$wikiId = WikiMap::getCurrentWikiId();

if ( $flowInstalled ) {
	global $wgFlowDefaultWikiDb, $wgFlowCluster;
	$flowDbr = ( new DbFactory( $wgFlowDefaultWikiDb, $wgFlowCluster ) )->getLB()->getConnection( DB_REPLICA );

	$flowBeforeTime = $dbr->addQuotes( UUID::getComparisonUUID( BEFORE_TIME )->getBinary() );

	$flowBetweenStartTime = $dbr->addQuotes( UUID::getComparisonUUID( BETWEEN_START )->getBinary() );
	$flowBetweenEndTime = $dbr->addQuotes( UUID::getComparisonUUID( BETWEEN_END )->getBinary() );
}

$numUsers = 0;

for ( $userId = 1; $userId <= $maxUser; $userId++ ) {
	// Find actor ID
	$actorId = (int)$dbr->newSelectQueryBuilder()
		->select( 'actor_id' )
		->from( 'actor' )
		->where( [ 'actor_user' => $userId ] )
		->caller( $fname )
		->fetchField();
	if ( !$actorId ) {
		continue;
	}

	$longEdits = (int)$dbr->newSelectQueryBuilder()
		->select( '*' )
		->from( 'revision' )
		->where( [
			'rev_actor' => $actorId,
			'rev_timestamp < ' . $beforeTime,
		] )
		->limit( $beforeEditsToCount )
		->caller( $fname )
		->fetchRowCount();

	$shortEdits = (int)$dbr->newSelectQueryBuilder()
		->select( '*' )
		->from( 'revision' )
		->where( [
			'rev_actor' => $actorId,
			'rev_timestamp >= ' . $betweenStartTime,
			'rev_timestamp < ' . $betweenEndTime,
		] )
		->limit( $betweenEditsToCount )
		->caller( $fname )
		->fetchRowCount();

	if ( $flowInstalled ) {
		// Only check for Flow edits if we've not counted enough timed edits already..

		if ( $longEdits < $beforeEditsToCount ) {
			// @phan-suppress-next-line PhanPossiblyUndeclaredGlobalVariable
			$longEdits += (int)$flowDbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'flow_revision' )
				->where( [
					'rev_user_id' => $userId,
					'rev_user_ip' => null,
					'rev_user_wiki' => $wikiId,
					// @phan-suppress-next-line PhanPossiblyUndeclaredGlobalVariable
					'rev_id < ' . $flowBeforeTime,
				] )
				->limit( $beforeEditsToCount )
				->caller( $fname )
				->fetchRowCount();
		}

		if ( $shortEdits < $betweenEditsToCount ) {
			// @phan-suppress-next-line PhanPossiblyUndeclaredGlobalVariable
			$shortEdits += (int)$flowDbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'flow_revision' )
				->where( [
					'rev_user_id' => $userId,
					'rev_user_ip' => null,
					'rev_user_wiki' => $wikiId,
					// @phan-suppress-next-line PhanPossiblyUndeclaredGlobalVariable
					'rev_id >= ' . $flowBetweenStartTime,
					// @phan-suppress-next-line PhanPossiblyUndeclaredGlobalVariable
					'rev_id < ' . $flowBetweenEndTime,
				] )
				->limit( $betweenEditsToCount )
				->caller( $fname )
				->fetchRowCount();
		}
	}

	if ( $longEdits != 0 || $shortEdits != 0 ) {
		$dbw->insert( 'bv2022_edits',
			[
				'bv_user' => $userId,
				'bv_long_edits' => $longEdits,
				'bv_short_edits' => $shortEdits
			],
			$fname
		);
		$numUsers++;
		if ( $numUsers % 500 === 0 ) {
			$lbFactory->waitForReplication();
		}
	}
}

echo WikiMap::getCurrentWikiId() . ": $numUsers users added\n";
