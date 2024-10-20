<?php

namespace MediaWiki\Extension\SecurePoll;

use Flow\Container;
use Flow\DbFactory;
use Flow\Model\UUID;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";

abstract class PopulateEditCount extends Maintenance {

	/**
	 * @var string Eligibility criteria date that $beforeEditsToCount edits need to be made before
	 */
	protected string $beforeTime;

	/**
	 * @var int Number of edits needed before $beforeTime
	 */
	protected int $beforeEditsToCount;

	/**
	 * @var string Eligibility criteria date that $betweenEditsToCount edits need to be made after,
	 *  but before $betweenEnd
	 */
	protected string $betweenStart;

	/**
	 * @var string Eligibility criteria date that $betweenEditsToCount edits need to be made before,
	 *  but after $betweenStart
	 */
	protected string $betweenEnd;

	/**
	 * @var int Number of edits needed between $betweenStart and $betweenEnd
	 */
	protected int $betweenEditsToCount;

	/**
	 * @var string Table to insert eligible voters into
	 */
	protected string $table;

	public function execute() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB();
		$dbr = $lb->getConnection( DB_REPLICA );
		$dbw = $lb->getConnection( DB_PRIMARY );

		$maxUser = (int)$dbr->newSelectQueryBuilder()
			->select( 'MAX(user_id)' )
			->from( 'user' )
			->caller( __METHOD__ )
			->fetchField();

		$flowInstalled = ExtensionRegistry::getInstance()->isLoaded( 'Flow' );

		// $beforeTime is exclusive, so specify the start of the day AFTER the last day
		// of edits that should count toward the "edits before" eligibility requirement.
		$beforeTime = $this->beforeTime;

		// $betweenEndTime is exclusive, so specify the start of the day AFTER the last day
		// of edits that should count toward the "edits between" eligibility requirement.
		$betweenStartTime = $this->betweenStart;
		$betweenEndTime = $this->betweenEnd;

		if ( $flowInstalled ) {
			/** @var DbFactory $dbFactory */
			$dbFactory = Container::get( 'db.factory' );
			$flowDbr = $dbFactory->getDB( DB_REPLICA );

			$wikiId = WikiMap::getCurrentWikiId();

			$flowBeforeTime = UUID::getComparisonUUID( $this->beforeTime )->getBinary();

			$flowBetweenStartTime = UUID::getComparisonUUID( $this->betweenStart )->getBinary();
			$flowBetweenEndTime = UUID::getComparisonUUID( $this->betweenEnd )->getBinary();
		}

		$maxEditCountUserId = (int)$dbr->newSelectQueryBuilder()
			->select( 'MAX(bv_user)' )
			->from( $this->table )
			->caller( __METHOD__ )
			->fetchField();

		if ( $maxEditCountUserId === $maxUser ) {
			$this->output(
				WikiMap::getCurrentWikiId() .
				": 0 users added; edit count already calculated for all users\n"
			);
			return;
		}

		$numUsers = 0;

		// $maxEditCountUserId will be 0 if no rows have already been inserted.
		// We want to start at at least 1, or the user id after the last row inserted.
		for ( $userId = $maxEditCountUserId + 1; $userId <= $maxUser; $userId++ ) {
			// Find actor ID
			$actorId = (int)$dbr->newSelectQueryBuilder()
				->select( 'actor_id' )
				->from( 'actor' )
				->where( [ 'actor_user' => $userId ] )
				->caller( __METHOD__ )
				->fetchField();
			if ( !$actorId ) {
				continue;
			}

			$longEdits = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'revision' )
				->where( [
					'rev_actor' => $actorId,
					$dbr->expr( 'rev_timestamp', '<', $beforeTime ),
				] )
				->limit( $this->beforeEditsToCount )
				->caller( __METHOD__ )
				->fetchRowCount();

			$shortEdits = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'revision' )
				->where( [
					'rev_actor' => $actorId,
					$dbr->expr( 'rev_timestamp', '>=', $betweenStartTime ),
					$dbr->expr( 'rev_timestamp', '<', $betweenEndTime ),
				] )
				->limit( $this->betweenEditsToCount )
				->caller( __METHOD__ )
				->fetchRowCount();

			if ( $flowInstalled ) {
				// Only check for Flow edits if we've not counted enough timed edits already..
				if ( $longEdits < $this->beforeEditsToCount ) {
					// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
					$longEdits += $flowDbr->newSelectQueryBuilder()
						->select( '*' )
						->from( 'flow_revision' )
						->where( [
							'rev_user_id' => $userId,
							'rev_user_ip' => null,
							'rev_user_wiki' => $wikiId,
							// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
							$flowDbr->expr( 'rev_id', '<', $flowBeforeTime ),
						] )
						->limit( $this->beforeEditsToCount )
						->caller( __METHOD__ )
						->fetchRowCount();
				}

				if ( $shortEdits < $this->betweenEditsToCount ) {
					// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
					$shortEdits += $flowDbr->newSelectQueryBuilder()
						->select( '*' )
						->from( 'flow_revision' )
						->where( [
							'rev_user_id' => $userId,
							'rev_user_ip' => null,
							'rev_user_wiki' => $wikiId,
							// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
							$flowDbr->expr( 'rev_id', '>=', $flowBetweenStartTime ),
							// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
							$flowDbr->expr( 'rev_id', '<', $flowBetweenEndTime ),
						] )
						->limit( $this->betweenEditsToCount )
						->caller( __METHOD__ )
						->fetchRowCount();
				}
			}

			if ( $longEdits !== 0 || $shortEdits !== 0 ) {
				$dbw->newInsertQueryBuilder()
					->insertInto( $this->table )
					->row( [
						'bv_user' => $userId,
						'bv_long_edits' => $longEdits,
						'bv_short_edits' => $shortEdits
					] )
					->caller( __METHOD__ )
					->execute();
				$numUsers++;
				if ( $numUsers % 500 === 0 ) {
					$this->waitForReplication();
				}
			}
		}

		$this->output( WikiMap::getCurrentWikiId() . ": $numUsers users added\n" );
	}
}
