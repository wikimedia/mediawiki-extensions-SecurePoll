<?php

/**
 * Parent class for ballot forms. This is the UI component of a voting method.
 */
abstract class SecurePoll_Ballot {
	var $election, $context;

	/**
	 * Get a list of names of tallying methods, which may be used to produce a 
	 * result from this ballot type.
	 * @return array
	 */
	abstract function getTallyTypes();

	/**
	 * Get the HTML form segment for a single question
	 * @param $question SecurePoll_Question
	 * @return string
	 */
	abstract function getQuestionForm( $question );

	/**
	 * Called when the form is submitted. This returns a Status object which, 
	 * when successful, contains a voting record in the value member. To 
	 * preserve voter privacy, voting records should be the same length 
	 * regardless of voter choices.
	 */
	abstract function submitForm();

	/**
	 * Unpack a string record into an array format suitable for the tally type
	 */
	abstract function unpackRecord( $record );

	/**
	 * Convert a record to a string of some kind
	 */
	function convertRecord( $record, $options = array() ) {
		$scores = $this->unpackRecord( $record );
		return $this->convertScores( $scores );
	}

	/**
	 * Convert a score array to a string of some kind
	 */
	abstract function convertScores( $scores, $options = array() );

	/**
	 * Create a ballot of the given type
	 * @param $context SecurePoll_Context
	 * @param $type string
	 * @param $election SecurePoll_Election
	 */
	static function factory( $context, $type, $election ) {
		switch ( $type ) {
		case 'approval':
			return new SecurePoll_ApprovalBallot( $context, $election );
		case 'preferential':
			return new SecurePoll_PreferentialBallot( $context, $election );
		case 'choose':
			return new SecurePoll_ChooseBallot( $context, $election );
		default:
			throw new MWException( "Invalid ballot type: $type" );
		}
	}

	/**
	 * Constructor.
	 * @param $context SecurePoll_Context
	 * @param $election SecurePoll_Election
	 */
	function __construct( $context, $election ) {
		$this->context = $context;
		$this->election = $election;
	}

	/**
	 * Get the HTML for this ballot. <form> tags should not be included,
	 * they will be added by the VotePage.
	 * @return string
	 */
	function getForm() {
		global $wgParser, $wgTitle;
		$questions = $this->election->getQuestions();
		if ( $this->election->getProperty( 'shuffle-questions' ) ) {
			shuffle( $questions );
		}

		$s = '';
		foreach ( $questions as $question ) {
			$s .= "<hr/>\n" .
				$question->parseMessage( 'text' ) .
				$this->getQuestionForm( $question ) .
				"\n";
		}
		return $s;
	}
}

