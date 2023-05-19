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
		$dbw->delete( 'securepoll_msgs', [ 'msg_entity' => $entityIds ] );
		$dbw->delete( 'securepoll_properties', [ 'pr_entity' => $entityIds ] );

		# Delete the entities
		if ( $optionIds ) {
			$dbw->delete( 'securepoll_options', [ 'op_entity' => $optionIds ], __METHOD__ );
		}
		if ( $questionIds ) {
			$dbw->delete( 'securepoll_questions', [ 'qu_entity' => $questionIds ], __METHOD__ );
		}
		$dbw->delete( 'securepoll_elections', [ 'el_entity' => $electionId ], __METHOD__ );
		$dbw->delete( 'securepoll_entity', [ 'en_id' => $entityIds ], __METHOD__ );
	}
}
$maintClass = DeletePoll::class;
require_once RUN_MAINTENANCE_IF_MAIN;
