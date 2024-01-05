<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Crypt\Crypt;
use MediaWiki\Extension\SecurePoll\Crypt\Random;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Store\DBStore;
use MediaWiki\Extension\SecurePoll\Store\Store;
use MediaWiki\Extension\SecurePoll\Store\XMLStore;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use MediaWiki\Extension\SecurePoll\User\RemoteMWAuth;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ParserOptions;
use RequestContext;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

/**
 * This object contains caches and various items of processing context for
 * SecurePoll. It manages instances of long-lived objects such as the
 * Store subclass.
 *
 * Long-lived data should be stored here, rather than in global variables or
 * static member variables.
 *
 * A context object is passed to almost all SecurePoll constructors. This class
 * provides factory functions for these objects.
 *
 * For debugging purposes, a var_dump() workalike which omits context objects
 * is available as $context->varDump().
 */
class Context {
	/** @var string[] Language fallback sequence */
	public $languages = [ 'en' ];

	/**
	 * Message text cache
	 * @var string[][][]
	 */
	public $messageCache = [];

	/** @var array election cache */
	public $electionCache = [];

	/**
	 * @var array Which messages are loaded. 2-d array: language and entity ID, value arbitrary.
	 */
	public $messagesLoaded = [];

	/** @var ParserOptions|null ParserOptions instance used for message parsing */
	public $parserOptions;

	/**
	 * Key value store of data needed for a decryption method. Data is added and
	 * used by a decryption object and persists across different decryption
	 * object instances.
	 *
	 * @var array
	 */
	public $decryptData = [];

	/** @var Store|null The store object */
	public $store;

	/** @var Random|null The Random instance */
	public $random;

	/** @var string[] */
	private $ballotTypesForVote;

	/**
	 * Create a new Context with an XML file as the storage backend.
	 * Returns false if there was a problem with the file, like a parse error.
	 * @param string $fileName
	 * @return bool|self
	 */
	public static function newFromXmlFile( $fileName ) {
		$context = new self;
		$store = new XMLStore( $fileName );
		$context->setStore( $store );
		$success = $store->readFile();
		if ( $success ) {
			return $context;
		} else {
			return false;
		}
	}

	/**
	 * Get the ParserOptions instance
	 * @return ParserOptions
	 */
	public function getParserOptions() {
		if ( !$this->parserOptions ) {
			$this->parserOptions = ParserOptions::newFromUser(
				RequestContext::getMain()->getUser()
			);
		}

		return $this->parserOptions;
	}

	/**
	 * Get the Store instance
	 * @return Store
	 */
	public function getStore() {
		if ( !isset( $this->store ) ) {
			$this->store = new DBStore(
				MediaWikiServices::getInstance()->getDBLoadBalancer(),
				false
			);
		}

		return $this->store;
	}

	/**
	 * Get a Title object for Special:SecurePoll
	 * @param string|false $subpage
	 * @return Title
	 */
	public function getSpecialTitle( $subpage = false ) {
		return SpecialPage::getTitleFor( 'SecurePoll', $subpage );
	}

	/**
	 * Set the store object. Overrides any previous store class.
	 * @param Store $store
	 */
	public function setStore( $store ) {
		$this->messageCache = $this->messagesLoaded = [];
		$this->store = $store;
	}

	/**
	 * Get the type of a particular entity
	 * @param int $id
	 * @return string|false
	 */
	public function getEntityType( $id ) {
		return $this->getStore()->getEntityType( $id );
	}

	/**
	 * Get an election object from the store, with a given entity ID. Returns
	 * false if it does not exist.
	 * @param int $id
	 * @return Election|bool
	 */
	public function getElection( $id ) {
		if ( !isset( $this->electionCache[$id] ) ) {
			$info = $this->getStore()->getElectionInfo( [ $id ] );
			if ( $info ) {
				$this->electionCache[$id] = $this->newElection( reset( $info ) );
			} else {
				$this->electionCache[$id] = false;
			}
		}

		return $this->electionCache[$id];
	}

