<?php

namespace MediaWiki\Extension\SecurePoll\Store;

use MediaWiki\Status\Status;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

/**
 * This is an abstraction of the persistence layer, to allow XML dumps to be
 * operated on and tallied, like elections in the local DB.
 *
 * Most of the UI layer has no need for this abstraction, and so we provide
 * direct database access via getDB() to ease development of those components.
 * The XML store will throw an exception if getDB() is called on it.
 *
 * Most of the functions here are internal interfaces for the use of
 * the entity classes (election, question and option). The entity classes
 * and Context provide methods that are more appropriate for general
 * users.
 */
interface Store {
	/**
	 * Get an array of messages with a given language, and entity IDs
	 * in a given array of IDs. The return format is a 2-d array mapping ID
	 * and message key to value.
	 * @param string $lang
	 * @param int[] $ids
	 * @return string[][]
	 */
	public function getMessages( $lang, $ids );

	/**
	 * Get a list of languages that the given entity IDs have messages for.
	 * Returns an array of language codes.
	 * @param int[] $ids
	 * @return string[]
	 */
	public function getLangList( $ids );

	/**
	 * Get an array of properties for a given set of IDs. Returns a 2-d array
	 * mapping IDs and property keys to values.
	 * @param int[] $ids
	 * @return string[][]
	 */
	public function getProperties( $ids );

	/**
	 * Get the type of one or more SecurePoll entities.
	 * @param int $id
	 * @return string|false
	 */
	public function getEntityType( $id );

	/**
	 * Get information about a set of elections, specifically the data that
	 * is stored in the securepoll_elections row in the DB. Returns a 2-d
	 * array mapping ID to associative array of properties.
	 * @param int[] $ids
	 * @return array[]
	 */
	public function getElectionInfo( $ids );

	/**
	 * Get election information for a given set of names.
	 * @param array $names
	 * @return array[]
	 */
	public function getElectionInfoByTitle( $names );

	/**
	 * Convert a row from the securepoll_elections table into an associative
	 * array suitable for return by getElectionInfo().
	 * @param stdClass $row
	 * @return array
	 */
	public function decodeElectionRow( $row );

	/**
	 * Get a database connection object.
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @return IDatabase
	 */
	public function getDB( $index = DB_PRIMARY );

	/**
	 * Set a flag which, if true, forces subsequent calls to getDB() to return
	 * a primary connection even if $index is DB_REPLICA.
	 *
	 * @param bool $forcePrimary
	 */
	public function setForcePrimary( $forcePrimary );

	/**
	 * Get an associative array of information about all questions in a given
	 * election.
	 * @param int $electionId
	 * @return array[]
	 */
	public function getQuestionInfo( $electionId );

	/**
	 * Call a callback function for all valid votes with a given election ID.
	 * @param int $electionId
	 * @param callable $callback
	 * @param int|null $voterId Optional, only used by some implementations
	 * @return Status
	 */
	public function callbackValidVotes( $electionId, $callback, $voterId = null );
}
