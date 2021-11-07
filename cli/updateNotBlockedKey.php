<?php

/**
 * Update election property with new block keys from SecurePoll Elections
 * This is for the SecurePoll version: "3.0.0"
 *
 * Usage: php updateNotBlockedKey.php
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateNotBlockedKey extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Updates old elections to use voter eligibility ' .
			'properties introduced in T268800. Elections that previously disallowed ' .
			'blocked voters now disallow sitewide and partially blocked voters.'
		);
		$this->requireExtension( 'SecurePoll' );
	}

	protected function doDBUpdates() {
		$updatedRows = 0;
		$dbw = $this->getDB( DB_PRIMARY );
		$res = $dbw->select(
			'securepoll_properties',
			'pr_entity',
			[ 'pr_key' => 'not-blocked' ],
			__METHOD__
		);
		$rowCount = $res->numRows();
		$this->output( "$rowCount row(s) selected\n" );
		foreach ( $res as $row ) {
			// No need to split updates into batches since this table is very small
			$dbw->update(
				'securepoll_properties',
				[ 'pr_key' => 'not-partial-blocked' ],
				[ 'pr_key' => 'not-blocked', 'pr_entity' => $row->pr_entity ],
				  __METHOD__
			);
			$updatedRows++;
			$row = [
				'pr_entity' => $row->pr_entity,
				'pr_key' => 'not-sitewide-blocked',
				'pr_value' => 1,
			];
			$dbw->insert( 'securepoll_properties', $row, __METHOD__, [ 'IGNORE' ] );
		}
		$this->output( "$updatedRows row(s) updated\nDone\n" );
		return true;
	}

	protected function getUpdateKey() {
		return __CLASS__;
	}
}

$maintClass = UpdateNotBlockedKey::class;
require_once RUN_MAINTENANCE_IF_MAIN;