	/**
	 * Get an election object from the store, with a given name. Returns false
	 * if there is no such election.
	 * @param string $name
	 * @return Election|false
	 */
	public function getElectionByTitle( $name ) {
		$info = $this->getStore()->getElectionInfoByTitle( [ $name ] );
		if ( $info ) {
			return $this->newElection( reset( $info ) );
		} else {
			return false;
		}
	}

	/**
	 * Get an election object from a securepoll_elections DB row. This will fail
	 * if the current store class does not support database operations.
	 * @param stdClass $row
	 * @return Election
	 */
	public function newElectionFromRow( $row ) {
		$info = $this->getStore()->decodeElectionRow( $row );

		return $this->newElection( $info );
	}

	/**
	 * Get a voter object from a securepoll_voters row
	 * @param stdClass $row
	 * @return Voter
	 */
	public function newVoterFromRow( $row ) {
		return Voter::newFromRow( $this, $row );
	}

	/**
	 * Create a voter with the given parameters. Assumes the voter does not exist,
	 * and inserts it into the database.
	 *
	 * The row needs to be locked before this function is called, to avoid
	 * duplicate key errors.
	 * @param array $params
	 * @return Voter
	 */
	public function createVoter( $params ) {
		return Voter::createVoter( $this, $params );
	}

	/**
	 * Create a voter object from the database
	 * @param int $id
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @return Voter|false false if the ID is not valid
	 */
	public function getVoter( $id, $index = DB_PRIMARY ) {
		return Voter::newFromId( $this, $id, $index );
	}

	/**
	 * Get a Random instance. This provides cryptographic random
	 * number generation.
	 * @return Random
	 */
	public function getRandom() {
		if ( !$this->random ) {
			$this->random = new Random;
		}

		return $this->random;
	}

	/**
	 * Set the global language fallback sequence.
	 *
	 * @param array $languages A list of language codes. When a message is
	 *     requested, the first code in the array will be tried first, followed
	 *     by the subsequent codes.
	 */
	public function setLanguages( $languages ) {
		$this->languages = $languages;
	}

	/**
	 * Get some messages from the backend store or the cache.
	 * This is an internal interface for Entity, generally you
	 * should use Entity::getMessage() instead.
	 *
	 * @param string $lang Language code
	 * @param array $ids Entity IDs
	 * @return string[][]
	 */
	public function getMessages( $lang, $ids ) {
		if ( isset( $this->messagesLoaded[$lang] ) ) {
			$cacheRow = $this->messagesLoaded[$lang];
			$uncachedIds = array_flip( $ids );
			foreach ( $uncachedIds as $id => $unused ) {
				if ( isset( $cacheRow[$id] ) ) {
					unset( $uncachedIds[$id] );
				}
			}
			if ( count( $uncachedIds ) ) {
				$messages = $this->getStore()->getMessages( $lang, array_keys( $uncachedIds ) );
				$this->messageCache[$lang] += $messages;
				$this->messagesLoaded[$lang] += $uncachedIds;
			}

			return array_intersect_key( $this->messageCache[$lang], array_flip( $ids ) );
		} else {
			$this->messagesLoaded[$lang] = $ids;
			$this->messageCache[$lang] = $this->getStore()->getMessages( $lang, $ids );

			return $this->messageCache[$lang];
		}
	}

	/**
	 * Get a particular message.
	 * This is an internal interface for Entity, generally you
	 * should use Entity::getMessage() instead.
	 *
	 * @param string $lang Language code
	 * @param string|int $id Entity ID
	 * @param string $key Message key
	 * @return string|false
	 */
	public function getMessage( $lang, $id, $key ) {
		if ( !isset( $this->messagesLoaded[$lang][$id] ) ) {
			$this->getMessages( $lang, [ $id ] );
		}

		return $this->messageCache[$lang][$id][$key] ?? false;
	}

	/**
	 * Get a database object, or throw an exception if the current store object
	 * does not support database operations.
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @return IDatabase
	 */
	public function getDB( $index = DB_PRIMARY ) {
		return $this->getStore()->getDB( $index );
	}

	/**
	 * @param array $info
	 * @return Election
	 */
	public function newElection( $info ) {
		return new Election( $this, $info );
	}

