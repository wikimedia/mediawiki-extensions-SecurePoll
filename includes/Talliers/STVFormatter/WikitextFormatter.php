<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

class WikitextFormatter extends HtmlFormatter {

	public function formatPreamble( array $elected, array $eliminated ) {
		// Generate overview of elected candidates
		$electionSummary = "==" .
			wfMessage( 'securepoll-stv-result-election-elected-header' ) . "==\n";

		$totalVotes = array_reduce( $this->rankedVotes, static function ( $count, $ballot ) {
			return $count + $ballot['count'];
		}, 0 );
		$electionSummary .= wfMessage( 'securepoll-stv-election-paramters' )
			->numParams(
				$this->seats,
				count( $this->candidates ),
				$totalVotes
			);

		$electedList = "";
		for ( $i = 0; $i < $this->seats; $i++ ) {
			$electedList .= "\n" . "* ";
			if ( isset( $elected[$i] ) ) {
				$currentCandidate = $elected[$i];
				$electedList .= $this->getCandidateName( $currentCandidate );
			} else {
				$electedList .= "''" .
							wfMessage( 'securepoll-stv-result-elected-list-unfilled-seat',
								implode(
									', ',
									array_map( [ $this, 'getCandidateName' ], $eliminated )
								)
							) . "''";
			}
		}
		$electionSummary .= $electedList;

		// Generate overview of eliminated candidates
		$electionSummary .= "\n" . "==" . wfMessage( 'securepoll-stv-result-election-eliminated-header' ) . "==";
		$eliminatedList = "";
		foreach ( $eliminated as $eliminatedCandidate ) {
			$eliminatedList .= "\n" . "* " . $this->getCandidateName( $eliminatedCandidate );
		}

		// If any candidates weren't eliminated from the rounds (as a result of seats filling)
		// Output them to the eliminated list after all of the round-eliminated candidates
		foreach (
			array_diff(
				array_keys( $this->candidates ),
				array_merge( $elected, $eliminated )
		) as $remainingCandidate ) {
			$eliminatedList .= "\n" . "* " . $this->getCandidateName( $remainingCandidate );
		}

		$electionSummary .= $eliminatedList;
		return $electionSummary;
	}

	public function formatRoundsPreamble(): string {
		$electionRounds = "\n" . "==" . wfMessage( 'securepoll-stv-result-election-rounds-header' ) . "==" . "\n";

		// Generate rounds table
		$table = '{| class="wikitable"' . "\n";
		// thead
		$table .= '!' .
			wfMessage( 'securepoll-stv-result-election-round-number-table-heading' )
			. "\n" . "!" .
			wfMessage( 'securepoll-stv-result-election-tally-table-heading' )
			. "\n" . "!" .
			wfMessage( 'securepoll-stv-result-election-result-table-heading' );
		return $electionRounds . $table;
	}

