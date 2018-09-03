<?php

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
 * and SecurePoll_Context provide methods that are more appropriate for general
 * users.
 */
interface SecurePoll_Store {
	/**
	 * Get an array of messages with a given language, and entity IDs
	 * in a given array of IDs. The return format is a 2-d array mapping ID
	 * and message key to value.
	 * @param string $lang
	 * @param int[] $ids
	 */
	function getMessages( $lang, $ids );

	/**
	 * Get a list of languages that the given entity IDs have messages for.
	 * Returns an array of language codes.
	 * @param int[] $ids
	 */
	function getLangList( $ids );

	/**
	 * Get an array of properties for a given set of IDs. Returns a 2-d array
	 * mapping IDs and property keys to values.
	 * @param int[] $ids
	 */
	function getProperties( $ids );

	/**
	 * Get the type of one or more SecurePoll entities.
	 * @param int $id
	 * @return string
	 */
	function getEntityType( $id );

	/**
	 * Get information about a set of elections, specifically the data that
	 * is stored in the securepoll_elections row in the DB. Returns a 2-d
	 * array mapping ID to associative array of properties.
	 * @param int[] $ids
	 */
	function getElectionInfo( $ids );

	/**
	 * Get election information for a given set of names.
	 * @param array $names
	 */
	function getElectionInfoByTitle( $names );

	/**
	 * Convert a row from the securepoll_elections table into an associative
	 * array suitable for return by getElectionInfo().
	 * @param stdClass $row
	 */
	function decodeElectionRow( $row );

	/**
	 * Get a database connection object.
	 * @param int $index DB_MASTER or DB_REPLICA
	 */
	function getDB( $index = DB_MASTER );

	/**
	 * Get an associative array of information about all questions in a given
	 * election.
	 * @param int $electionId
	 */
	function getQuestionInfo( $electionId );

	/**
	 * Call a callback function for all valid votes with a given election ID.
	 * @param int $electionId
	 * @param callable $callback
	 */
	function callbackValidVotes( $electionId, $callback );
}

/**
 * Storage class for a DB backend. This is the one that's most often used.
 */
class SecurePoll_DBStore implements SecurePoll_Store {
	function getMessages( $lang, $ids ) {
		$db = $this->getDB();
		$res = $db->select(
			'securepoll_msgs',
			'*',
			[
				'msg_entity' => $ids,
				'msg_lang' => $lang
			],
			__METHOD__
		);
		$messages = [];
		foreach ( $res as $row ) {
			$messages[$row->msg_entity][$row->msg_key] = $row->msg_text;
		}
		return $messages;
	}

	function getLangList( $ids ) {
		$db = $this->getDB();
		$res = $db->select(
			'securepoll_msgs',
			'DISTINCT msg_lang',
			[
				'msg_entity' => $ids
			],
			__METHOD__ );
		$langs = [];
		foreach ( $res as $row ) {
			$langs[] = $row->msg_lang;
		}
		return $langs;
	}

	function getProperties( $ids ) {
		$db = $this->getDB();
		$res = $db->select(
			'securepoll_properties',
			'*',
			[ 'pr_entity' => $ids ],
			__METHOD__ );
		$properties = [];
		foreach ( $res as $row ) {
			$properties[$row->pr_entity][$row->pr_key] = $row->pr_value;
		}
		return $properties;
	}

