<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class DumpGlobalVoterList extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Dumps the Global Voter list';
	}

	public function execute() {
		global $wgLocalDatabases;

		$voters = [];
		$batchSize = 1000;
		$wikis = $wgLocalDatabases;
		$lbFactory = MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		foreach ( $wikis as $wikiId ) {
			$lb = $lbFactory->getMainLB( $wikiId );
			$db = $lb->getConnection( DB_REPLICA, [], $wikiId );

			if ( !$db->tableExists( 'securepoll_lists' ) ) {
				$lb->reuseConnection( $db );
				continue;
			}

			$wikiName = WikiMap::getWikiName( $wikiId );
			$userId = 0;
			while ( true ) {
				$res = $db->select( [
					'securepoll_lists',
					'user'
				], [
					'user_id',
					'user_name',
					'user_email',
					'user_email_authenticated'
				], [
					'user_id=li_member',
					'li_member > ' . $db->addQuotes( $userId )
				], __METHOD__, [
					'ORDER BY' => 'li_member',
					'LIMIT' => $batchSize
				] );

				if ( !$res->numRows() ) {
					break;
				}

				foreach ( $res as $row ) {
					$userId = (int)$row->user_id;
					if ( !$row->user_email || !$row->user_email_authenticated ) {
						continue;
					}
					if ( isset( $voters[$row->user_email] ) ) {
						$voters[$row->user_email]['wikis'] .= ', ' . $wikiName;
					} else {
						$voters[$row->user_email] = [
							'wikis' => $wikiName,
							'name' => $row->user_name
						];
					}
				}
				$this->error( "Found " . count( $voters ) . " voters with email addresses\n" );
			}
			$lb->reuseConnection( $db );
		}

		foreach ( $voters as $email => $info ) {
			$this->output( "{$info['name']} <$email>\n" );
		}
	}
}

$maintClass = "DumpGlobalVoterList";
require_once RUN_MAINTENANCE_IF_MAIN;
