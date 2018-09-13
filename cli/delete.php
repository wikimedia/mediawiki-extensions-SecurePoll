<?php

require __DIR__ . '/cli.inc';

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
 * @suppress SecurityCheck-XSS
 * @param int|string $electionId
 * @return bool
 */
function spDeleteElection( $electionId ) {
	$dbw = wfGetDB( DB_MASTER );

	$type = $dbw->selectField( 'securepoll_entity', 'en_type',
		[ 'en_id' => $electionId ],
		__METHOD__, [ 'FOR UPDATE' ] );
	if ( !$type ) {
		echo "The specified id does not exist.\n";
		return false;
	}
	if ( $type !== 'election' ) {
		echo "The specified id is for an entity of type \"$type\", not \"election\".\n";
		return false;
	}

	# Get a list of entity IDs and lock them
	$questionIds = [];
	$res = $dbw->select( 'securepoll_questions', [ 'qu_entity' ],
		[ 'qu_election' => $electionId ],
		__METHOD__, [ 'FOR UPDATE' ] );
	foreach ( $res as $row ) {
		$questionIds[] = $row->qu_entity;
	}

	$res = $dbw->select( 'securepoll_options', [ 'op_entity' ],
		[ 'op_election' => $electionId ],
		__METHOD__, [ 'FOR UPDATE' ] );
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

	return true;
}
