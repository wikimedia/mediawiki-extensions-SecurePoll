<?php

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

	function convertScores( $scores, $options = array() ) {
		$s = '';
		foreach ( $this->election->getQuestions() as $question ) {
			$qid = $question->getId();
			if ( !isset( $scores[$qid] ) ) {
				return false;
			}
			if ( $s !== '' ) {
				$s .= '; ';
			}
			$oid = keys( $scores );
			$option = $this->election->getOption( $oid );
			$s .= $option->getMessage( 'name' );
		}
		return $s;
	}
}

