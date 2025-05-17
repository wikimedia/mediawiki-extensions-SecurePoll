<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

/**
 * This is the basic Schulze method with no tie-breaking. The algorithm in this
 * class is believed to be equivalent to the method in the Debian constitution,
 * assuming no quorum and no default option. It has been cross-tested against
 * debian-vote (but not devotee).
 */
class SchulzeTallier extends PairwiseTallier {
	/** @var array|null */
	public $ranks;
	/** @var array|null */
	public $strengths;

	/**
	 * @param array $victories
	 * @return array
	 */
	private function getPathStrengths( $victories ) {
		# This algorithm follows Markus Schulze, "A New Monotonic, Clone-Independent, Reversal
		# Symmetric, and Condorcet-Consistent Single-Winner Election Method" and also
		# http://en.wikipedia.org/w/index.php?title=User:MarkusSchulze/Statutory_Rules&oldid=303036893
		# Path strengths in the Schulze method are given by pairs of integers notated (a, b)
		# where a is the strength in one direction and b is the strength in the other. We make
		# a matrix of path strength pairs "p", giving the path strength of the row index beating
		# the column index.

		# First the path strength matrix is populated with the "direct" victory count in each
		# direction, i.e. the number of people who preferenced A over B, and B over A.
		$strengths = [];
		foreach ( $this->optionIds as $oid1 ) {
			foreach ( $this->optionIds as $oid2 ) {
				if ( $oid1 === $oid2 ) {
					continue;
				}
				$v12 = $victories[$oid1][$oid2];
				$v21 = $victories[$oid2][$oid1];
				if ( $v12 > $v21 ) {
					# Direct victory
					$strengths[$oid1][$oid2] = [
						$v12,
						$v21
					];
				} else {
					# Direct loss
					$strengths[$oid1][$oid2] = [
						0,
						0
					];
				}
			}
		}

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

	private function convertStrengthMatrixToRanks( array $strengths ): array {
		$unusedIds = $this->optionIds;
		$ranks = [];
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
	 * @param array $s1
	 * @param array $s2
	 * @return bool
	 */
	private function isSchulzeWin( $s1, $s2 ) {
		return $s1[0] > $s2[0] || ( $s1[0] == $s2[0] && $s1[1] < $s2[1] );
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function finishTally() {
		$this->strengths = $this->getPathStrengths( $this->victories );
		$this->ranks = $this->convertStrengthMatrixToRanks( $this->strengths );
	}

	/**
	 * @return array|null
	 */
	public function getRanks() {
		return $this->ranks;
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function loadJSONResult( $data ) {
		$this->ranks = $data['ranks'];
		$this->victories = $data['victories'];
		$this->strengths = $data['strengths'];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getJSONResult() {
		return [
			'ranks' => $this->ranks,
			'victories' => $this->victories,
			'strengths' => $this->strengths,
		];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getHtmlResult() {
		$s = '<h2>' . wfMessage( 'securepoll-ranks' )->parse() . "</h2>\n";
		$s .= $this->convertRanksToHtml( $this->ranks );

		$s .= '<h2>' . wfMessage( 'securepoll-pairwise-victories' )->parse() . "</h2>\n";
		$rankedIds = array_keys( $this->ranks );
		$s .= $this->convertMatrixToHtml( $this->victories, $rankedIds );

		$s .= '<h2>' . wfMessage( 'securepoll-strength-matrix' )->parse() . "</h2>\n";
		$s .= $this->convertMatrixToHtml( $this->strengths, $rankedIds );

		return $s;
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getTextResult() {
		$rankedIds = array_keys( $this->ranks );

		return wfMessage( 'securepoll-ranks' )->text() . "\n" .
			$this->convertRanksToText( $this->ranks ) . "\n\n" .
			wfMessage( 'securepoll-pairwise-victories' )->text() . "\n" . $this->convertMatrixToText( $this->victories, $rankedIds ) . "\n\n" .
			wfMessage( 'securepoll-strength-matrix' )->text() . "\n" .
			$this->convertMatrixToText( $this->strengths, $rankedIds );
	}
}
