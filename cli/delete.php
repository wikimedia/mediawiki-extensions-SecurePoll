<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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

		$type = $dbw->selectField(
			'securepoll_entity',
			'en_type',
			[ 'en_id' => $electionId ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);

		if ( !$type ) {
			$this->fatalError( "The specified id does not exist.\n" );
		}
		if ( $type !== 'election' ) {
			$this->fatalError( "The specified id is for an entity of type \"$type\", not \"election\".\n" );
		}

		# Get a list of entity IDs and lock them
		$res = $dbw->select(
			'securepoll_questions',
			[ 'qu_entity' ],
			[ 'qu_election' => $electionId ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
		$questionIds = [];
		foreach ( $res as $row ) {
			$questionIds[] = $row->qu_entity;
		}

		$res = $dbw->select(
			'securepoll_options',
			[ 'op_entity' ],
			[ 'op_election' => $electionId ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
		$optionIds = [];
		foreach ( $res as $row ) {
			$optionIds[] = $row->op_entity;
		}

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
