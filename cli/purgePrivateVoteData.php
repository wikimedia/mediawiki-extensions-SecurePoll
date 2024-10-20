<?php
/**
 * Purge private data (IP, XFF, UA) from SecurePoll Votes
 *
 * Usage: php purgePrivateVoteData.php
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
 * @author Chris Steipp
 * @ingroup Maintenance
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Maintenance\Maintenance;

class PurgePrivateVoteData extends Maintenance {

	/** @var int|null */
	private $purgeDays = null;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Script purge private data (IP, XFF, UA) from SecurePoll Votes' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		if ( $this->purgeDays === null ) {
			$this->purgeDays = $this->getConfig()->get( 'SecurePollKeepPrivateInfoDays' );
		}

		$electionsToPurge = [];
		$deleteSets = [];
		$dbr = $this->getDB( DB_REPLICA );

		if ( !$dbr->tableExists( 'securepoll_elections', __METHOD__ ) ) {
			$this->output( "`securepoll_elections` table does not exist. Nothing to do.\n" );
			return;
		}

		$elResult = $dbr->newSelectQueryBuilder()
			->select( [ 'el_entity', 'el_title', 'el_end_date' ] )
			->from( 'securepoll_elections' )
			->where( $dbr->expr( 'el_end_date', '<',
				$dbr->timestamp( time() - ( $this->purgeDays * 24 * 60 * 60 ) )
			) )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $elResult as $row ) {
			$electionsToPurge[] = $row->el_entity;
			$this->output( "Election '{$row->el_title}' with end date '{$row->el_end_date}' " .
				"will have data purged\n" );
		}

		if ( count( $electionsToPurge ) > 0 ) {
			$conds = [ 'vote_election' => $electionsToPurge ];
			// In case a partial purge ran previously
			$conds[] = $dbr->expr( 'vote_ip', '!=', '' )
				->or( 'vote_xff', '!=', '' )
				->or( 'vote_ua', '!=', '' );
			$minVoteId = 0;
			do {
				$vRes = $dbr->newSelectQueryBuilder()
					->select( 'vote_id' )
					->from( 'securepoll_votes' )
					->where( $conds )
					->andWhere( $dbr->expr( 'vote_id', '>=', $minVoteId ) )
					->orderBy( 'vote_id' )
					->limit( $this->getBatchSize() )
					->caller( __METHOD__ )
					->fetchResultSet();

				if ( $vRes->numRows() === 0 ) {
					break;
				}

				$setMin = null;
				$setMax = null;
				foreach ( $vRes as $row ) {
					if ( $setMin === null ) {
						$setMin = $row->vote_id;
					}
					$setMax = $row->vote_id + 1;
				}
				$deleteSets[] = [ $setMin, $setMax ];
				$minVoteId = $setMax;
			} while ( $vRes->numRows() == $this->getBatchSize() );

			$dbw = $this->getDB( DB_PRIMARY );

			foreach ( $deleteSets as $deleteSet ) {
				[ $minId, $maxId ] = $deleteSet;
				$dbw->newUpdateQueryBuilder()
					->update( 'securepoll_votes' )
					->set( [ 'vote_ip' => '', 'vote_xff' => '', 'vote_ua' => '' ] )
					->where( $conds )
					->andWhere( [
						$dbw->expr( 'vote_id', '>=', $minId ),
						$dbw->expr( 'vote_id', '<', $maxId ),
					] )
					->caller( __METHOD__ )
					->execute();
				$this->output( "Purged data from " . $dbw->affectedRows() . " votes\n" );

				$this->waitForReplication();
			}
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = PurgePrivateVoteData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
