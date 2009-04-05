<?php

abstract class SecurePoll_Ballot {
	var $election;

	abstract function getTallyTypes();
	abstract function getForm();
	abstract function submitForm();

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

	function __construct( $election ) {
		$this->election = $election;
	}
}

class SecurePoll_ChooseBallot extends SecurePoll_Ballot {
	function getTallyTypes() {
		return array( 'plurality' );
	}

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

	function submitForm() {
		global $wgRequest;
		$questions = $this->election->getQuestions();
		$record = '';
		foreach ( $questions as $question ) {
			$result = $wgRequest->getInt( 'securepoll_q' . $question->getId() );
			if ( !$result ) {
				return Status::newFatal( 'securepoll_unanswered_questions' );
			}
			$record .= sprintf( 'Q%08XA%08X', $question->getId(), $result );
		}
		$record .= "\n";
		return Status::newGood( $record );
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
}

