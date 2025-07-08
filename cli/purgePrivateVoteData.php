<?php

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Maintenance\Maintenance;

/**
 * @deprecated since 1.45. Use maintenance/PurgePrivateVoteData.php instead.
 */
class PurgePrivateVoteDataDeprecated extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Script to purge private data (IP, XFF, UA) from SecurePoll Votes' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'SecurePoll' );
	}

	/** @inheritDoc */
	public function execute() {
		$maintenanceScript = $this->createChild( PurgePrivateVoteData::class );
		$maintenanceScript->setBatchSize( $this->getBatchSize() );
		$maintenanceScript->execute();
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgePrivateVoteDataDeprecated::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
