<?php

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
 *          not-blocked
 *              True if voters need to not be blocked
 *          not-bot
 *              True if voters need to not have the bot permission
 *          need-group
 *              The name of an MW group voters need to be in
 *          need-list
 *              The name of a SecurePoll list voters need to be in
 *          admins
 *              A list of admin names, pipe separated
 *          disallow-change
 *              True if a voter is not allowed to change their vote
 *          encrypt-type
 *              The encryption module name
 *      
 *      See the other module for documentation of the following.
 *
 *      RemoteMWAuth
 *          remote-mw-script-path
 *
 *      ChooseBallot
 *          shuffle-questions
 *          shuffle-options
 *
 *      GpgCrypt
 *          gpg-encrypt-key
 *          gpg-sign-key
 *          gpg-decrypt-key
 *
 *      VotePage
 *          jump-url
 *          jump-id
 *          return-url
 */
class SecurePoll_Election extends SecurePoll_Entity {
	var $questions, $auth, $ballot;
	var $title, $ballotType, $tallyType, $primaryLang, $startDate, $endDate, $authType;

	/**
	 * Constructor.
	 * @param $id integer
	 */
	function __construct( $id ) {
		parent::__construct( 'election', $id );
	}

	/**
	 * Create an object based on a DB result row.
	 * @param $row object
	 */
	static function newFromRow( $row ) {
		$election = new self( $row->el_entity );
		$election->title = $row->el_title;
		$election->ballotType = $row->el_ballot;
		$election->tallyType = $row->el_tally;
		$election->primaryLang = $row->el_primary_lang;
		$election->startDate = $row->el_start_date;
		$election->endDate = $row->el_end_date;
		$election->authType = $row->el_auth_type;
		return $election;
	}

	/**
	 * Get a list of localisable message names. See SecurePoll_Entity.
	 */
	function getMessageNames() {
		return array( 'title', 'intro', 'jump-text', 'return-text' );
	}

	/**
	 * Get a list of child entity objects. See SecurePoll_Entity.
	 */
	function getChildren() {
		return $this->getQuestions();
	}

	/**
	 * Get the start date in MW internal form.
	 */
	function getStartDate() { return $this->startDate; }

	/**
	 * Get the end date in MW internal form.
	 */
	function getEndDate() { return $this->endDate; }

