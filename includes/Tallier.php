<?php

abstract class SecurePoll_Tallier {
	var $context, $question, $optionsById;

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
		foreach ( $this->question->getOptions() as $option ) {
			$this->optionsById[$option->getId()] = $option;
		}
	}

	function convertRanksToHtml( $ranks ) {
		$s = "<table class=\"securepoll-results\">";
		$ids = array_keys( $ranks );
		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i-1] ) ? $ranks[$ids[$i-1]] : false;
			$nextRank = isset( $ids[$i+1] ) ? $ranks[$ids[$i+1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= "<tr>" .
				Xml::element( 'td', array(), $rank ) .
				Xml::element( 'td', array(), $option->parseMessage( 'text' ) ) .
				"</tr>\n";
		}
		$s .= "</table>";
		return $s;
	}

	function convertRanksToText( $ranks ) {
		$s = '';
		$ids = array_keys( $ranks );
		$colWidth = 6;
		foreach ( $this->optionsById as $option ) {
			$colWidth = max( $colWidth, $option->getMessage( 'text' ) );
		}

		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i-1] ) ? $ranks[$ids[$i-1]] : false;
			$nextRank = isset( $ids[$i+1] ) ? $ranks[$ids[$i+1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= str_pad( $rank, 6 ) . ' | ' . 
				$option->getMessage( 'text' ) . "\n";
		}
		return $s;
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

		foreach ( $this->tally as $oid => $rank ) {
			$option = $this->optionsById[$oid];
			$s .= '<tr><td>' . $option->getMessage( 'text' ) . "</td>\n" .
				'<td>' . $this->tally[$oid] . "</td>\n" .
				"</tr>\n";
		}
		$s .= "</table>\n";
		return $s;
	}

	function getTextResult() {
		// Calculate column width
		$width = 10;
		foreach ( $this->tally as $oid => $rank ) {
			$option = $this->optionsById[$oid];
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
		foreach ( $this->tally as $oid => $rank ) {
			$option = $this->optionsById[$oid];
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
	var $abbrevs;
	var $rowLabels = array();

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

	function getOptionAbbreviations() {
		if ( is_null( $this->abbrevs ) ) {
			$abbrevs = array();
			foreach ( $this->question->getOptions() as $option ) {
				$text = $option->getMessage( 'text' );
				$parts = explode( ' ', $text );
				$initials = '';
				foreach ( $parts as $part ) {
					if ( $part === '' || ctype_punct( $part[0] ) ) {
						continue;
					}
					$initials .= $part[0];
				}
				if ( isset( $abbrevs[$initials] ) ) {
					$index = 2;
					while ( isset( $abbrevs[$initials . $index] ) ) {
						$index++;
					}
					$initials .= $index;
				}
				$abbrevs[$initials] = $option->getId();
			}
			$this->abbrevs = array_flip( $abbrevs );
		}
		return $this->abbrevs;
	}

	function getRowLabels( $format = 'html' ) {
		if ( !isset( $this->rowLabels[$format] ) ) {
			$rowLabels = array();
			$abbrevs = $this->getOptionAbbreviations();
			foreach ( $this->question->getOptions() as $option ) {
				if ( $format == 'html' ) {
					$label = $option->parseMessage( 'text' );
				} else {
					$label = $option->getMessage( 'text' );
				}
				if ( $label !== $abbrevs[$option->getId()] ) {
					$label .= ' (' . $abbrevs[$option->getId()] . ')';
				}
				$rowLabels[$option->getId()] = $label;
			}
			$this->rowLabels[$format] = $rowLabels;
		}
		return $this->rowLabels[$format];
	}

	function convertMatrixToHtml( $matrix, $rankedIds ) {
		$abbrevs = $this->getOptionAbbreviations();
		$rowLabels = $this->getRowLabels( 'html' );

		$s = "<table class=\"securepoll-results\">";

		# Corner box
		$s .= "<tr>\n<th>&nbsp;</th>\n";

		# Header row
		foreach ( $rankedIds as $oid ) {
			$s .= Xml::element( 'th', array(), $abbrevs[$oid] ) . "\n";
		}
		$s .= "</tr>\n";

		foreach ( $rankedIds as $oid1 ) {
			# Header column
			$s .= "<tr>\n";
			$s .= Xml::element( 'td', array( 'class' => 'securepoll-results-row-heading' ),
				$rowLabels[$oid1] );
			# Rest of the matrix
			foreach ( $rankedIds as $oid2 ) {
				if ( isset( $matrix[$oid1][$oid2] ) ) {
					$value = $matrix[$oid1][$oid2];
				} else {
					$value = '';
				}
				if ( is_array( $value ) ) {
					$value = '(' . implode( ', ', $value ) . ')';
				}
				$s .= Xml::element( 'td', array(), $value ) . "\n";
			}
			$s .= "</tr>\n";
		}
		$s .= "</table>";
	}

	function convertMatrixToText( $matrix, $rankedIds ) {
		$abbrevs = $this->getOptionAbbreviations();
		$minWidth = 15;
		$rowLabels = $this->getRowLabels( 'text' );

		# Calculate column widths
		$colWidths = array();
		foreach ( $abbrevs as $id => $abbrev ) {
			if ( strlen( $abbrev ) < $minWidth ) {
				$colWidths[$id] = $minWidth;
			} else {
				$colWidths[$id] = strlen( $abbrev );
			}
		}
		$headerColumnWidth = $minWidth;
		foreach ( $rowLabels as $label ) {
			$headerColumnWidth = max( $headerColumnWidth, strlen( $label ) );
		}

		# Corner box
		$s = str_repeat( ' ', $headerColumnWidth ) . ' | ';

		# Header row
		foreach ( $rankedIds as $oid ) {
			$s .= str_pad( $abbrevs[$oid], $colWidths[$oid] ) . ' | ';
		}
		$s .= "\n";

		# Divider
		$s .= str_repeat( '-', $headerColumnWidth ) . '-+-';
		foreach ( $rankedIds as $oid ) {
			$s .= str_repeat( '-', $colWidths[$oid] ) . '-+-';
		}
		$s .= "\n";

		foreach ( $rankedIds as $oid1 ) {
			# Header column
			$s .= str_pad( $rowLabels[$oid1], $headerColumnWidth ) . ' | ';

			# Rest of the matrix
			foreach ( $rankedIds as $oid2 ) {
				if ( isset( $matrix[$oid1][$oid2] ) ) {
					$value = $matrix[$oid1][$oid2];
				} else {
					$value = '';
				}
				if ( is_array( $value ) ) {
					$value = '(' . implode( ', ', $value ) . ')';
				}
				$s .= str_pad( $value, $colWidths[$oid2] ) . ' | ';
			}
			$s .= "\n";
		}
		return $s;
	}

}



/**
 * A tallier which gives a tie-breaking ranking of candidates (TBRC) by 
 * selecting random preferential votes
 */
abstract class SecurePoll_RandomPrefVoteTallier {
	var $records, $random;

	function addVote( $vote ) {
		$this->records[] = $vote;
	}

	function getTBRCMatrix() {
		$tbrc = array();
		$marked = array();

		$random = $this->context->getRandom();
		$status = $random->open();
		if ( !$status->isOK() ) {
			throw new MWException( "Unable to open random device for TBRC ranking" );
		}

		# Random ballot round
		$numCands = count( $this->optionIds );
		$numPairsRanked = 0;
		$numRecordsUsed = 0;
		while ( $numRecordsUsed < count( $this->records )
			&& $numPairsRanked < $numCands * $numCands ) 
		{
			# Pick the record
			$recordIndex = $random->getInt( $numCands - $numRecordsUsed );
			$ranks = $this->records[$recordIndex];
			$numRecordsUsed++;

			# Swap it to the end
			$destIndex = $numCands - $numRecordsUsed;
			$this->records[$recordIndex] = $this->records[$destIndex];
			$this->records[$destIndex] = $ranks;

			# Apply its rankings
			foreach ( $this->optionIds as $oid1 ) {
				if ( !isset( $ranks[$oid1] ) ) {
					throw new MWException( "Invalid vote record, missing option $oid1" );
				}
				foreach ( $this->optionIds as $oid2 ) {
					if ( isset( $marked[$oid1][$oid2] ) ) {
						// Already ranked
						continue;
					}

					if ( $oid1 == $oid2 ) {
						# Same candidate, no win
						$tbrc[$oid1][$oid2] = false;
					} elseif ( $ranks[$oid1] < $ranks[$oid2] ) {
						# oid1 win
						$tbrc[$oid1][$oid2] = true;
					} elseif ( $ranks[$oid2] < $ranks[$oid1] ) {
						# oid2 win
						$tbrc[$oid1][$oid2] = false;
					} else {
						# Tie, don't mark
						continue;
					}
					$marked[$oid1][$oid2] = true;
					$numPairsRanked++;
				}
			}
		}

		# Random win round
		if ( $numPairsRanked < $numCands * $numCands ) {
			$randomRanks = $random->shuffle( $this->optionIds );
			foreach ( $randomRanks as $oidWin ) {
				if ( $numPairsRanked >= $numCands * $numCands ) {
					# Done
					break;
				}
				foreach ( $this->optionIds as $oidOther ) {
					if ( !isset( $marked[$oidWin][$oidOther] ) ) {
						$tbrc[$oidWin][$oidOther] = true;
						$marked[$oidWin][$oidOther] = true;
						$numPairsRanked++;
					}
					if ( !isset( $marked[$oidOther][$oidWin] ) ) {
						$tbrc[$oidOther][$oidWin] = false;
						$marked[$oidOther][$oidWin] = true;
						$numPairsRanked++;
					}
				}
			}
		}

		return $tbrc;
	}
}

/**
 * This is the basic Schulze method with no tie-breaking.
 */
class SecurePoll_SchulzeTallier extends SecurePoll_PairwiseTallier {
	function getPathStrengths( $victories ) {
		# This algorithm follows Markus Schulze, "A New Monotonic, Clone-Independent, Reversal
		# Symmetric, and Condorcet-Consistent Single-Winner Election Method" and also 
		# http://en.wikipedia.org/w/index.php?title=User:MarkusSchulze/Statutory_Rules&oldid=303036893
		#
		# Path strengths in the Schulze method are given by pairs of integers notated (a, b)
		# where a is the strength in one direction and b is the strength in the other. We make 
		# a matrix of path strength pairs "p", giving the path strength of the row index beating
		# the column index.

		# First the path strength matrix is populated with the "direct" victory count in each
		# direction, i.e. the number of people who preferenced A over B, and B over A.
		$strengths = array();
		foreach ( $this->optionIds as $oid1 ) {
			foreach ( $this->optionIds as $oid2 ) {
				if ( $oid1 === $oid2 ) {
					continue;
				}
				$v12 = $victories[$oid1][$oid2];
				$v21 = $victories[$oid2][$oid1];
				#if ( $v12 > $v21 ) {
					# Direct victory
					$strengths[$oid1][$oid2] = array( $v12, $v21 );
				#} else {
					# Direct loss
				#	$strengths[$oid1][$oid2] = array( 0, 0 );
				#}
			}
		}

		echo $this->convertMatrixToText( $strengths, $this->optionIds ) . "\n";

		# Next (continuing the Floyd-Warshall algorithm) we calculate the strongest indirect
		# paths. This part dominates the O(N^3) time order.
		foreach ( $this->optionIds as $oid1 ) {
			foreach ( $this->optionIds as $oid2 ) {
				if ( $oid1 === $oid2 ) {
					continue;
				}
				foreach ( $this->optionIds as $oid3 ) {
					if ( $oid1 === $oid3 || $oid2 === $oid3 ) {
						continue;
					}
					$s21 = $strengths[$oid2][$oid1];
					$s13 = $strengths[$oid1][$oid3];
					$s23 = $strengths[$oid2][$oid3];
					if ( $this->isSchulzeWin( $s21, $s13 ) ) {
						$temp = $s13;
					} else {
						$temp = $s21;
					}
					if ( $this->isSchulzeWin( $temp, $s23 ) ) {
						$strengths[$oid2][$oid3] = $temp;
					}
				}
			}
		}

		return $strengths;
	}

	function convertStrengthMatrixToRanks( $strengths ) {
		$unusedIds = $this->optionIds;
		$ranks = array();
		$currentRank = 1;

		while ( count( $unusedIds ) ) {
			$winners = array_flip( $unusedIds );
			foreach ( $unusedIds as $oid1 ) {
				foreach ( $unusedIds as $oid2 ) {
					if ( $oid1 == $oid2 ) {
						continue;
					}
					$s12 = $strengths[$oid1][$oid2];
					$s21 = $strengths[$oid2][$oid1];
					if ( $this->isSchulzeWin( $s21, $s12 ) ) {
						# oid1 is defeated by someone, not a winner
						unset( $winners[$oid1] );
						break;
					}
				}
			}
			if ( !count( $winners ) ) {
				# No winners, everyone ties for this position
				$winners = array_flip( $unusedIds );
			}

			# Now $winners is the list of candidates that tie for this position
			foreach ( $winners as $oid => $unused ) {
				$ranks[$oid] = $currentRank;
			}
			$currentRank += count( $winners );
			$unusedIds = array_diff( $unusedIds, array_keys( $winners ) );
		}
		return $ranks;
	}

	/**
	 * Determine whether Schulze's win relation "s1 >win s2" for path strength 
	 * pairs s1 and s2 is satisfied. 
	 *
	 * When applied to final path strengths instead of intermediate results, 
	 * the paper notates this relation >D (greater than subscript D).
	 *
	 * The inequality in the second part is reversed because the first part 
	 * refers to wins, and the second part refers to losses.
	 */
	function isSchulzeWin( $s1, $s2 ) {
		return $s1[0] > $s2[0] || ( $s1[0] == $s2[0] && $s1[1] < $s2[1] );
	}

	function finishTally() {
		$this->strengths = $this->getPathStrengths( $this->victories );
		$this->ranks = $this->convertStrengthMatrixToRanks( $this->strengths );
	}

	function getRanks() {
		return $this->ranks;
	}

	function getHtmlResult() {
		global $wgOut;
		$s = $wgOut->parse( '<h2>' . wfMsgNoTrans( 'securepoll-ranks' ) . "</h2>\n" );
		$s .= $this->convertRanksToHtml( $this->ranks );

		$s = $wgOut->parse( '<h2>' . wfMsgNoTrans( 'securepoll-pairwise-victories' ) . "</h2>\n" );
		$rankedIds = array_keys( $this->ranks );
		$s .= $this->convertMatrixToHtml( $this->victories, $rankedIds );

		$s .= $wgOut->parse( '<h2>' . wfMsgNoTrans( 'securepoll-strength-matrix' ) . "</h2>\n" );
		$s .= $this->convertMatrixToHtml( $this->strengths, $rankedIds );
		return $s;
	}

	function getTextResult() {
		$rankedIds = array_keys( $this->ranks );

		return
			wfMsg( 'securepoll-ranks' ) . "\n" .
			$this->convertRanksToText( $this->ranks ) . "\n\n" .
			wfMsg( 'securepoll-pairwise-victories' ). "\n" .
			$this->convertMatrixToText( $this->victories, $rankedIds ) . "\n\n" .
			wfMsg( 'securepoll-strength-matrix' ) . "\n" .
			$this->convertMatrixToText( $this->strengths, $rankedIds );
	}
}
