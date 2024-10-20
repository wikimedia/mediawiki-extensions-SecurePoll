<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Maintenance\Maintenance;

class DeletePoll extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete a poll from the local SecurePoll database' );

		$this->addArg( 'id', 'Secure Poll id to delete' );
		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$electionId = (int)$this->getArg( 0 );

		$dbw = $this->getDB( DB_PRIMARY );

		$type = $dbw->newSelectQueryBuilder()
			->select( 'en_type' )
			->from( 'securepoll_entity' )
			->where( [ 'en_id' => $electionId ] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchField();

		if ( !$type ) {
			$this->fatalError( "The specified id does not exist.\n" );
		}
		if ( $type !== 'election' ) {
			$this->fatalError( "The specified id is for an entity of type \"$type\", not \"election\".\n" );
		}

		# Get a list of entity IDs and lock them
		$questionIds = $dbw->newSelectQueryBuilder()
			->select( 'qu_entity' )
			->from( 'securepoll_questions' )
			->where( [ 'qu_election' => $electionId ] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchFieldValues();

		$optionIds = $dbw->newSelectQueryBuilder()
			->select( 'op_entity' )
			->from( 'securepoll_options' )
			->where( [ 'op_election' => $electionId ] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchFieldValues();

		$entityIds = array_merge( $optionIds, $questionIds, [ $electionId ] );

		# Delete the messages and properties
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_msgs' )
			->where( [ 'msg_entity' => $entityIds ] )
			->caller( __METHOD__ )
			->execute();
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_properties' )
			->where( [ 'pr_entity' => $entityIds ] )
			->caller( __METHOD__ )
			->execute();

		# Delete the entities
		if ( $optionIds ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'securepoll_options' )
				->where( [ 'op_entity' => $optionIds ] )
				->caller( __METHOD__ )
				->execute();
		}
		if ( $questionIds ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'securepoll_questions' )
				->where( [ 'qu_entity' => $questionIds ] )
				->caller( __METHOD__ )
				->execute();
		}
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_elections' )
			->where( [ 'el_entity' => $electionId ] )
			->caller( __METHOD__ )
			->execute();
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_entity' )
			->where( [ 'en_id' => $entityIds ] )
			->caller( __METHOD__ )
			->execute();
	}
}
$maintClass = DeletePoll::class;
require_once RUN_MAINTENANCE_IF_MAIN;