	function getElectionInfo( $ids ) {
		$ids = (array)$ids;
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_elections',
			'*',
			[ 'el_entity' => $ids ],
			__METHOD__ );
		$infos = [];
		foreach ( $res as $row ) {
			$infos[$row->el_entity] = $this->decodeElectionRow( $row );
		}
		return $infos;
	}

	function getElectionInfoByTitle( $names ) {
		$names = (array)$names;
		$db = $this->getDB();
		$res = $db->select(
			'securepoll_elections',
			'*',
			[ 'el_title' => $names ],
			__METHOD__ );
		$infos = [];
		foreach ( $res as $row ) {
			$infos[$row->el_title] = $this->decodeElectionRow( $row );
		}
		return $infos;
	}

	function decodeElectionRow( $row ) {
		static $map = [
			'id' => 'el_entity',
			'title' => 'el_title',
			'ballot' => 'el_ballot',
			'tally' => 'el_tally',
			'primaryLang' => 'el_primary_lang',
			'startDate' => 'el_start_date',
			'endDate' => 'el_end_date',
			'auth' => 'el_auth_type',
			'owner' => 'el_owner'
		];

		$info = [];
		foreach ( $map as $key => $field ) {
			if ( $key == 'startDate' || $key == 'endDate' ) {
				$info[$key] = wfTimestamp( TS_MW, $row->$field );
			} elseif ( isset( $row->$field ) ) {
				$info[$key] = $row->$field;
			}
		}
		return $info;
	}

	function getDB( $index = DB_MASTER ) {
		return wfGetDB( $index );
	}

	function getQuestionInfo( $electionId ) {
		$db = $this->getDB();
		$res = $db->select(
			[ 'securepoll_questions', 'securepoll_options' ],
			'*',
			[
				'qu_election' => $electionId,
				'op_question=qu_entity'
			],
			__METHOD__,
			[ 'ORDER BY' => 'qu_index, qu_entity' ]
		);

		$questions = [];
		$options = [];
		$questionId = false;
		$electionId = false;
		foreach ( $res as $row ) {
			if ( $questionId === false ) {
			} elseif ( $questionId !== $row->qu_entity ) {
				$questions[] = [
					'id' => $questionId,
					'election' => $electionId,
					'options' => $options
				];
				$options = [];
			}
			$options[] = [
				'id' => $row->op_entity,
				'election' => $row->op_election,
			];
			$questionId = $row->qu_entity;
			$electionId = $row->qu_election;
		}
		if ( $questionId !== false ) {
			$questions[] = [
				'id' => $questionId,
				'election' => $electionId,
				'options' => $options
			];
		}
		return $questions;
	}

	function callbackValidVotes( $electionId, $callback, $voterId = null ) {
		$dbr = $this->getDB();
		$where = [
			'vote_election' => $electionId,
			'vote_current' => 1,
			'vote_struck' => 0
		];
		if ( $voterId !== null ) {
			$where['vote_voter'] = $voterId;
		}
		$res = $dbr->select(
			'securepoll_votes',
			'*',
			$where,
			__METHOD__
		);

		foreach ( $res as $row ) {
			$status = call_user_func( $callback, $this, $row->vote_record );
			if ( $status instanceof Status && !$status->isOK() ) {
				return $status;
			}
		}
		return Status::newGood();
	}

	function getEntityType( $id ) {
		$db = $this->getDB();
		$res = $db->selectRow(
			'securepoll_entity',
			'*',
			[ 'en_id' => $id ],
			__METHOD__ );
		return $res
			? $res->en_type
			: false;
	}
}

/**
 * Storage class that stores all data in local memory. The memory must be
 * initialized somehow, methods for this are not provided except in the
 * subclass.
 */
class SecurePoll_MemoryStore implements SecurePoll_Store {
	public $messages, $properties, $idsByName, $votes;
	public $entityInfo;

	/**
	 * Get an array containing all election IDs stored in this object
	 * @return array
	 */
	function getAllElectionIds() {
		$electionIds = [];
		foreach ( $this->entityInfo as $info ) {
			if ( $info['type'] !== 'election' ) {
				continue;
			}
			$electionIds[] = $info['id'];
		}
		return $electionIds;
	}

	function getMessages( $lang, $ids ) {
		if ( !isset( $this->messages[$lang] ) ) {
			return [];
		}
		return array_intersect_key( $this->messages[$lang], array_flip( $ids ) );
	}

	function getLangList( $ids ) {
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

	function getProperties( $ids ) {
		$ids = (array)$ids;
		return array_intersect_key( $this->properties, array_flip( $ids ) );
	}

	function getElectionInfo( $ids ) {
		$ids = (array)$ids;
		return array_intersect_key( $this->entityInfo, array_flip( $ids ) );
	}

	function getElectionInfoByTitle( $names ) {
		$names = (array)$names;
		$ids = array_intersect_key( $this->idsByName, array_flip( $names ) );
		$info = array_intersect_key( $this->entityInfo, array_flip( $ids ) );
		return $info;
	}

	function getQuestionInfo( $electionId ) {
		return $this->entityInfo[$electionId]['questions'];
	}

	function decodeElectionRow( $row ) {
		throw new MWException( 'Internal error: attempt to use decodeElectionRow() with ' .
			'a storage class that doesn\'t support it.' );
	}

	function getDB( $index = DB_MASTER ) {
		throw new MWException( 'Internal error: attempt to use getDB() when the database ' .
			'is disabled.' );
	}

	function callbackValidVotes( $electionId, $callback ) {
		if ( !isset( $this->votes[$electionId] ) ) {
			return Status::newGood();
		}
		foreach ( $this->votes[$electionId] as $vote ) {
			$status = call_user_func( $callback, $this, $vote );
			if ( !$status->isOK() ) {
				return $status;
			}
		}
		return Status::newGood();
	}

	function getEntityType( $id ) {
		return isset( $this->entityInfo[$id] )
			? $this->entityInfo[$id]['type']
			: false;
	}
}

/**
 * Storage class for an XML file store. Election configuration data is cached,
 * and vote data can be loaded into a tallier on demand.
 */
class SecurePoll_XMLStore extends SecurePoll_MemoryStore {
	public $xmlReader, $fileName;
	public $voteCallback, $voteElectionId, $voteCallbackStatus;

