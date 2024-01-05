<?php

namespace MediaWiki\Extension\SecurePoll\Store;

use LogicException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;

/**
 * Storage class that stores all data in local memory. The memory must be
 * initialized somehow, methods for this are not provided except in the
 * subclass.
 */
class MemoryStore implements Store {
	/** @var array|null */
	public $messages;
	/** @var array|null */
	public $properties;
	/** @var array|null */
	public $idsByName;
	/** @var array|null */
	public $votes;
	/** @var array */
	public $entityInfo = [];

	/**
	 * Get an array containing all election IDs stored in this object
	 * @return array
	 */
	public function getAllElectionIds() {
		$electionIds = [];
		foreach ( $this->entityInfo as $info ) {
			if ( $info['type'] !== 'election' ) {
				continue;
			}
			$electionIds[] = $info['id'];
		}

		return $electionIds;
	}

	public function getMessages( $lang, $ids ) {
		if ( !isset( $this->messages[$lang] ) ) {
			return [];
		}

		return array_intersect_key( $this->messages[$lang], array_flip( $ids ) );
	}

	public function getLangList( $ids ) {
		$langs = [];
		foreach ( $this->messages as $lang => $langMessages ) {
			foreach ( $ids as $id ) {
				if ( isset( $langMessages[$id] ) ) {
					$langs[] = $lang;
					break;
				}
			}
		}

		return $langs;
	}

	public function getProperties( $ids ) {
		$ids = (array)$ids;

		return array_intersect_key( $this->properties, array_flip( $ids ) );
	}

	public function getElectionInfo( $ids ) {
		$ids = (array)$ids;

		return array_intersect_key( $this->entityInfo, array_flip( $ids ) );
	}

	public function getElectionInfoByTitle( $names ) {
		$names = (array)$names;
		$ids = array_intersect_key( $this->idsByName, array_flip( $names ) );

		return array_intersect_key( $this->entityInfo, array_flip( $ids ) );
	}

	public function getQuestionInfo( $electionId ) {
		return $this->entityInfo[$electionId]['questions'];
	}

	public function decodeElectionRow( $row ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod LSP violation
		throw new LogicException(
			'Internal error: attempt to use decodeElectionRow() with ' .
			'a storage class that doesn\'t support it.'
		);
	}

	public function getDB( $index = DB_PRIMARY ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod LSP violation
		throw new LogicException(
			'Internal error: attempt to use getDB() when the database is disabled.'
		);
	}

	public function setForcePrimary( $forcePrimary ) {
	}

	public function callbackValidVotes( $electionId, $callback, $voterId = null ) {
		if ( !isset( $this->votes[$electionId] ) ) {
			return Status::newGood();
		}

		// T288366 Tallies fail on beta/prod with little visibility
		// Add logging to gain more context into where it fails
		LoggerFactory::getInstance( 'AdHocDebug' )->info(
			'Adding votes to election',
			[
				'electionId' => $electionId,
				'voteCount' => count( $this->votes[$electionId] ),
			]
		);

		foreach ( $this->votes[$electionId] as $i => $vote ) {
			$status = call_user_func( $callback, $this, $vote );
			if ( !$status->isOK() ) {
				return $status;
			}

			if ( $i % 5 === 0 ) {
				// T288366 Tallies fail on beta/prod with little visibility
				// Add logging to gain more context into where it fails
				LoggerFactory::getInstance( 'AdHocDebug' )->info(
					'Batch addRecord() currently in progress',
					[
						'electionId' => $electionId,
						'currentVoteIndex' => $i,
					]
				);
			}
		}

		return Status::newGood();
	}

	public function getEntityType( $id ) {
		return isset( $this->entityInfo[$id] ) ? $this->entityInfo[$id]['type'] : false;
	}
}
