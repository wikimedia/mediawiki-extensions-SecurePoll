<?php

use MediaWiki\User\ActorMigration;
use MediaWiki\User\User;

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

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MakeSimpleList extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generate a list of users with some number of edits before some date' );

		$this->addArg( 'listname', 'Name of the list' );
		$this->addOption( 'replace', 'If list exists, delete it and recreate' );
		$this->addOption( 'ignore-existing', 'Leave existing list items in place' );
		$this->addOption( 'edits', 'Edit count required for eligibility', false, true );
		$this->addOption( 'before', 'Consider edits made before DATE (strtotime format)', false, true );
		$this->addOption( 'mainspace-only', 'Consider only NS_MAIN edits' );
		$this->addOption(
			'start-from',
			'Start from user ID UID. Allows crashed invocations to be resumed.',
			false,
			true
		);

		$this->setBatchSize( 100 );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );
		$dbw = $this->getDB( DB_PRIMARY );
		$before = $this->hasOption( 'before' )
			? $dbr->timestamp( strtotime( $this->getOption( 'before' ) ) ) : false;
		$minEdits = $this->getOption( 'edits', false );

		$listName = $this->getArg( 0 );
		$startBatch = $this->getOption( 'start-from', 0 );
		$insertOptions = [];

		$listExists = $dbr->selectField(
			'securepoll_lists',
			'1',
			[ 'li_name' => $listName ],
			__METHOD__
		);
		if ( $listExists ) {
			if ( $this->hasOption( 'replace' ) ) {
				$this->output( "Deleting existing list...\n" );
				$dbw->delete( 'securepoll_lists', [ 'li_name' => $listName ], __METHOD__ );
			} elseif ( $this->hasOption( 'ignore-existing' ) ) {
				$insertOptions[] = 'IGNORE';
			} else {
				$this->fatalError( "Error: list exists. Use --replace to replace it.\n" );
			}
		}

		$userQuery = User::getQueryInfo();
		$userQuery['fields'] = [ 'user_id' ];

		$beforeQ = $before !== false ? $dbr->addQuotes( $before ) : false;

		$lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();

		while ( true ) {
			echo "user_id > $startBatch\n";
			$res = $dbr->select(
				$userQuery['tables'],
				$userQuery['fields'],
				[ 'user_id > ' . $dbr->addQuotes( $startBatch ) ],
				__METHOD__,
				[ 'LIMIT' => $this->getBatchSize() ],
				$userQuery['joins']
			);

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
				$edits = 0;
				$revWhere = ActorMigration::newMigration()
					->getWhere( $dbr, 'rev_user', User::newFromRow( $row ) );

				foreach ( $revWhere['orconds'] as $key => $cond ) {
					$conds = [ $cond ];
					if ( $before !== false ) {
						$conds[] = ( $key === 'actor' ? 'revactor_timestamp' : 'rev_timestamp' )
							. ' < ' . $beforeQ;
					}
					if ( $this->hasOption( 'mainspace-only' ) ) {
						$conds['page_namespace'] = 0;
					}

					$edits = $dbr->selectRowCount(
						[ 'revision', 'page' ] + $revWhere['tables'],
						'1',
						$conds,
						__METHOD__,
						[ 'LIMIT' => $minEdits ],
						$revWhere['joins'] + [ 'page' => [ 'INNER JOIN', 'rev_page = page_id' ] ]
					);
				}

				if ( $edits >= $minEdits ) {
					$insertBatch[] = $insertRow;
				}
			}
			if ( $insertBatch ) {
				$dbw->insert( 'securepoll_lists', $insertBatch, __METHOD__, $insertOptions );
				$lbFactory->waitForReplication();
			}
		}
	}

}

$maintClass = MakeSimpleList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
