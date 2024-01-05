<?php

namespace MediaWiki\Extension\SecurePoll\Store;

use MediaWiki\Status\Status;
use XMLReader;

/**
 * Storage class for an XML file store. Election configuration data is cached,
 * and vote data can be loaded into a tallier on demand.
 */
class XMLStore extends MemoryStore {
	/** @var XMLReader|null */
	public $xmlReader;
	/** @var string */
	public $fileName;
	/** @var callable|null */
	public $voteCallback;
	/** @var int|null */
	public $voteElectionId;
	/** @var Status|null */
	public $voteCallbackStatus;

	/** @var string[][] Valid entity info keys by entity type. */
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
		'question' => [
			'id',
			'election'
		],
		'option' => [
			'id',
			'election'
		],
	];

	/** @var string[][] The type of each entity child and its corresponding (plural) info element */
	private static $childTypes = [
		'election' => [ 'question' => 'questions' ],
		'question' => [ 'option' => 'options' ],
		'option' => []
	];

	/** @var string[] All entity types */
	private static $entityTypes = [
		'election',
		'question',
		'option'
	];

	/**
	 * Constructor. Note that readFile() must be called before any information
	 * can be accessed. Context::newFromXmlFile() is a shortcut
	 * method for this.
	 * @param string $fileName
	 */
	public function __construct( $fileName ) {
		$this->fileName = $fileName;
	}

	/**
	 * Read the file and return boolean success.
	 * @return bool
	 */
	public function readFile() {
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
	public function doTopLevel() {
		$xr = $this->xmlReader;

		# Check document element
		while ( $xr->read() && $xr->nodeType !== XMLReader::ELEMENT ) {
		}

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
	public function doElection() {
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
				if ( $this->voteCallback && $electionInfo && $electionInfo['id'] == $this->voteElectionId ) {
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
	public function readEntity( $entityType ) {
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
				// @phan-suppress-next-line PhanTypeMismatchDimAssignment
				$messages[$lang][$name] = $value;
				continue;
			}
			if ( $xr->name == 'property' ) {
				$name = $xr->getAttribute( 'name' );
				$value = $this->readStringElement();
				// @phan-suppress-next-line PhanTypeMismatchDimAssignment
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
	public function readStringElement() {
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

	public function callbackValidVotes( $electionId, $callback, $voterId = null ) {
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
