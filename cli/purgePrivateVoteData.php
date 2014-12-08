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
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PurgePrivateVoteData extends Maintenance {

	private $mPurgeDays = null;

	function __construct() {
		parent::__construct();
		$this->mDescription = 'Script purge private data (IP, XFF, UA) from SecurePoll Votes';
		$this->setBatchSize( 200 );
	}

	public function execute() {
		global $wgSecurePollKeepPrivateInfoDays;

		if ( $this->mPurgeDays === null ) {
			$this->mPurgeDays = $wgSecurePollKeepPrivateInfoDays;
		}

		$electionsToPurge = array();
		$deleteSets = array();
		$dbr = wfGetDB( DB_SLAVE );

		$elResult = $dbr->select( 'securepoll_elections',
			array( 'el_entity', 'el_title', 'el_end_date' ),
			"el_end_date < " . $dbr->addQuotes( $dbr->timestamp( time() - ( $this->mPurgeDays * 24 * 60 * 60 ) ) ),
			__METHOD__
		);

		foreach ( $elResult as $row  ) {
			$electionsToPurge[] = $row->el_entity;
			$this->output( "Election '{$row->el_title}' with end date '{$row->el_end_date}' will have data purged\n" );
		}

		if ( count( $electionsToPurge ) > 0 ) {

			$conds = array( 'vote_election' => $electionsToPurge );
			// In case a partial purge ran previously
			$conds[] = "( vote_ip != '' OR vote_xff != '' OR vote_ua != '' )";
			$minVoteId = 0;
			do {
				$vRes = $dbr->select( 'securepoll_votes',
					array( 'vote_id' ),
					array_merge( $conds, array( 'vote_id >= ' . $dbr->addQuotes( $minVoteId ) ) ),
					__METHOD__,
					array( 'ORDER BY' => 'vote_id ASC', 'LIMIT' => $this->mBatchSize )
				);

				if ( $vRes->numRows() === 0 ) {
					break;
				}

				$setMin = null;
				$setMax = null;
				foreach ( $vRes as $row  ) {
					if ( $setMin === null ) {
						$setMin = $row->vote_id;
					}
					$setMax = $row->vote_id + 1;
				}
				$deleteSets[] = array( $setMin, $setMax );
				$minVoteId = $setMax;
			} while( $vRes->numRows() == $this->mBatchSize );

			$dbw = wfGetDB( DB_MASTER );

			foreach ( $deleteSets as $deleteSet ) {
				list ( $minId, $maxId ) = $deleteSet;
				$dbw->update(
					'securepoll_votes',
					array( 'vote_ip' => '', 'vote_xff' => '', 'vote_ua' => '' ),
					array_merge( $conds,
						array( 'vote_id >= ' . $dbr->addQuotes( $minId ),
							'vote_id < ' . $dbr->addQuotes( $maxId ) )
					),
					__METHOD__,
					array( 'LIMIT' => $this->mBatchSize )
				);
				$this->output( "Purged data from " . $dbw->affectedRows() . " votes\n" );

				wfWaitForSlaves();
			}
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = "PurgePrivateVoteData";
require_once( RUN_MAINTENANCE_IF_MAIN );
