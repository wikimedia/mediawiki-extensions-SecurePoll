<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ImportGlobalVoterList extends Maintenance {
	/** @var IReadableDatabase */
	private $dbcr;
	/** @var IDatabase */
	private $dbcw;
	/** @var string */
	private $listName;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Add usernames from a flat file to a global voter list' );
		$this->addOption( 'list-name',
			'The securepoll list name',
			true, true );
		$this->addOption( 'delete', 'Delete the list if it exists' );
		$this->addArg( 'input-file', 'The file with one username per line' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$caDbManager = CentralAuthServices::getDatabaseManager();
		$this->dbcr = $caDbManager->getCentralReplicaDB();
		$this->dbcw = $caDbManager->getCentralPrimaryDB();

		$this->listName = $this->getOption( 'list-name' );
		if ( $this->hasOption( 'delete' ) ) {
			print "Removing any existing members of list \"{$this->listName}\"\n";
			$this->dbcw->newDeleteQueryBuilder()
				->deleteFrom( 'securepoll_lists' )
				->where( [ 'li_name' => $this->listName ] )
				->caller( __METHOD__ )
				->execute();
		}

		$batch = [];
		foreach ( $this->getUsersToAdd() as $userName ) {
			$batch[] = $userName;
			if ( count( $batch ) >= $this->getBatchSize() ) {
				$this->processBatch( $batch );
				$batch = [];
			}
		}
		$this->processBatch( $batch );
	}

	private function getUsersToAdd(): \Generator {
		$fileName = $this->getArg( 0 );
		$file = fopen( $fileName, 'r' );
		if ( !$file ) {
			$this->fatalError( "Unable to open file $fileName" );
		}
		for ( $lineNum = 1; true; $lineNum++ ) {
			$line = fgets( $file );
			if ( $line === false ) {
				break;
			}
			if ( $line === "\n" ) {
				continue;
			}
			$userName = str_replace( '_', ' ', trim( $line ) );
			yield trim( $userName );
		}
	}

	private function processBatch( array $batch ) {
		if ( !$batch ) {
			return;
		}

		// Get IDs of users that aren't already in the list
		$res = $this->dbcr->newSelectQueryBuilder()
			->select( [ 'gu_name', 'gu_id' ] )
			->from( 'globaluser' )
			->leftJoin( 'securepoll_lists', null, [
				'li_member=gu_id',
				'li_name' => $this->listName
			] )
			->where( [
				'gu_name' => $batch,
				'li_member' => null
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$insertBatch = [];
		foreach ( $res as $row ) {
			print "Adding {$row->gu_name}\n";
			$insertBatch[] = [
				'li_name' => $this->listName,
				'li_member' => $row->gu_id
			];
		}

		if ( !$insertBatch ) {
			return;
		}

		$this->dbcw->newInsertQueryBuilder()
			->insertInto( 'securepoll_lists' )
			->rows( $insertBatch )
			->caller( __METHOD__ )
			->execute();
	}
}

// @codeCoverageIgnoreStart
$maintClass = ImportGlobalVoterList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
