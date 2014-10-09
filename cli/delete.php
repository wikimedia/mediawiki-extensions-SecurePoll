<?php

require( dirname( __FILE__ ) . '/cli.inc' );

$usage = <<<EOT
Delete a poll from the local SecurePoll database.

Usage: delete.php <id>

EOT;

if ( !isset( $args[0] ) ) {
	echo $usage;
	exit( 1 );
}
$id = intval( $args[0] );
if ( $args[0] !== (string)$id ) {
	echo "The specified id \"{$args[0]}\" is not an integer\n";
	exit( 1 );
}

$success = spDeleteElection( $id );
exit( $success ? 0 : 1 );

/**
 * @param $electionId int|string
 */
function spDeleteElection( $electionId ) {
	$dbw = wfGetDB( DB_MASTER );

	$type = $dbw->selectField( 'securepoll_entity', 'en_type',
		array( 'en_id' => $electionId ),
		__METHOD__, array( 'FOR UPDATE' ) );
	if ( !$type ) {
		echo "The specified id does not exist.\n";
		return false;
	}
	if ( $type !== 'election' ) {
		echo "The specified id is for an entity of type \"$type\", not \"election\".\n";
		return false;
	}

	# Get a list of entity IDs and lock them
	$questionIds = array();
	$res = $dbw->select( 'securepoll_questions', array( 'qu_entity' ),
		array( 'qu_election' => $electionId ),
		__METHOD__, array( 'FOR UPDATE' ) );
	foreach ( $res as $row ) {
		$questionIds[] = $row->qu_entity;
	}

	$res = $dbw->select( 'securepoll_options', array( 'op_entity' ),
		array( 'op_election' => $electionId ),
		__METHOD__, array( 'FOR UPDATE' ) );
	$optionIds = array();
	foreach ( $res as $row ) {
		$optionIds[] = $row->op_entity;
	}

	$entityIds = array_merge( $optionIds, $questionIds, array( $electionId ) );

	# Delete the messages and properties
	$dbw->delete( 'securepoll_msgs', array( 'msg_entity' => $entityIds ) );
	$dbw->delete( 'securepoll_properties', array( 'pr_entity' => $entityIds ) );

	# Delete the entities
	if ( $optionIds ) {
		$dbw->delete( 'securepoll_options', array( 'op_entity' => $optionIds ), __METHOD__ );
	}
	if ( $questionIds ) {
		$dbw->delete( 'securepoll_questions', array( 'qu_entity' => $questionIds ), __METHOD__ );
	}
	$dbw->delete( 'securepoll_elections', array( 'el_entity' => $electionId ), __METHOD__ );
	$dbw->delete( 'securepoll_entity', array( 'en_id' => $entityIds ), __METHOD__ );

	return true;
}
