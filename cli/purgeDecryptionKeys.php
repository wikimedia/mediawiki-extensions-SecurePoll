<?php

/**
 * Purge GPG decryption keys from SecurePoll elections that have ended.
 *
 * Usage: php purgeDecryptionKeys.php
 *
 * This script is based on the purgePrivateVoteData.php script by Chris Steipp.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Sam Smith <samsmith@wikimedia.org>
 * @ingroup Maintenance
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Maintenance\Maintenance;

class PurgeDecryptionKeys extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge decryption keys from SecurePoll elections that have ended' );
		$this->setBatchSize( 200 );

		$this->addOption( 'dry-run', 'No decryption keys will be purged' );
		$this->addOption( 'before', 'Filter out elections that ended after this time, e.g. \'20210923000000\'' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );

		foreach ( [ 'securepoll_elections', 'securepoll_properties' ] as $table ) {
			if ( !$dbr->tableExists( $table, __METHOD__ ) ) {
				$this->output(
					"`{$table}` table does not exist. Nothing to do.\n"
				);

				return;
			}
		}

		$before = $this->getOption( 'before', 0 );
		$res = $dbr->newSelectQueryBuilder()
			->select( [
				'el_entity',
				'el_title',
				'el_end_date',
			] )
			->from( 'securepoll_elections' )
			->leftJoin( 'securepoll_properties', null, 'el_entity=pr_entity' )
			->where( [
				$dbr->expr( 'el_end_date', '<', $dbr->timestamp( $before ) ),
				'pr_key' => [ 'gpg-decrypt-key', 'openssl-decrypt-key' ],
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res->count() === 0 ) {
			$this->output( "No elections that have ended have decryption keys to purge. Nothing to do.\n" );

			return;
		}

		$allElectionIDs = [];

		foreach ( $res as $row ) {
			$allElectionIDs[] = $row->el_entity;

			$this->output( sprintf(
				"Election '%s' with end date '%s' will have its decryption key purged.\n",
				$row->el_title,
				$row->el_end_date
			) );
		}

		if ( $this->hasOption( 'dry-run' ) ) {
			$this->output(
				"Run this maintenance script again without the dry-run option to purge these decryption keys.\n"
			);

			return;
		}

		$electionIDChunks = array_chunk( $allElectionIDs, $this->getBatchSize() );
		$dbw = $this->getDB( DB_PRIMARY );

		foreach ( $electionIDChunks as $electionIDs ) {
			foreach ( $electionIDs as $electionID ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'securepoll_properties' )
					->where( [
						'pr_entity' => $electionID,
						'pr_key' => [ 'gpg-decrypt-key', 'openssl-decrypt-key' ],
					] )
					->caller( __METHOD__ )
					->execute();
			}
		}

		$this->output( "Done.\n" );
	}
}

$maintClass = PurgeDecryptionKeys::class;
require_once RUN_MAINTENANCE_IF_MAIN;