	/** Valid entity info keys by entity type. */
	private static $entityInfoKeys = [
		'election' => [
			'id',
			'title',
			'ballot',
			'tally',
			'primaryLang',
			'startDate',
			'endDate',
			'auth'
		],
		'question' => [ 'id', 'election' ],
		'option' => [ 'id', 'election' ],
	];

	/** The type of each entity child and its corresponding (plural) info element */
	private static $childTypes = [
		'election' => [ 'question' => 'questions' ],
		'question' => [ 'option' => 'options' ],
		'option' => []
	];

	/** All entity types */
	private static $entityTypes = [ 'election', 'question', 'option' ];

	/**
	 * Constructor. Note that readFile() must be called before any information
	 * can be accessed. SecurePoll_Context::newFromXmlFile() is a shortcut
	 * method for this.
	 * @param string $fileName
	 */
	function __construct( $fileName ) {
		$this->fileName = $fileName;
	}

	/**
	 * Read the file and return boolean success.
	 * @return bool
	 */
	function readFile() {
		$this->xmlReader = new XMLReader;
		$xr = $this->xmlReader;
		$fileName = realpath( $this->fileName );
		$uri = 'file://' . str_replace( '%2F', '/', rawurlencode( $fileName ) );
		$xr->open( $uri );
		$xr->setParserProperty( XMLReader::SUBST_ENTITIES, true );
		$success = $this->doTopLevel();
		$xr->close();
		$this->xmlReader = null;
		return $success;
	}

