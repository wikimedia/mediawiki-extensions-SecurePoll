<?php

namespace MediaWiki\Extension\SecurePoll\Maintenance;

use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd

/**
 * Drops unused SecurePoll tables.
 * @since 1.45
 */
class DropUnusedTables extends Maintenance {

	public const TABLES_TO_DROP = [
		'securepoll_cookie_match',
		'securepoll_entity',
		'securepoll_log',
		'securepoll_options',
		'securepoll_questions',
		'securepoll_strike',
		'securepoll_voters',
		'securepoll_votes',
	];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Drop unused tables' );
		$this->addOption( 'force', 'Drop tables even if they are non-empty' );
		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$groupPermissionsLookup = $this->getServiceContainer()->getGroupPermissionsLookup();
		$groups = $groupPermissionsLookup->getGroupsWithPermission( 'securepoll-create-poll' );

		if ( $groups ) {
			$this->output( "Found user groups having permission to create polls, nothing to do.\n" );
			return;
		}

		$dbw = $this->getPrimaryDB();
		'@phan-var \Wikimedia\Rdbms\IMaintainableDatabase $dbw';

		foreach ( self::TABLES_TO_DROP as $table ) {
			if ( $dbw->tableExists( $table, __METHOD__ ) ) {
				$value = $dbw->newSelectQueryBuilder()
					->fields( '1' )
					->from( $table )
					->limit( 1 )
					->caller( __METHOD__ )
					->fetchField();

				if ( !$value || $this->hasOption( 'force' ) ) {
					$this->output( "Dropping table '$table'\n" );
					$dbw->dropTable( $table, __METHOD__ );
				} else {
					$this->output( "Table '$table' is not empty, skipping.\n" );
				}
			}
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = DropUnusedTables::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
