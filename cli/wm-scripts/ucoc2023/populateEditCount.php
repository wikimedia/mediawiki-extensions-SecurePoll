<?php

/**
 * Rules from https://meta.wikimedia.org/wiki/Universal_Code_of_Conduct/Revised_enforcement_guidelines/Voter_information#General_rule
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 3 January 2023 across Wikimedia wikis;
 *   - and have made at least 20 edits between 3 July 2022 and 3 January 2023.
 */

use Flow\Container;
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
const BEFORE_TIME = '20230104000000';
$beforeTime = $dbr->addQuotes( BEFORE_TIME );
const BEFORE_EDITS_TO_COUNT = 300;

// $betweenEndTime is exclusive, so specify the start of the day AFTER the last day
// of edits that should count toward the "edits between" eligibility requirement.
const BETWEEN_START = '20220703000000';
$betweenStartTime = $dbr->addQuotes( BETWEEN_START );
const BETWEEN_END = '20230104000000';
$betweenEndTime = $dbr->addQuotes( BETWEEN_END );
const BETWEEN_EDITS_TO_COUNT = 20;

if ( $flowInstalled ) {
	/** @var DbFactory $dbFactory */
	$dbFactory = Container::get( 'db.factory' );
	$flowDbr = $dbFactory->getDB( DB_REPLICA );

	$wikiId = WikiMap::getCurrentWikiId();

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
		->limit( BEFORE_EDITS_TO_COUNT )
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
		->limit( BETWEEN_EDITS_TO_COUNT )
		->caller( $fname )
		->fetchRowCount();

	if ( $flowInstalled ) {
		// Only check for Flow edits if we've not counted enough timed edits already..
		if ( $longEdits < BEFORE_EDITS_TO_COUNT ) {
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
				->limit( BEFORE_EDITS_TO_COUNT )
				->caller( $fname )
				->fetchRowCount();
		}

		if ( $shortEdits < BETWEEN_EDITS_TO_COUNT ) {
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
				->limit( BETWEEN_EDITS_TO_COUNT )
				->caller( $fname )
				->fetchRowCount();
		}
	}

	if ( $longEdits !== 0 || $shortEdits !== 0 ) {
		$dbw->insert( 'ucoc2023_edits',
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
