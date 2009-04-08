<?php

/**
 * Parent class for ballot forms. This is the UI component of a voting method.
 */
abstract class SecurePoll_Ballot {
	var $election;

	/**
	 * Get a list of names of tallying methods, which may be used to produce a 
	 * result from this ballot type.
	 * @return array
	 */
	abstract function getTallyTypes();

	/**
	 * Get the HTML for this ballot. <form> tags should not be included,
	 * they will be added by the VotePage.
	 * @return string
	 */
	abstract function getForm();

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
	 * @param $type string
	 * @param $election SecurePoll_Election
	 */
	static function factory( $type, $election ) {
		switch ( $type ) {
		case 'approval':
			return new SecurePoll_ApprovalBallot( $election );
		case 'preferential':
			return new SecurePoll_PreferentialBallot( $election );
		case 'choose':
			return new SecurePoll_ChooseBallot( $election );
		default:
			throw new MWException( "Invalid ballot type: $type" );
		}
	}

	/**
	 * Constructor.
	 * @param $election SecurePoll_Election
	 */
	function __construct( $election ) {
		$this->election = $election;
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
	 * Get the HTML for this ballot. 
	 * @return string
	 */
	function getForm() {
		global $wgParser, $wgTitle;
		$questions = $this->election->getQuestions();
		if ( $this->election->getProperty( 'shuffle-questions' ) ) {
			shuffle( $questions );
		}

		$s = '';
		$parserOpts = new ParserOptions;

		foreach ( $questions as $question ) {
			$s .= "<hr/>\n";
			$s .= $wgParser->parse( $question->getMessage( 'text' ), $wgTitle, $parserOpts )->getText();
			$options = $question->getChildren();
			if ( $this->election->getProperty( 'shuffle-options' ) ) {
				shuffle( $options );
			}
			$name = 'securepoll_q' . $question->getId();
			foreach ( $options as $option ) {
				$optionText = $option->getMessage( 'text' );
				$optionHTML = $wgParser->parse( $optionText, $wgTitle, $parserOpts, false )->getText();
				$optionId = $option->getId();
				$radioId = "{$name}_opt{$optionId}";
				$s .= Xml::radio( $name, $optionId, false, array( 'id' => $radioId ) ) .
					'&nbsp;' .
					Xml::tags( 'label', array( 'for' => $radioId ), $optionText ) .
					"<br/>\n";
			}
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
 * TODO: this is code copied directly from BoardVote, it needs to be ported.
 */
class SecurePoll_PreferentialBallot extends SecurePoll_Ballot {
	function getTallyTypes() {
		return array( 'plurality', 'condorcet' );
	}

	function getRecord() {
		global $wgBoardCandidates;

		$record = "I prefer: ";
	  	$num_candidates = count( $wgBoardCandidates );
		$cnt = 0;
		foreach ( $this->mVotedFor as $i => $rank ) {
			$cnt++;

			$record .= $wgBoardCandidates[ $i ] . "[";
			$record .= ( $rank == '' ) ? 100 : $rank;
			$record .= "]";
			$record .= ( $cnt != $num_candidates ) ? ", " : "";
		}
		$record .= "\n";

		// Pad it out with spaces to a constant length, so that the encrypted record is secure
		$padLength = array_sum( array_map( 'strlen', $wgBoardCandidates ) ) +     $num_candidates * 8    + 20;
		//           ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^   ^^^^^^^^^^^^^^^^^^^^^^^^^^  ^^^^
		//               length of the candidate names added together         room for rank & separators   extra

		$record = str_pad( $record, $padLength );
		return $record;
	}

	function validVote() {
		foreach ( $this->mVotedFor as $rank ) {
			if ( $rank != '' ) {
				if ( !preg_match( '/^[1-9]\d?$/', $rank ) ) {
					return false;
				}
			}
		}

		return true;
	}

	function voteEntry( $index, $candidate ) {
		return "
		<tr><td align=\"right\">
		  <input type=\"text\" maxlength=\"2\" size=\"2\" name=\"candidate[{$index}]\" />
		</td><td align=\"left\">
		  $candidate
		</td></tr>";
	}

	function getForm() { }
	function submitForm() { }
	function unpackRecord( $record ) {}
}

