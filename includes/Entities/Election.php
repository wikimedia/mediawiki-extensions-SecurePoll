<?php

namespace MediaWiki\Extension\SecurePoll\Entities;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Crypt\Crypt;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\Xml\Xml;
use Wikimedia\Rdbms\IDatabase;

/**
 * Class representing an *election*. The term is intended to include straw polls,
 * surveys, etc. An election has one or more *questions* which voters answer.
 * The *voters* submit their *votes*, which are later tallied to provide a result.
 * An election runs only once and produces a single result.
 *
 * Each election has its own independent set of voters. Voters are created
 * when the underlying user attempts to vote. A voter may vote more than once,
 * unless the election disallows this, but only one of their votes is counted.
 *
 * Elections have a list of key/value pairs called properties, which are defined
 * and used by various modules in order to configure the election. The properties,
 * in order of the module that defines them, are as follows:
 *
 *      Election
 *          min-edits
 *              Minimum number of edits needed to be qualified
 *          max-registration
 *              Latest acceptable registration date
 *          not-sitewide-blocked
 *              True if voters need to not have a sitewide block
 *          not-partial-blocked
 *              True if voters need to not have a partial block
 *          not-bot
 *              True if voters need to not have the bot permission
 *          need-group
 *              The name of an MW group voters need to be in
 *          need-list
 *              The name of a SecurePoll list voters need to be in
 *          need-central-list
 *              The name of a list in the CentralAuth database which is linked
 *              to globaluser.gu_id
 *          include-list
 *              The name of a SecurePoll list of voters who can vote regardless of the above
 *          exclude-list
 *              The name of a SecurePoll list of voters who may not vote regardless of the above
 *          admins
 *              A list of admin names, pipe separated
 *          disallow-change
 *              True if a voter is not allowed to change their vote
 *          encrypt-type
 *              The encryption module name
 *          not-centrally-blocked
 *              True if voters need to not be blocked on more than X projects
 *          central-block-threshold
 *              Number of blocks across projects that disqualify a user from voting.
 *          voter-privacy
 *              True to disable transparency features (public voter list and
 *              public encrypted record dump) in favour of preserving voter
 *              privacy.
 *
 *      See the other module for documentation of the following.
 *
 *      RemoteMWAuth
 *          remote-mw-script-path
 *
 *      Ballot
 *          shuffle-questions
 *          shuffle-options
 *
 *      GpgCrypt
 *          gpg-encrypt-key
 *          gpg-sign-key
 *          gpg-decrypt-key
 *
 *      OpenSslCrypt
 *          openssl-encrypt-key
 *          openssl-sign-key
 *          openssl-decrypt-key
 *          openssl-verify-key
 *
 *      VotePage
 *          jump-url
 *          jump-id
 *          return-url
 */
class Election extends Entity {
	/** @var Question[]|null */
	public $questions;
	/** @var Auth|null */
	public $auth;
	/** @var Ballot|null */
	public $ballot;
	/** @var string */
	public $id;
	/** @var string */
	public $title;
	/** @var string */
	public $ballotType;
	/** @var string */
	public $tallyType;
	/** @var string */
	public $primaryLang;
	/** @var string */
	public $startDate;
	/** @var string */
	public $endDate;
	/** @var string */
	public $authType;
	/** @var int */
	public $owner = 0;

	/**
	 * Constructor.
	 *
	 * Do not use this constructor directly, instead use
	 * Context::getElection().
	 * @param Context $context
	 * @param array $info
	 */
	public function __construct( $context, $info ) {
		parent::__construct( $context, 'election', $info );
		$this->id = $info['id'];
		$this->title = $info['title'];
		$this->ballotType = $info['ballot'];
		$this->tallyType = $info['tally'];
		$this->primaryLang = $info['primaryLang'];
		$this->startDate = $info['startDate'];
		$this->endDate = $info['endDate'];
		$this->authType = $info['auth'];
		if ( isset( $info['owner'] ) ) {
			$this->owner = $info['owner'];
		}
	}

	/**
	 * Get a list of localisable message names. See Entity.
	 * @return array
	 */
	public function getMessageNames() {
		return [
			'title',
			'intro',
			'jump-text',
			'return-text',
			'unqualified-error',
			'comment-prompt'
		];
	}

