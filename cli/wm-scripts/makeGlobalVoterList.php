<?php

namespace MediaWiki\Extension\SecurePoll;

use Maintenance;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDatabase;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MakeGlobalVoterList extends Maintenance {
	/** @var int */
	private $shortMinEdits;
	/** @var int */
	private $longMinEdits;
	/** @var string */
	private $editCountTable;
	/** @var int|null */
	private $lastReportTime;
	/** @var int|null */
	private $startTime;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'edit-count-table',
			'The table name which stores short and long edit counts',
			true, true );
		$this->addOption( 'list-name',
			'The securepoll list name to create (will be deleted first if exists)',
			true, true );
		$this->addOption( 'short-min-edits',
			'The minimum number of edits a user must have in the shorter time period ' .
			'to be counted as qualified',
			true, true );
		$this->addOption( 'long-min-edits',
			'The minimum number of edits a user must have in the longer time period ' .
			'to be counted as qualified',
			true, true );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$caDbManager = CentralAuthServices::getDatabaseManager();
		$dbcr = $caDbManager->getCentralReplicaDB();
		$dbcw = $caDbManager->getCentralPrimaryDB();

		$listName = $this->getOption( 'list-name' );
		$this->shortMinEdits = $this->getOption( 'short-min-edits' );
		$this->longMinEdits = $this->getOption( 'long-min-edits' );
		$this->editCountTable = $this->getOption( 'edit-count-table' );

		$dbcw->delete(
			'securepoll_lists',
			[ 'li_name' => $listName ],
			__METHOD__ );

		$totalUsers = $dbcr->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();

		$userName = '';
		$numUsers = 0;
		$numQualified = 0;
		while ( true ) {
			$res = $dbcr->newSelectQueryBuilder()
				->select( [ 'gu_id', 'gu_name' ] )
				->from( 'globaluser' )
				->where( 'gu_name > ' . $dbcr->addQuotes( $userName ) )
				->limit( $this->getBatchSize() )
				->orderBy( 'gu_name' )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( !$res->numRows() ) {
				break;
			}

			$users = [];
			foreach ( $res as $row ) {
				$users[$row->gu_id] = $row->gu_name;
				$userName = $row->gu_name;
				$numUsers++;
			}

			$qualifieds = $this->getQualifiedUsers( $users );
			$insertBatch = [];
			foreach ( $qualifieds as $id => $name ) {
				$insertBatch[] = [
					'li_name' => $listName,
					'li_member' => $id
				];
			}
			if ( $insertBatch ) {
				$dbcw->insert( 'securepoll_lists', $insertBatch, __METHOD__ );
				$numQualified += count( $insertBatch );
			}
			$this->reportProgress( $numUsers, $totalUsers );
		}
		echo WikiMap::getCurrentWikiId() . " qualified \t$numQualified\n";
	}

	/**
	 * @param array $users
	 * @return array
	 */
	private function getQualifiedUsers( $users ) {
		global $wgLocalDatabases;
		$caDbManager = CentralAuthServices::getDatabaseManager();
		$dbcr = $caDbManager->getCentralReplicaDB();

		$res = $dbcr->newSelectQueryBuilder()
			->select( [ 'lu_name', 'lu_wiki' ] )
			->from( 'localuser' )
			->where( [ 'lu_name' => $users ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$editCounts = [];
		$foreignUsers = [];
		foreach ( $res as $row ) {
			$foreignUsers[$row->lu_wiki][] = $row->lu_name;
			$editCounts[$row->lu_name] = [ 0, 0 ];
		}

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		foreach ( $foreignUsers as $wiki => $wikiUsers ) {
			if ( !in_array( $wiki, $wgLocalDatabases ) ) {
				continue;
			}
			$lb = $lbFactory->getMainLB( $wiki );
			$db = $lb->getMaintenanceConnectionRef( DB_REPLICA, [], $wiki );
			if ( !$db->tableExists( $this->editCountTable ) ) {
				print "WARNING: Skipping wiki $wiki: table {$this->editCountTable} does not exist\n";
				continue;
			}
			$foreignEditCounts = $this->getEditCounts( $db, $wikiUsers );
			foreach ( $foreignEditCounts as $name => $count ) {
				$editCounts[$name][0] += $count[0];
				$editCounts[$name][1] += $count[1];
			}
		}

		$idsByUser = array_flip( $users );
		$qualifiedUsers = [];
		foreach ( $editCounts as $user => $count ) {
			if ( $this->isQualified( $count[0], $count[1] ) ) {
				$id = $idsByUser[$user];
				$qualifiedUsers[$id] = $user;
			}
		}

		return $qualifiedUsers;
	}

	/**
	 * @param IDatabase $db
	 * @param string[] $userNames
	 * @return array
	 */
	private function getEditCounts( $db, $userNames ) {
		$res = $db->newSelectQueryBuilder()
			->select( [ 'user_name', 'bv_long_edits', 'bv_short_edits' ] )
			->from( 'user' )
			->join( $this->editCountTable, null, 'bv_user=user_id' )
			->where( [ 'user_name' => $userNames ] )
			->caller( __METHOD__ )
			->fetchResultSet();
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
	 *
	 * @param int $short
	 * @param int $long
	 * @return bool
	 */
	private function isQualified( $short, $long ) {
		return $short >= $this->shortMinEdits && $long >= $this->longMinEdits;
	}

	/**
	 * Report progress
	 * @param int $current
	 * @param int $total
	 */
	private function reportProgress( $current, $total ) {
		$now = time();
		if ( !$this->startTime ) {
			$this->startTime = $now;
		}
		if ( $now - $this->lastReportTime < 10 ) {
			return;
		}
		$this->lastReportTime = $now;
		$lang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		$estTotalDuration = ( $now - $this->startTime ) * $total / $current;
		$estRemaining = $estTotalDuration - ( $now - $this->startTime );

		print $lang->formatNum( $current ) . " of " . $lang->formatNum( $total ) . " ; " .
			number_format( $current / $total * 100, 2 ) . '% ; estimated time remaining: ' .
			$lang->formatDuration( $estRemaining ) . "\n";
	}

}

$maintClass = MakeGlobalVoterList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