	/**
	 * Do the top-level document element, and return success.
	 * @return bool
	 */
	function doTopLevel() {
		$xr = $this->xmlReader;

		# Check document element
		// @codingStandardsIgnoreStart
		while ( $xr->read() && $xr->nodeType !== XMLReader::ELEMENT );
		// @codingStandardsIgnoreEnd
		if ( $xr->name != 'SecurePoll' ) {
			wfDebug( __METHOD__ . ": invalid document element\n" );
			return false;
		}

		while ( $xr->read() ) {
			if ( $xr->nodeType !== XMLReader::ELEMENT ) {
				continue;
			}
			if ( $xr->name !== 'election' ) {
				continue;
			}
			if ( !$this->doElection() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Read an <election> element and position the cursor past the end of it.
	 * Return success.
	 * @return bool
	 */
	function doElection() {
		$xr = $this->xmlReader;
		if ( $xr->isEmptyElement ) {
			wfDebug( __METHOD__ . ": unexpected empty element\n" );
			return false;
		}
		$xr->read();
		$electionInfo = false;
		while ( $xr->nodeType !== XMLReader::NONE ) {
			if ( $xr->nodeType === XMLReader::END_ELEMENT ) {
				# Finished
				return true;
			}
			if ( $xr->nodeType !== XMLReader::ELEMENT ) {
				# Skip comments, intervening text, etc.
				$xr->read();
				continue;
			}
			if ( $xr->name === 'configuration' ) {
				# Load configuration
				$electionInfo = $this->readEntity( 'election' );
				if ( $electionInfo === false ) {
					return false;
				}
				continue;
			}

			if ( $xr->name === 'vote' ) {
				# Notify tallier of vote record if requested
				if ( $this->voteCallback && $electionInfo
					&& $electionInfo['id'] == $this->voteElectionId
				) {
					$record = $this->readStringElement();
					$status = call_user_func( $this->voteCallback, $this, $record );
					if ( !$status->isOK() ) {
						$this->voteCallbackStatus = $status;
						return false;
					}
				} else {
					$xr->next();
				}
				continue;
			}

			wfDebug( __METHOD__ . ": ignoring unrecognized element <{$xr->name}>\n" );
			$xr->next();
		}
		wfDebug( __METHOD__ . ": unexpected end of stream\n" );
		return false;
	}

	/**
	 * Read an entity configuration element: <configuration>, <question> or
	 * <option>, and position the cursor past the end of it.
	 *
	 * This function operates recursively to read child elements. It returns
	 * the info array for the entity.
	 * @param string $entityType
	 * @return false|array
	 */
	function readEntity( $entityType ) {
		$xr = $this->xmlReader;
		$info = [ 'type' => $entityType ];
		$messages = [];
		$properties = [];
		if ( $xr->isEmptyElement ) {
			wfDebug( __METHOD__ . ": unexpected empty element\n" );
			$xr->read();
			return false;
		}
		$xr->read();

		while ( true ) {
			if ( $xr->nodeType === XMLReader::NONE ) {
				wfDebug( __METHOD__ . ": unexpected end of stream\n" );
				return false;
			}
			if ( $xr->nodeType === XMLReader::END_ELEMENT ) {
				# End of entity
				$xr->read();
				break;
			}
			if ( $xr->nodeType !== XMLReader::ELEMENT ) {
				# Intervening text, comments, etc.
				$xr->read();
				continue;
			}
			if ( $xr->name === 'message' ) {
				$name = $xr->getAttribute( 'name' );
				$lang = $xr->getAttribute( 'lang' );
				$value = $this->readStringElement();
				$messages[$lang][$name] = $value;
				continue;
			}
			if ( $xr->name == 'property' ) {
				$name = $xr->getAttribute( 'name' );
				$value = $this->readStringElement();
				$properties[$name] = $value;
				continue;
			}

			# Info elements
			if ( in_array( $xr->name, self::$entityInfoKeys[$entityType] ) ) {
				$name = $xr->name;
				$value = $this->readStringElement();
				# Fix date format
				if ( $name == 'startDate' || $name == 'endDate' ) {
					$value = wfTimestamp( TS_MW, $value );
				}
				$info[$name] = $value;
				continue;
			}

			# Child elements
			if ( isset( self::$childTypes[$entityType][$xr->name] ) ) {
				$infoKey = self::$childTypes[$entityType][$xr->name];
				$childInfo = $this->readEntity( $xr->name );
				if ( !$childInfo ) {
					return false;
				}
				$info[$infoKey][] = $childInfo;
				continue;
			}

			wfDebug( __METHOD__ . ": ignoring unrecognized element <{$xr->name}>\n" );
			$xr->next();
		}

		if ( !isset( $info['id'] ) ) {
			wfDebug( __METHOD__ . ": missing id element in <$entityType>\n" );
			return false;
		}

		# This has to be done after the element is fully parsed, or you
		# have to require 'id' to be above any children in the XML doc.
		$this->addParentIds( $info, $info['type'], $info['id'] );

		$id = $info['id'];
		if ( isset( $info['title'] ) ) {
			$this->idsByName[$info['title']] = $id;
		}
		$this->entityInfo[$id] = $info;
		foreach ( $messages as $lang => $values ) {
			$this->messages[$lang][$id] = $values;
		}
		$this->properties[$id] = $properties;
		return $info;
	}

	/**
	 * Propagate parent ids to child elements
	 * @param array &$info
	 * @param string $key
	 * @param int $id
	 */
	public function addParentIds( &$info, $key, $id ) {
		foreach ( self::$childTypes[$info['type']] as $childType ) {
			if ( isset( $info[$childType] ) ) {
				foreach ( $info[$childType] as &$child ) {
					$child[$key] = $id;
					# Recurse
					$this->addParentIds( $child, $key, $id );
				}
			}
		}
	}

	/**
	 * When the cursor is positioned on an element node, this reads the entire
	 * element and returns the contents as a string. On return, the cursor is
	 * positioned past the end of the element.
	 * @return string
	 */
	function readStringElement() {
		$xr = $this->xmlReader;
		if ( $xr->isEmptyElement ) {
			$xr->read();
			return '';
		}
		$s = '';
		$level = 1;
		while ( $xr->read() && $level ) {
			if ( $xr->nodeType == XMLReader::TEXT ) {
				$s .= $xr->value;
				continue;
			}
			if ( $xr->nodeType == XMLReader::ELEMENT && !$xr->isEmptyElement ) {
				$level++;
				continue;
			}
			if ( $xr->nodeType == XMLReader::END_ELEMENT ) {
				$level--;
				continue;
			}
		}
		return $s;
	}

	function callbackValidVotes( $electionId, $callback ) {
		$this->voteCallback = $callback;
		$this->voteElectionId = $electionId;
		$this->voteCallbackStatus = Status::newGood();
		$success = $this->readFile();
		$this->voteCallback = $this->voteElectionId = null;
		if ( !$this->voteCallbackStatus->isOK() ) {
			return $this->voteCallbackStatus;
		} elseif ( $success ) {
			return Status::newGood();
		} else {
			return Status::newFatal( 'securepoll-dump-file-corrupt' );
		}
	}
}
