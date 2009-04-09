<?php

abstract class SecurePoll_Tallier {
	var $election;

	abstract function addRecord( $record );
	abstract function getResult();

	static function factory( $type, $election ) {
		switch ( $type ) {
		case 'plurality':
			return new SecurePoll_PluralityTallier( $election );
		default:
			throw new MWException( "Invalid tallier type: $type" );
		}
	}

	function __construct( $election ) {
		$this->election = $election;
	}
}

/**
 * Tallier that supports choose-one, approval and range voting
 */
class SecurePoll_PluralityTallier extends SecurePoll_Tallier {
	var $tally = array();

	function __construct( $election ) {
		parent::__construct( $election );
		$questions = $this->election->getQuestions();
		foreach ( $questions as $question ) {
			foreach ( $question->getOptions() as $option ) {
				$this->tally[$question->getId()][$option->getId()] = 0;
			}
		}
	}

	function addRecord( $record ) {
		$i = 0;
		$ballot = $this->election->getBallot();
		$scores = $ballot->unpackRecord( $record );
		if ( $scores === false ) {
			return false;
		}
		foreach ( $scores as $qid => $questionScores ) {
			if ( !isset( $this->tally[$qid] ) ) {
				wfDebug( __METHOD__.": unknown QID $qid\n" );
				return false;
			}
			foreach ( $questionScores as $oid => $score ) {
				if ( !isset( $this->tally[$qid][$oid] ) ) {
					wfDebug( __METHOD__.": unknown OID $oid\n" );
					return false;
				}
				$this->tally[$qid][$oid] += $score;
			}
		}
		return true;
	}

	function getResult() {
		global $wgOut;
		$questions = $this->election->getQuestions();
		
		// Sort the scores
		foreach ( $this->tally as &$scores ) {
			arsort( $scores );
		}

		// Show the results
		$s = '';
		foreach ( $questions as $question ) {
			if ( $s !== '' ) {
				$s .= "<hr/>\n";
			}
			$s .= $wgOut->parse( $question->getMessage( 'text' ) ) .
				'<table class="securepoll-result-table" border="1">';
			foreach ( $question->getOptions() as $option ) {
				$s .= '<tr><td>' . $option->getMessage( 'text' ) . "</td>\n" .
					'<td>' . $this->tally[$question->getId()][$option->getId()] . "</td>\n" .
					"</tr>\n";
			}
			$s .= "</table>\n";
		}
		return $s;
	}
}