	public function formatRound(): string {
		// tbody
		$previouslyElected = [];
		$previouslyEliminated = [];
		$tbody = "";
		foreach ( $this->resultsLog['rounds'] as $round ) {
			$tr = "\n" . "|-";
			// Round number
			$tr .= "\n" . "|" . $round['round'];

			// Sort rankings before listing them
			uksort( $round['rankings'], static function ( $aKey, $bKey ) use ( $round ) {
				$a = $round['rankings'][$aKey];
				$b = $round['rankings'][$bKey];
				if ( $a['total'] === $b['total'] ) {
					// ascending sort
					return $aKey <=> $bKey;
				}
				// descending sort
				return $b['total'] <=> $a['total'];
			} );

			$tally = "";
			$votesTransferred = false;
			foreach ( $round['rankings'] as $currentCandidate => $rank ) {
				$content = "\n" . "*";
				// Was the candidate eliminated this round?
				$candidateEliminatedThisRound = in_array( $currentCandidate, $round['eliminated'] );
				if ( $candidateEliminatedThisRound ) {
					$content .= "<s>";
				}
				$name = $this->getCandidateName( $currentCandidate );
				$nameContent = wfMessage( 'securepoll-stv-result-candidate', $name );
				$nameContent .= ' ';
				$content .= $nameContent;
				$candidateState = "";

				// Only show candidates who haven't been eliminated by this round
				if ( in_array( $currentCandidate, $previouslyEliminated ) ) {
					continue;
				}
				$roundedVotes = round( $rank['votes'], self::DISPLAY_PRECISION );
				$roundedTotal = round( $rank['total'], self::DISPLAY_PRECISION );

				// Rounding doesn't guarantee accurate display. One value may be rounded up/down and another one
				// left as-is, resulting in a discrepency of 1E-6
				// Calculating the earned votes post-rounding simulates how earned votes are calculated by
				// the algorithm and ensures that our display shows accurate math
				$roundedEarned = round( $roundedTotal - $roundedVotes, self::DISPLAY_PRECISION );
				$formattedVotes = $this->formatForNumParams( $roundedVotes );
				$formattedTotal = $this->formatForNumParams( $roundedTotal );

				// We select the votes-gain/-votes-surplus message based on the sign of
				// $roundedEarned. However, those messages expect its absolute value.
				$formattedEarned = $this->formatForNumParams( abs( $roundedEarned ) );

				// Round 1 should just show the initial votes and is guaranteed to neither elect nor eliminate
				if ( $round['round'] === 1 ) {
					$candidateState .= wfMessage( 'securepoll-stv-result-votes-no-change' )
							->numParams( $formattedTotal );
				} elseif ( $roundedEarned > 0 ) {
					$candidateState .= wfMessage( 'securepoll-stv-result-votes-gain' )
							->numParams(
								$formattedVotes,
								$formattedEarned,
								$formattedTotal
							);
					$votesTransferred = true;
				} elseif ( $roundedEarned < 0 ) {
					$candidateState  .= wfMessage( 'securepoll-stv-result-votes-surplus' )
						->numParams(
							$formattedVotes,
							$formattedEarned,
							$formattedTotal
						);
					$votesTransferred = true;
				} else {
					$candidateState .= wfMessage( 'securepoll-stv-result-votes-no-change' )
							->numParams( $formattedTotal );
				}

				if ( in_array( $currentCandidate, $round['elected'] ) ) {
					// Mark the candidate as having been previously elected (for display purposes only).
					$previouslyElected[] = $currentCandidate;
				} elseif ( in_array( $currentCandidate, $previouslyElected ) ) {
					$formattedParams = $this->formatForNumParams( $round['keepFactors'][$currentCandidate] );
					$candidateState .= ' ' . wfMessage( 'securepoll-stv-result-round-keep-factor' )
							->numParams( $formattedParams );
				} elseif ( $candidateEliminatedThisRound ) {
					// Mark the candidate as having been previously eliminated (for display purposes only).
					$previouslyEliminated[] = $currentCandidate;
				}

				$content .= $candidateState;

				if ( $candidateEliminatedThisRound ) {
					$content .= "</s>";
				}

				$tally .= $content;
			}
			$tr .= "\n" . "|" . $tally;

			// Result
			$roundResults = "\n" . "|";

			// Quota
			$roundResults .= wfMessage( 'securepoll-stv-result-round-quota' )
				->numParams( $this->formatForNumParams( $round['quota'] ) ) . "\n";

			// Elected
			if ( count( $round['elected'] ) ) {
				$candidatesElected  = array_map( [ $this, 'getCandidateName' ], $round['elected'] );
				$formattedElected = implode( ', ', $candidatesElected );
				$roundResults .= wfMessage( 'securepoll-stv-result-round-elected', $formattedElected ) . "\n";
			}

			// Eliminated
			if ( $round['eliminated'] !== null && count( $round['eliminated'] ) > 0 ) {
				$candidatesEliminated  = array_map( [ $this, 'getCandidateName' ], $round['eliminated'] );
				$formattedEliminated = implode( ', ', $candidatesEliminated );
				$roundResults .= wfMessage( 'securepoll-stv-result-round-eliminated', $formattedEliminated );
			}

			// Votes transferred
			if ( $votesTransferred ) {
				$roundResults .= "\n" . wfMessage( 'securepoll-stv-votes-transferred' );
			}
			$tr .= $roundResults;
			$tbody .= $tr;
		}
		return $tbody;
	}
}
