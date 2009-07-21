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

/**
 * A ballot class which asks the user to choose one answer only from the 
 * given options, for each question.
 *
 * The following election properties are used:
 *     shuffle-questions    when present and true, the questions are shown in random order
 *     shuffle-options      when present and true, the options are shown in random order
 */
class SecurePoll_ChooseBallot extends SecurePoll_Ballot {
	/**
	 * Get a list of names of tallying methods, which may be used to produce a 
	 * result from this ballot type.
	 * @return array
	 */
	function getTallyTypes() {
		return array( 'plurality' );
	}

	/**
	 * Get the HTML form segment for a single question
	 * @param $question SecurePoll_Question
	 * @return string
	 */
	function getQuestionForm( $question ) {
		$options = $question->getChildren();
		if ( $this->election->getProperty( 'shuffle-options' ) ) {
			shuffle( $options );
		}
		$name = 'securepoll_q' . $question->getId();
		$s = '';
		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();
			$radioId = "{$name}_opt{$optionId}";
			$s .= 
				'<div class="securepoll-option-choose">' .
				Xml::radio( $name, $optionId, false, array( 'id' => $radioId ) ) .
				'&nbsp;' .
				Xml::tags( 'label', array( 'for' => $radioId ), $optionHTML ) .
				"</div>\n";
		}
		return $s;
	}

	/**
	 * Called when the form is submitted. 
	 * @return Status
	 */
	function submitForm() {
		global $wgRequest;
		$questions = $this->election->getQuestions();
		$record = '';
		foreach ( $questions as $question ) {
			$result = $wgRequest->getInt( 'securepoll_q' . $question->getId() );
			if ( !$result ) {
				return Status::newFatal( 'securepoll-unanswered-questions' );
			}
			$record .= $this->packRecord( $question->getId(), $result );
		}
		$record .= "\n";
		return Status::newGood( $record );
	}

	function packRecord( $qid, $oid ) {
		return sprintf( 'Q%08XA%08X', $qid, $oid );
	}

	function unpackRecord( $record ) {
		$result = array();
		$record = trim( $record );
		for ( $offset = 0; $offset < strlen( $record ); $offset += 18 ) {
			if ( !preg_match( '/Q([0-9A-F]{8})A([0-9A-F]{8})/A', $record, $m, 0, $offset ) ) {
				wfDebug( __METHOD__.": regex doesn't match\n" );
				return false;
			}
			$qid = intval( base_convert( $m[1], 16, 10 ) );
			$oid = intval( base_convert( $m[2], 16, 10 ) );
			$result[$qid] = array( $oid => 1 );
		}
		return $result;
	}
}

/**
 * Ballot for preferential voting
 * Properties:
 *     shuffle-questions
 *     shuffle-options
 *     must-rank-all
 */
class SecurePoll_PreferentialBallot extends SecurePoll_Ballot {
	function getTallyTypes() {
		return array( 'schulze' );
	}

	function getQuestionForm( $question ) {
		global $wgRequest;
		$options = $question->getChildren();
		if ( $this->election->getProperty( 'shuffle-options' ) ) {
			shuffle( $options );
		}
		$name = 'securepoll_q' . $question->getId();
		$s = '';
		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();
			$inputId = "{$name}_opt{$optionId}";
			$oldValue = $wgRequest->getVal( $inputId, '' );
			$s .= 
				'<div class="securepoll-option-preferential">' .
				Xml::input( $inputId, '3', $oldValue, array(
					'id' => $inputId,
					'maxlength' => 3,
				) ) .
				'&nbsp;' .
				Xml::tags( 'label', array( 'for' => $inputId ), $optionHTML ) .
				'&nbsp;' .
				"</div>\n";
		}
		return $s;
	}

	function submitForm() {
		global $wgRequest;
		$questions = $this->election->getQuestions();
		$record = '';
		$status = Status::newGood();

		foreach ( $questions as $question ) {
			$options = $question->getOptions();
			foreach ( $options as $option ) {
				$id = 'securepoll_q' . $question->getId() . '_opt' . $option->getId();
				$rank = $wgRequest->getVal( $id );

				if ( is_numeric( $rank ) ) {
					if ( $rank <= 0 || $rank >= 1000 ) {
						$status->fatal( 'securepoll-invalid-rank', $id );
						continue;
					} else {
						$rank = intval( $rank );
					}
				} elseif ( strval( $rank ) === '' ) {
					if ( $this->election->getProperty( 'must-rank-all' ) ) {
						$status->fatal( 'securepoll-unranked-options', $id );
						continue;
					} else {
						$rank = 1000;
					}
				} else {
					$status->fatal( 'securepoll-invalid-rank', $id );
					continue;
				}
				$record .= sprintf( 'Q%08X-A%08X-R%08X--', 
					$question->getId(), $option->getId(), $rank );
			}
		}
		if ( $status->isOK() ) {
			$status->value = $record . "\n";
		}
		return $status;
	}

	function unpackRecord( $record ) {
		$ranks = array();
		$itemLength = 3*8 + 7;
		for ( $offset = 0; $offset < strlen( $record ); $offset += $itemLength ) {
			if ( !preg_match( '/Q([0-9A-F]{8})-A([0-9A-F]{8})-R([0-9A-F]{8})--/A', 
				$record, $m, 0, $offset ) ) 
			{
				wfDebug( __METHOD__.": regex doesn't match\n" );
				return false;
			}
			$qid = intval( base_convert( $m[1], 16, 10 ) );
			$oid = intval( base_convert( $m[2], 16, 10 ) );
			$rank = intval( base_convert( $m[3], 16, 10 ) );
			$ranks[$qid][$oid] = $rank;
		}
		return $ranks;
	}
}

