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

use MediaWiki\MediaWikiServices;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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

		if ( !$dbr->tableExists( 'securepoll_elections' ) ) {
			$this->output( "`securepoll_elections` table does not exist. Nothing to do.\n" );
			return;
		}

		$elResult = $dbr->select( 'securepoll_elections',
			[ 'el_entity', 'el_title', 'el_end_date' ],
			"el_end_date < " . $dbr->addQuotes(
				$dbr->timestamp( time() - ( $this->purgeDays * 24 * 60 * 60 ) )
			),
			__METHOD__
		);

		foreach ( $elResult as $row ) {
			$electionsToPurge[] = $row->el_entity;
			$this->output( "Election '{$row->el_title}' with end date '{$row->el_end_date}' " .
				"will have data purged\n" );
		}

		if ( count( $electionsToPurge ) > 0 ) {
			$conds = [ 'vote_election' => $electionsToPurge ];
			// In case a partial purge ran previously
			$conds[] = 'vote_ip != ' . $dbr->addQuotes( '' ) .
				' OR vote_xff != ' . $dbr->addQuotes( '' ) .
				' OR vote_ua != ' . $dbr->addQuotes( '' );
			$minVoteId = 0;
			do {
				$vRes = $dbr->select( 'securepoll_votes',
					[ 'vote_id' ],
					array_merge( $conds, [ 'vote_id >= ' . $dbr->addQuotes( $minVoteId ) ] ),
					__METHOD__,
					[ 'ORDER BY' => 'vote_id ASC', 'LIMIT' => $this->getBatchSize() ]
				);

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
			$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

			foreach ( $deleteSets as $deleteSet ) {
				[ $minId, $maxId ] = $deleteSet;
				$dbw->update(
					'securepoll_votes',
					[ 'vote_ip' => '', 'vote_xff' => '', 'vote_ua' => '' ],
					array_merge( $conds,
						[ 'vote_id >= ' . $dbr->addQuotes( $minId ),
							'vote_id < ' . $dbr->addQuotes( $maxId ) ]
					),
					__METHOD__,
					[ 'LIMIT' => $this->getBatchSize() ]
				);
				$this->output( "Purged data from " . $dbw->affectedRows() . " votes\n" );

				$lbFactory->waitForReplication();
			}
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = PurgePrivateVoteData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