	/**
	 * @param array $info
	 * @return Question
	 */
	public function newQuestion( $info ) {
		return new Question( $this, $info );
	}

	/**
	 * @param array $info
	 * @return Option
	 */
	public function newOption( $info ) {
		return new Option( $this, $info );
	}

	/**
	 * @param string $type
	 * @param Election $election
	 * @return Crypt|false False when encryption type is set to "none"
	 */
	public function newCrypt( $type, $election ) {
		return Crypt::factory( $this, $type, $election );
	}

	/**
	 * @param string $type
	 * @param ElectionTallier $electionTallier
	 * @param Question $question
	 * @return Tallier
	 */
	public function newTallier( $type, $electionTallier, $question ) {
		return Tallier::factory( $this, $type, $electionTallier, $question );
	}

	/**
	 * @param string $type
	 * @param Election $election
	 * @return Ballot
	 */
	public function newBallot( $type, $election ) {
		return Ballot::factory( $this, $type, $election );
	}

	/**
	 * Get a map of ballot type names to classes. Include all the ballot
	 * types that may be found in the securepoll_votes table.
	 *
	 * @return string[]
	 */
	public function getBallotTypesForTally() {
		return Ballot::BALLOT_TYPES;
	}

	/**
	 * Get a map of ballot type names to classes, for all ballot types which
	 * are valid for new votes and election creation.
	 *
	 * @return string[]
	 */
	public function getBallotTypesForVote() {
		if ( $this->ballotTypesForVote === null ) {
			$types = $this->getBallotTypesForTally();

			// Archived (T300087)
			unset( $types['radio-range-comment' ] );

			// Remove STV from options if flag is not set
			if ( !RequestContext::getMain()->getConfig()->get( 'SecurePollSingleTransferableVoteEnabled' ) ) {
				unset( $types['stv'] );
			}
			$this->ballotTypesForVote = $types;
		}
		return $this->ballotTypesForVote;
	}

	/**
	 * @param string $type
	 * @return LocalAuth|RemoteMWAuth
	 */
	public function newAuth( $type ) {
		return Auth::factory( $this, $type );
	}

	/**
	 * @param array $params
	 * @return Voter
	 */
	public function newVoter( $params ) {
		return new Voter( $this, $params );
	}

	/**
	 * @param Election $election
	 * @return ElectionTallier
	 */
	public function newElectionTallier( $election ) {
		return new ElectionTallier( $this, $election );
	}

	/**
	 * Debugging function to output a representation of a mixed-type variable,
	 * but omitting the $obj->context member variables for brevity.
	 *
	 * @param mixed $var
	 * @param bool $return True to return the text instead of echoing
	 * @param int $level Recursion level, leave this as zero when calling.
	 * @return string|void
	 */
	public function varDump( $var, $return = false, $level = 0 ) {
		$tab = '    ';
		$indent = str_repeat( $tab, $level );
		if ( is_array( $var ) ) {
			$s = "array(\n";
			foreach ( $var as $key => $value ) {
				$s .= "$indent$tab" . $this->varDump(
						$key,
						true,
						$level + 1
					) . " => " . $this->varDump( $value, true, $level + 1 ) . ",\n";
			}
			$s .= "{$indent})";
		} elseif ( is_object( $var ) ) {
			$props = (array)$var;
			$s = get_class( $var ) . " {\n";
			foreach ( $props as $key => $value ) {
				$s .= "$indent$tab" . $this->varDump( $key, true, $level + 1 ) . " => ";
				if ( $key === 'context' ) {
					$s .= "[CONTEXT],\n";
				} else {
					$s .= $this->varDump( $value, true, $level + 1 ) . ",\n";
				}
			}
			$s .= "{$indent}}";
		} else {
			$s = var_export( $var, true );
		}
		if ( $level == 0 ) {
			$s .= "\n";
		}
		if ( $return ) {
			return $s;
		} else {
			echo $s;
		}
	}

	/**
	 * @param string $resource
	 * @return string
	 */
	public function getResourceUrl( $resource ) {
		global $wgExtensionAssetsPath;

		return "$wgExtensionAssetsPath/SecurePoll/resources/$resource";
	}
}
