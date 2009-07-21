<?php

abstract class SecurePoll_Tallier {
	var $context, $question;

	abstract function addVote( $scores );
	abstract function getHtmlResult();
	abstract function getTextResult();

	abstract function finishTally();

	static function factory( $context, $type, $question ) {
		switch ( $type ) {
		case 'plurality':
			return new SecurePoll_PluralityTallier( $context, $question );
		case 'schulze':
			return new SecurePoll_SchulzeTallier( $context, $question );
		default:
			throw new MWException( "Invalid tallier type: $type" );
		}
	}

	function __construct( $context, $question ) {
		$this->context = $context;
		$this->question = $question;
	}
}

/**
 * Tallier that supports choose-one, approval and range voting
 */
class SecurePoll_PluralityTallier extends SecurePoll_Tallier {
	var $tally = array();

	function __construct( $context, $question ) {
		parent::__construct( $context, $question );
		foreach ( $question->getOptions() as $option ) {
			$this->tally[$option->getId()] = 0;
		}
	}

	function addVote( $scores ) {
		foreach ( $scores as $oid => $score ) {
			if ( !isset( $this->tally[$oid] ) ) {
				wfDebug( __METHOD__.": unknown OID $oid\n" );
				return false;
			}
			$this->tally[$oid] += $score;
		}
		return true;
	}

	function finishTally() {
		// Sort the scores
		arsort( $this->tally );
	}

	function getHtmlResult() {
		// Show the results
		$s = "<table class=\"securepoll-results\">\n";

		foreach ( $this->question->getOptions() as $option ) {
			$s .= '<tr><td>' . $option->getMessage( 'text' ) . "</td>\n" .
				'<td>' . $this->tally[$option->getId()] . "</td>\n" .
				"</tr>\n";
		}
		$s .= "</table>\n";
		return $s;
	}

	function getTextResult() {
		// Calculate column width
		$width = 10;
		foreach ( $this->question->getOptions() as $option ) {
			$width = max( $width, strlen( $option->getMessage( 'text' ) ) );
		}
		if ( $width > 57 ) {
			$width = 57;
		}

		// Show the results
		$qtext = $this->question->getMessage( 'text' );
		$s = '';
		if ( $qtext !== '' ) {
			$s .= wordwrap( $qtext ) . "\n";
		}
		foreach ( $this->question->getOptions() as $option ) {
			$otext = $option->getMessage( 'text' );
			if ( strlen( $otext ) > $width ) {
				$otext = substr( $otext, 0, $width - 3 ) . '...';
			} else {
				$otext = str_pad( $otext, $width );
			}
			$s .= $otext . ' | ' . 
				$this->tally[$option->getId()] . "\n";
		}
		return $s;
	}

	function getRanks() {
		$ranks = array();
		$currentRank = 1;
		$oids = array_keys( $this->tally );
		$scores = array_values( $this->tally );
		foreach ( $oids as $i => $oid ) {
			if ( $i > 0 && $scores[$i-1] !== $scores[$i] ) {
				$currentRank = $i + 1;
			}
			$ranks[$oid] = $currentRank;
		}
		return $ranks;
	}
}

abstract class SecurePoll_PairwiseTallier extends SecurePoll_Tallier {
	var $optionIds = array();
	var $victories = array();

	function __construct( $context, $question ) {
		parent::__construct( $context, $question );
		$this->optionIds = array();
		foreach ( $question->getOptions() as $option ) {
			$this->optionIds[] = $option->getId();
		}

		$this->victories = array();
		foreach ( $this->optionIds as $i ) {
			foreach ( $this->optionIds as $j ) {
				$this->victories[$i][$j] = 0;
			}
		}
	}

	function addVote( $ranks ) {
		foreach ( $this->optionIds as $oid1 ) {
			if ( !isset( $ranks[$oid1] ) ) {
				wfDebug( "Invalid vote record, missing option $oid1\n" );
				return false;
			}
			foreach ( $this->optionIds as $oid2 ) {
				# Lower = better
				if ( $ranks[$oid1] < $ranks[$oid2] ) {
					$this->victories[$oid1][$oid2]++;
				}
			}
		}
		return true;
	}
}

/**
 * This is the basic Schulze method with no tie-breaking.
 */
class SecurePoll_SchulzeTallier extends SecurePoll_PairwiseTallier {
	var $strengths;

	function finishTally() {
		# This algorithm follows Markus Schulze, "A New Monotonic, Clone-Independent, Reversal
		# Symmetric, and Condorcet-Consistent Single-Winner Election Method"
		
		$this->strengths = array();
		foreach ( $this->optionIds as $oid1 ) {
			foreach ( $this->optionIds as $oid2 ) {
				if ( $oid1 === $oid2 ) {
					continue;
				}
				if ( $this->victories[$oid1][$oid2] > $this->victories[$oid2][$oid1] ) {
					$this->strengths[$oid1][$oid2] = $this->victories[$oid1][$oid2];
				} else {
					$this->strengths[$oid1][$oid2] = 0;
				}
			}
		}

		foreach ( $this->optionIds as $oid1 ) {
			foreach ( $this->optionIds as $oid2 ) {
				if ( $oid1 === $oid2 ) {
					continue;
				}
				foreach ( $this->optionIds as $oid3 ) {
					if ( $oid1 === $oid3 || $oid2 === $oid3 ) {
						continue;
					}
					$this->strengths[$oid2][$oid3] = max(
						$this->strengths[$oid2][$oid3], 
						min(
							$this->strengths[$oid2][$oid1],
							$this->strengths[$oid1][$oid3]
						)
					);
				}
			}
		}

		# Calculate ranks
		$this->ranks = array();
		$rankedOptions = $this->optionIds;
		usort( $rankedOptions, array( $this, 'comparePair' ) );
		$rankedOptions = array_reverse( $rankedOptions );
		$currentRank = 1;
		foreach ( $rankedOptions as $i => $oid ) {
			if ( $i > 0 && $this->comparePair( $rankedOptions[$i-1], $oid ) ) {
				$currentRank = $i + 1;
			}
			$this->ranks[$oid] = $currentRank;
		}
	}

	function comparePair( $i, $j ) {
		if ( $i === $j ) {
			return 0;
		}
		$sij = $this->strengths[$i][$j];
		$sji = $this->strengths[$j][$i];
		if ( $sij > $sji ) {
			return 1;
		} elseif ( $sji > $sij ) {
			return -1;
		} else {
			return 0;
		}
	}

	function getHtmlResult() {
		return '<pre>' . $this->getTextResult() . '</pre>';
	}

	function getTextResult() {
		return 
			"Victory matrix:\n" .
			var_export( $this->victories, true ) . "\n\n" .
			"Path strength matrix:\n" .
			var_export( $this->strengths, true ) . "\n\n" .
			"Ranks:\n" .
			var_export( $this->ranks, true ) . "\n";
	}
}