	/**
	 * Returns true if the election has started.
	 * @param $ts The reference timestamp, or false for now.
	 */
	function isStarted( $ts = false ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}
		return !$this->startDate || $ts >= $this->startDate;
	}

	/**
	 * Returns true if the election has finished.
	 * @param $ts The reference timestamp, or false for now.
	 */
	function isFinished( $ts = false ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}
		return $this->endDate && $ts >= $this->endDate;
	}

	/**
	 * Get the ballot object for this election.
	 * @return SecurePoll_Ballot
	 */
	function getBallot() {
		if ( !$this->ballot ) {
			$this->ballot = SecurePoll_Ballot::factory( $this->ballotType, $this );
		}
		return $this->ballot;
	}

	/**
	 * Determine whether a voter would be qualified to vote in this election, 
	 * based on the given associative array of parameters.
	 * @param $params Associative array
	 * @return Status
	 */
	function getQualifiedStatus( $params ) {
		$props = $params['properties'];
		$status = Status::newGood();

		# Edits
		$minEdits = $this->getProperty( 'min-edits' );
		$edits = isset( $props['edit-count'] ) ? $props['edit-count'] : 0;
		if ( $minEdits && $edits < $minEdits ) {
			$status->fatal( 'securepoll-too-few-edits', $minEdits, $edits );
		}

		# Blocked
		$notBlocked = $this->getProperty( 'not-blocked' );
		$isBlocked = !empty( $props['blocked'] );
		if ( $notBlocked && $isBlocked ) {
			$status->fatal( 'securepoll-blocked' );
		}

		# Bot
		$notBot = $this->getProperty( 'not-bot' );
		$isBot = !empty( $props['bot'] );
		if ( $notBot && $isBot ) {
			$status->fatal( 'securepoll-bot' );
		}

		# Groups
		$needGroup = $this->getProperty( 'need-group' );
		$groups = isset( $props['groups'] ) ? $props['groups'] : array();
		if ( $needGroup && !in_array( $needGroup, $groups ) ) {
			$status->fatal( 'securepoll-not-in-group', $needGroup );
		}

		# Lists
		$needList = $this->getProperty( 'need-list' );
		$lists = isset( $props['lists'] ) ? $props['lists'] : array();
		if ( $needList && !in_array( $needList, $lists ) ) {
			$status->fatal( 'securepoll-not-in-list' );
		}
		return $status;
	}

	/**
	 * Returns true if the user is an admin of the current election.
	 * @param $user User
	 */
	function isAdmin( User $user ) {
		$admins = array_map( 'trim', explode( '|', $this->getProperty( 'admins' ) ) );
		return in_array( $user->getName(), $admins );
	}

	/**
	 * Returns true if the voter has voted already.
	 * @param $voter SecurePoll_Voter
	 */
	function hasVoted( $voter ) {
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow(
			'securepoll_votes',
			array( "1" ),
			array(
				'vote_election' => $this->getId(),
				'vote_voter' => $voter->getId(),
			),
			__METHOD__ );
		return $row !== false;
	}

	/**
	 * Returns true if the election allows voters to change their vote after it
	 * is initially cast.
	 * @return bool
	 */
	function allowChange() {
		return !$this->getProperty( 'disallow-change' );
	}

	/**
	 * Get the questions in this election
	 * @return array of SecurePoll_Question objects.
	 */
	function getQuestions() {
		if ( $this->questions === null ) {
			$db = wfGetDB( DB_MASTER );
			$res = $db->select(
				array( 'securepoll_questions', 'securepoll_options' ),
				'*',
				array(
					'qu_election' => $this->getId(),
					'op_question=qu_entity'
				),
				__METHOD__,
				array( 'ORDER BY' => 'qu_index, qu_entity' )
			);

			$this->questions = array();
			$options = array();
			$questionId = false;
			foreach ( $res as $row ) {
				if ( $questionId === false ) {
				} elseif ( $questionId !== $row->qu_entity ) {
					$this->questions[] = new SecurePoll_Question( $questionId, $options );
					$options = array();
				}
				$options[] = SecurePoll_Option::newFromRow( $row );
				$questionId = $row->qu_entity;
			}
			if ( $questionId !== false ) {
				$this->questions[] = new SecurePoll_Question( $questionId, $options );
			}
		}
		return $this->questions;
	}

	/**
	 * Get the authorisation object.
	 * @return SecurePoll_Auth
	 */
	function getAuth() {
		if ( !$this->auth ) {
			$this->auth = SecurePoll_Auth::factory( $this->authType );
		}
		return $this->auth;
	}

	/**
	 * Get the primary language for this election. This language will be used as
	 * a default in the relevant places.
	 * @return string
	 */
	function getLanguage() {
		return $this->primaryLang;
	}

	/**
	 * Get the cryptography module for this election, or false if none is
	 * defined.
	 * @return SecurePoll_Crypt or false
	 */
	function getCrypt() {
		$type = $this->getProperty( 'encrypt-type' );
		if ( $type === false || $type === 'none' ) {
			return false;
		}
		$crypt = SecurePoll_Crypt::factory( $type, $this );
		if ( !$crypt ) {
			throw new MWException( 'Invalid encryption type' );
		}
		return $crypt;
	}

	/**
	 * Get the tallier object
	 * @return SecurePoll_Tallier
	 */
	function getTallier() {
		$tallier = SecurePoll_Tallier::factory( $this->tallyType, $this );
		if ( !$tallier ) {
			throw new MWException( 'Invalid tally type' );
		}
		return $tallier;
	}

}