	/**
	 * Get the election's parent election... hmm...
	 * @return Election
	 */
	public function getElection() {
		return $this;
	}

	/**
	 * Get a list of child entity objects. See Entity.
	 * @return array
	 */
	public function getChildren() {
		return $this->getQuestions();
	}

	/**
	 * Get the start date in MW internal form.
	 * @return string
	 */
	public function getStartDate() {
		return $this->startDate;
	}

	/**
	 * Get the end date in MW internal form.
	 * @return string
	 */
	public function getEndDate() {
		return $this->endDate;
	}

	/**
	 * Returns true if the election has started.
	 * @param string|bool $ts The reference timestamp, or false for now.
	 * @return bool
	 */
	public function isStarted( $ts = false ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}

		return !$this->startDate || $ts >= $this->startDate;
	}

	/**
	 * Returns true if the election has finished.
	 * @param string|bool $ts The reference timestamp, or false for now.
	 * @return bool
	 */
	public function isFinished( $ts = false ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}

		return $this->endDate && $ts >= $this->endDate;
	}

	/**
	 * Returns number of votes from an election.
	 * @return string
	 */
	public function getVotesCount() {
		$dbr = $this->context->getDB( DB_REPLICA );

		return $dbr->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'securepoll_votes' )
			->where( [
				'vote_election' => $this->getId(),
				'vote_current' => 1,
				'vote_struck' => 0,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Get the ballot object for this election.
	 * @return Ballot
	 */
	public function getBallot() {
		if ( !$this->ballot ) {
			$this->ballot = $this->context->newBallot( $this->ballotType, $this );
		}

		return $this->ballot;
	}

	/**
	 * Determine whether a voter would be qualified to vote in this election,
	 * based on the given associative array of parameters.
	 * @param array $params Associative array
	 * @return Status
	 */
	public function getQualifiedStatus( $params ) {
		global $wgLang;
		$props = $params['properties'];
		$status = Status::newGood();

		$lists = $props['lists'] ?? [];
		$centralLists = $props['central-lists'] ?? [];
		$includeList = $this->getProperty( 'include-list' );
		$excludeList = $this->getProperty( 'exclude-list' );

		$includeUserGroups = explode( '|', $this->getProperty( 'allow-usergroups', '' ) );
		$inAllowedUserGroups = array_intersect( $includeUserGroups, $props['groups'] );

		if ( $excludeList && in_array( $excludeList, $lists ) ) {
			$status->fatal( 'securepoll-in-exclude-list' );
		} elseif ( ( $includeList && in_array( $includeList, $lists ) ) ||
			$inAllowedUserGroups ) {
			// Good
		} else {
			// Edits
			$minEdits = $this->getProperty( 'min-edits' );
			$edits = $props['edit-count'] ?? 0;
			if ( $minEdits && $edits < $minEdits ) {
				$status->fatal(
					'securepoll-too-few-edits',
					$wgLang->formatNum( $minEdits ),
					$wgLang->formatNum( $edits )
				);
			}

			// Registration date
			$maxDate = $this->getProperty( 'max-registration' );
			$date = $props['registration'] ?? 0;
			if ( $maxDate && $date > $maxDate ) {
				$status->fatal(
					'securepoll-too-new',
					$wgLang->date( $maxDate ),
					$wgLang->date( $date ),
					$wgLang->time( $maxDate ),
					$wgLang->time( $date )
				);
			}

			// Blocked
			$notAllowedSitewideBlocked = $this->getProperty( 'not-sitewide-blocked' );
			$notPartialBlocked = $this->getProperty( 'not-partial-blocked' );
			$isBlocked = !empty( $props['blocked'] );
			$isSitewideBlocked = $props['isSitewideBlocked'];
			if ( $notAllowedSitewideBlocked && $isBlocked && $isSitewideBlocked ) {
				$status->fatal( 'securepoll-blocked' );
			} elseif ( $notPartialBlocked && $isBlocked && !$isSitewideBlocked ) {
				$status->fatal( 'securepoll-blocked-partial' );
			}

			// Centrally blocked on more than X projects
			$notCentrallyBlocked = $this->getProperty( 'not-centrally-blocked' );
			$centralBlockCount = $props['central-block-count'] ?? 0;
			$centralBlockThreshold = $this->getProperty( 'central-block-threshold', 1 );
			if ( $notCentrallyBlocked && $centralBlockCount >= $centralBlockThreshold ) {
				$status->fatal(
					'securepoll-blocked-centrally',
					$wgLang->formatNum( $centralBlockThreshold )
				);
			}

			// Bot
			$notBot = $this->getProperty( 'not-bot' );
			$isBot = !empty( $props['bot'] );
			if ( $notBot && $isBot ) {
				$status->fatal( 'securepoll-bot' );
			}

			// Groups
			$needGroup = $this->getProperty( 'need-group' );
			$groups = $props['groups'] ?? [];
			if ( $needGroup && !in_array( $needGroup, $groups ) ) {
				$status->fatal( 'securepoll-not-in-group', $needGroup );
			}

			// Lists
			$needList = $this->getProperty( 'need-list' );
			if ( $needList && !in_array( $needList, $lists ) ) {
				$status->fatal( 'securepoll-not-in-list' );
			}

			$needCentralList = $this->getProperty( 'need-central-list' );
			if ( $needCentralList && !in_array( $needCentralList, $centralLists ) ) {
				$status->fatal( 'securepoll-not-in-list' );
			}
		}

		// Get custom error message and add it to the status's error messages
		if ( !$status->isOK() ) {
			$errorMsgText = $this->getMessage( 'unqualified-error' );
			if ( $errorMsgText !== '[unqualified-error]' && $errorMsgText !== '' ) {
				// We create the message as a separate step so that possible wikitext in
				// $errorMsgText gets parsed.
				$errorMsg = wfMessage( 'securepoll-custom-unqualified', $errorMsgText );
				$status->error( $errorMsg );
			}
		}

		return $status;
	}

	/**
	 * Returns true if the user is an admin of the current election.
	 * @param User $user
	 * @return bool
	 */
	public function isAdmin( $user ) {
		$admins = array_map( 'trim', explode( '|', $this->getProperty( 'admins' ) ) );

		$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
		return in_array( $user->getName(), $admins )
			&& in_array( 'electionadmin', $userGroupManager->getUserEffectiveGroups( $user ) );
	}

	/**
	 * Returns true if the voter has voted already.
	 * @param Voter $voter
	 * @return bool
	 */
	public function hasVoted( $voter ) {
		$db = $this->context->getDB();
		$row = $db->newSelectQueryBuilder()
			->select( '1' )
			->from( 'securepoll_votes' )
			->where( [
				'vote_election' => $this->getId(),
				'vote_voter' => $voter->getId(),
			] )
			->caller( __METHOD__ )
			->fetchRow();

		return $row !== false;
	}

	/**
	 * Returns true if the election allows voters to change their vote after it
	 * is initially cast.
	 * @return bool
	 */
	public function allowChange() {
		return !$this->getProperty( 'disallow-change' );
	}

	/**
	 * Get the questions in this election
	 * @return Question[]
	 */
	public function getQuestions() {
		if ( $this->questions === null ) {
			$info = $this->context->getStore()->getQuestionInfo( $this->getId() );
			$this->questions = [];
			foreach ( $info as $questionInfo ) {
				$this->questions[] = $this->context->newQuestion( $questionInfo );
			}
		}

		return $this->questions;
	}

	/**
	 * Get the authorization object.
	 * @return Auth
	 */
	public function getAuth() {
		if ( !$this->auth ) {
			$this->auth = $this->context->newAuth( $this->authType );
		}

		return $this->auth;
	}

	/**
	 * Get the primary language for this election. This language will be used as
	 * a default in the relevant places.
	 * @return string
	 */
	public function getLanguage() {
		return $this->primaryLang;
	}

	/**
	 * Get the cryptography module for this election, or false if none is
	 * defined.
	 * @return Crypt|false
	 * @throws InvalidDataException
	 */
	public function getCrypt() {
		$type = $this->getProperty( 'encrypt-type', 'none' );
		try {
			return $this->context->newCrypt( $type, $this );
		} catch ( InvalidArgumentException $e ) {
			throw new InvalidDataException( 'Invalid encryption type' );
		}
	}

	/**
	 * Get the tally type
	 * @return string
	 */
	public function getTallyType() {
		return $this->tallyType;
	}

	/**
	 * Call a callback function for each valid vote record, in random order.
	 * @param callable $callback
	 * @return Status
	 */
	public function dumpVotesToCallback( $callback ) {
		$random = $this->context->getRandom();
		$status = $random->open();
		if ( !$status->isOK() ) {
			return $status;
		}
		$db = $this->context->getDB();
		$res = $db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'securepoll_votes' )
			->where( [
				'vote_election' => $this->getId(),
				'vote_current' => 1,
				'vote_struck' => 0
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		if ( $res->numRows() ) {
			$order = $random->shuffle( range( 0, $res->numRows() - 1 ) );
			foreach ( $order as $i ) {
				$res->seek( $i );
				call_user_func( $callback, $this, $res->fetchObject() );
			}
		}
		$random->close();

		return Status::newGood();
	}

	/**
	 * Get an XML snippet describing the configuration of this object
	 * @param array $params
	 * @return string
	 */
	public function getConfXml( $params = [] ) {
		$s = "<configuration>\n" . Xml::element( 'title', [], $this->title ) . "\n" . Xml::element(
				'ballot',
				[],
				$this->ballotType
			) . "\n" . Xml::element( 'tally', [], $this->tallyType ) . "\n" . Xml::element(
				'primaryLang',
				[],
				$this->primaryLang
			) . "\n" . Xml::element(
				'startDate',
				[],
				wfTimestamp( TS_ISO_8601, $this->startDate )
			) . "\n" . Xml::element(
				'endDate',
				[],
				wfTimestamp( TS_ISO_8601, $this->endDate )
			) . "\n" . $this->getConfXmlEntityStuff( $params );

		// If we're making a jump dump, we need to add some extra properties, and
		// override the auth type
		if ( !empty( $params['jump'] ) ) {
			$s .= Xml::element( 'auth', [], 'local' ) . "\n" . Xml::element(
					'property',
					[ 'name' => 'jump-url' ],
					$this->context->getSpecialTitle()->getCanonicalURL()
				) . "\n" . Xml::element(
					'property',
					[ 'name' => 'jump-id' ],
					(string)$this->getId()
				) . "\n";
		} else {
			$s .= Xml::element( 'auth', [], $this->authType ) . "\n";
		}

		foreach ( $this->getQuestions() as $question ) {
			$s .= $question->getConfXml( $params );
		}
		$s .= "</configuration>\n";

		return $s;
	}

	/**
	 * Get property names which aren't included in an XML dump
	 * @param array $params
	 * @return array
	 */
	public function getPropertyDumpExclusion( $params = [] ) {
		if ( empty( $params['private'] ) ) {
			return [
				'gpg-encrypt-key',
				'gpg-sign-key',
				'gpg-decrypt-key',
				'openssl-encrypt-key',
				'openssl-sign-key',
				'openssl-decrypt-key'
			];
		} else {
			return [];
		}
	}

	/**
	 * Tally the valid votes for this election.
	 * Returns a Status object. On success, the value property will contain a
	 * ElectionTallier object.
	 * @return Status
	 */
	public function tally() {
		$tallier = $this->context->newElectionTallier( $this );
		$status = $tallier->execute();
		if ( $status->isOK() ) {
			return Status::newGood( $tallier );
		} else {
			return $status;
		}
	}

	/**
	 * Get the stored tally results for this election. The caller can use the
	 * returned tallier to format the results in the desired way.
	 *
	 * @param IDatabase $dbr
	 * @return ElectionTallier|bool
	 */
	public function getTallyFromDb( $dbr ) {
		$result = $dbr->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $this->getId(),
				'pr_key' => 'tally-result',
			] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$result ) {
			return false;
		}

		$tallier = $this->context->newElectionTallier( $this );
		$tallier->loadJSONResult( json_decode( $result, true ) );
		return $tallier;
	}
}
