<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDatabase;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Find users with a specified user right and add them to a local or global
 * SecurePoll list.
 */
class FindUsersWithRight extends Maintenance {
	/** @var string */
	private $right;

	/** @var string|null */
	private $centralList;

	/** @var string|null */
	private $localList;

	/** @var int */
	private $numAdded = 0;

	/** @var int */
	private $numFound = 0;

	/** @var int */
	private $numUnattached = 0;

	/** @var bool */
	private $centralAuthLoaded = false;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Find users with a specified user right, " .
			"and add them to a local or global SecurePoll list" );
		$this->addOption( 'right', 'The name of the user right to look for',
			true, true );
		$this->addOption( 'list',
			'Add bots to the specified local list',
			false, true );
		$this->addOption( 'central-list',
			'Add bots to the specified central list',
			false, true );
	}

	public function execute() {
		$this->right = $this->getOption( 'right' );
		$this->localList = $this->getOption( 'list' );
		$this->centralList = $this->getOption( 'central-list' );
		if ( !$this->localList && !$this->centralList ) {
			$this->fatalError( 'Either --local-list or --central-list must be specified.' );
		}

		$this->centralAuthLoaded = ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' );

		$this->doLocalGroups();
		$this->doCentralGroups();
		echo "Found {$this->numFound} users, added {$this->numAdded} unique users to the list.\n";
		if ( $this->numUnattached ) {
			echo "{$this->numUnattached} users were not attached so could not be added to the central list.\n";
		}
	}

	private function doLocalGroups() {
		$services = MediaWikiServices::getInstance();
		$groups = $services->getGroupPermissionsLookup()
			->getGroupsWithPermission( $this->right );
		if ( !$groups ) {
			echo "No groups have the specified right; no users added.\n";
			return;
		}

		$lb = $services->getDBLoadBalancer();
		$dbr = $lb->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'user_id', 'user_name' ] )
			->from( 'user_groups' )
			->join( 'user', null, [ 'user_id=ug_user' ] )
			->where( [
				'ug_group' => $groups,
				// Expiry condition similar to UsersPager::getQueryInfo()
				$dbr->expr( 'ug_expiry', '=', null )->or( 'ug_expiry', '>=', $dbr->timestamp() ),
			] )
			->distinct()
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->numFound += $res->numRows();

		if ( $this->centralList && $this->centralAuthLoaded ) {
			$centralIdLookup = $services->getCentralIdLookup();
			$caDbManager = CentralAuthServices::getDatabaseManager();
			$dbcw = $caDbManager->getCentralPrimaryDB();

			foreach ( $res as $row ) {
				$user = new UserIdentityValue( (int)$row->user_id, $row->user_name );
				$cid = $centralIdLookup->centralIdFromLocalUser( $user );
				if ( $cid ) {
					$this->addToList( $dbcw, $this->centralList, $cid );
				} else {
					$this->numUnattached++;
				}
			}
		}

		if ( $this->localList ) {
			$dbw = $lb->getConnection( DB_PRIMARY );
			foreach ( $res as $row ) {
				$this->addToList( $dbw, $this->localList, $row->user_id );
			}
		}
	}

	private function doCentralGroups() {
		if ( !$this->centralAuthLoaded ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
		$caDbManager = CentralAuthServices::getDatabaseManager();
		$dbcr = $caDbManager->getCentralReplicaDB();

		// Get a list of users, ignoring restrictions and expiry
		$res = $dbcr->newSelectQueryBuilder()
			->select( [ 'gu_name', 'gu_id', 'lu_local_id' ] )
			->from( 'global_user_groups' )
			->join( 'global_group_permissions', null, 'ggp_group=gug_group' )
			->join( 'globaluser', null, 'gu_id=gug_user' )
			->join( 'localuser', null, 'gu_name=lu_name' )
			->where( [
				'ggp_permission' => $this->right,
				'lu_wiki' => WikiMap::getCurrentWikiId()
			] )
			->distinct()
			->caller( __METHOD__ )
			->fetchResultSet();

		// Apply restrictions and expiry by calling CentralAuthUser for each discovered user
		$localIds = [];
		$centralIds = [];
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::getInstanceByName( $row->gu_name );
			if ( $centralUser->hasGlobalPermission( $this->right ) ) {
				$this->numFound++;
				$localIds[] = $row->lu_local_id;
				$centralIds[] = $row->gu_id;
			}
		}

		if ( $localIds && $this->localList ) {
			$dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			foreach ( $localIds as $id ) {
				$this->addToList( $dbw, $this->localList, $id );
			}
		}
		if ( $centralIds && $this->centralList ) {
			$dbcw = $caDbManager->getCentralPrimaryDB();
			foreach ( $centralIds as $id ) {
				$this->addToList( $dbcw, $this->centralList, $id );
			}
		}
	}

	/**
	 * @param IDatabase $db
	 * @param string $list
	 * @param int $member
	 */
	private function addToList( $db, $list, $member ) {
		$insertRow = [
			'li_name' => $list,
			'li_member' => $member
		];
		// FIXME: make the index unique so that we can use INSERT IGNORE
		$alreadyAdded = $db->newSelectQueryBuilder()
			->select( '1' )
			->from( 'securepoll_lists' )
			->where( $insertRow )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$alreadyAdded ) {
			$db->newInsertQueryBuilder()
				->insertInto( 'securepoll_lists' )
				->row( $insertRow )
				->caller( __METHOD__ )
				->execute();
			$this->numAdded += $db->affectedRows();
		}
	}
}

$maintClass = FindUsersWithRight::class;
require_once RUN_MAINTENANCE_IF_MAIN;
