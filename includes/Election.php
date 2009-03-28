<?php

class SecurePoll_Election extends SecurePoll_Entity {
	var $questions, $auth, $ballot;
	var $title, $ballotType, $tallyType, $primaryLang, $startDate, $endDate, $authType;

	function __construct( $id ) {
		parent::__construct( 'election', $id );
	}

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

	function getChildren() {
		return $this->getQuestions();
	}

	function getStartDate() { return $this->startDate; }
	function getEndDate() { return $this->endDate; }

	function isStarted( $ts = false ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}
		return !$this->startDate || $ts >= $this->startDate;
	}

	function isFinished( $ts ) {
		if ( $ts === false ) {
			$ts = wfTimestampNow();
		}
		return $this->endDate && $ts < $this->endDate;
	}

	function getBallot() {
		if ( !$this->ballot ) {
			$this->ballot = SecurePoll_Ballot::factory( $this->ballotType, $this );
		}
		return $this->ballot;
	}

	function getQualifiedStatus( $user ) {
		return Status::newGood();	
	}

	function hasVoted( $user ) {
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( 
			'securepoll_votes', 
			array( "1" ),
			array( 
				'vote_election' => $this->getId(),
				'vote_user' => $user->getId(),
			),
			__METHOD__ );
		return $row !== false;
	}

	function allowChange() {
		return true;
	}

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

	function getAuth() {
		if ( !$this->auth ) {
			$this->auth = SecurePoll_Auth::factory( $this->authType );
		}
		return $this->auth;
	}

	function getLanguage() {
		return $this->primaryLang;
	}

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
}

