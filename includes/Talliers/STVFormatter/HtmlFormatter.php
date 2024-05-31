<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;
use OOUI\Tag;

class HtmlFormatter implements STVFormatter {

	protected const DISPLAY_PRECISION = 6;

	/**
	 * Number of seats to be filled.
	 * @var int
	 */
	protected $seats;

	/**
	 * An array of results, gets populated per round
	 * holds current round, both elected and eliminated candidate and the total votes per round.
	 * @var array[]
	 */
	protected $resultsLog;

	/**
	 * An array of vote combinations keyed by underscore-delimited
	 * and ranked options. Each vote has a rank array (which allows
	 * index-1 access to each ranked option) and a count
	 * @var array
	 */
	protected $rankedVotes;

	/**
	 * An array of all candidates in the election.
	 * @var array<int, string>
	 */
	protected $candidates = [];

	/**
	 *
	 * @param array $resultLogs
	 * @param array $rankedVotes
	 * @param int $seats
	 * @param array $candidates
	 */
	public function __construct( $resultLogs, $rankedVotes, $seats, $candidates ) {
		$context = RequestContext::getMain();
		OutputPage::setupOOUI(
				strtolower( $context->getSkin()->getSkinName() ),
				$context->getLanguage()->getDir()
		);

		$this->resultsLog = $resultLogs;
		$this->seats = $seats;
		$this->rankedVotes = $rankedVotes;
		$this->candidates = $candidates;
	}

	public function formatPreamble( array $elected, array $eliminated ) {
		// Generate overview of elected candidates
		$electionSummary = new PanelLayout( [
			'expanded' => false,
			'content' => [],
		] );
		$electionSummary->appendContent(
			( new Tag( 'h2' ) )->appendContent(
				wfMessage( 'securepoll-stv-result-election-elected-header' )
			)
		);

		$totalVotes = array_reduce( $this->rankedVotes, static function ( $count, $ballot ) {
			return $count + $ballot['count'];
		}, 0 );
		$electionSummary->appendContent(
			( new Tag( 'p' ) )->appendContent(
				wfMessage( 'securepoll-stv-election-paramters' )
					->numParams(
						$this->seats,
						count( $this->candidates ),
						$totalVotes
					)
			)
		);

		$electedList = ( new Tag( 'ol' ) )->addClasses( [ 'election-summary-elected-list' ] );
		for ( $i = 0; $i < $this->seats; $i++ ) {
			if ( isset( $elected[$i] ) ) {
				$currentCandidate = $elected[$i];
				$electedList->appendContent(
					( new Tag( 'li' ) )->appendContent(
						$this->getCandidateName( $currentCandidate )
					)
				);
			} else {
				$eliminated = $this->getLastEliminated( $this->resultsLog['rounds'] );
				$formattedEliminated = implode( ', ', $eliminated );
				$electedList->appendContent(
					( new Tag( 'li' ) )->appendContent(
						( new Tag( 'i' ) )->appendContent(
							wfMessage(
								'securepoll-stv-result-elected-list-unfilled-seat',
								$formattedEliminated
							)
						)
					)
				);
			}
		}
		$electionSummary->appendContent( $electedList );

		// Generate overview of eliminated candidates
		$electionSummary->appendContent(
			( new Tag( 'h2' ) )->appendContent(
				wfMessage( 'securepoll-stv-result-election-eliminated-header' )
			)
		);
		$eliminatedList = ( new Tag( 'ul' ) );
		foreach ( $eliminated as $eliminatedCandidate ) {
			$eliminatedList->appendContent(
				( new Tag( 'li' ) )->appendContent(
					$this->getCandidateName( $eliminatedCandidate )
				)
			);
		}

		// If any candidates weren't eliminated from the rounds (as a result of seats filling)
		// Output them to the eliminated list after all of the round-eliminated candidates
		foreach (
			array_diff(
				array_keys( $this->candidates ),
				array_merge( $elected, $eliminated )
		) as $remainingCandidate ) {
			$eliminatedList->appendContent(
				( new Tag( 'li' ) )->appendContent(
					$this->getCandidateName( $remainingCandidate )
				)
			);
		}

		$electionSummary->appendContent( $eliminatedList );
		return $electionSummary;
	}

	public function formatRoundsPreamble() {
		$electionRounds = new PanelLayout( [
			'expanded' => false,
			'content' => [],
		] );

		$electionRounds->appendContent(
			( new Tag( 'h2' ) )->appendContent(
				wfMessage( 'securepoll-stv-result-election-rounds-header' )
			)
		);

		// Help text
		$electionRounds->appendContent(
			( new Tag( 'p' ) )->appendContent(
				new HtmlSnippet( wfMessage( 'securepoll-stv-help-text' )->parse() )
			)
		);
		return $electionRounds;
	}

	public function formatRound() {
		// Generate rounds table
		$table = new Tag( 'table' );
		$table->addClasses( [
			'wikitable'
		] );

		// thead
		$table->appendContent(
			( new Tag( 'thead' ) )->appendContent( ( new Tag( 'tr' ) )->appendContent(
				( new Tag( 'th' ) )->appendContent(
					wfMessage( 'securepoll-stv-result-election-round-number-table-heading' )
				),
				( new Tag( 'th' ) )->appendContent(
					wfMessage( 'securepoll-stv-result-election-tally-table-heading' )
				),
				( new Tag( 'th' ) )->appendContent(
					wfMessage( 'securepoll-stv-result-election-result-table-heading' )
				)
			) )
		);

		// tbody
		$previouslyElected = [];
		$previouslyEliminated = [];

		$tbody = new Tag( 'tbody' );
		foreach ( $this->resultsLog['rounds'] as $round ) {
			$tr = new Tag( 'tr' );

			// Round number
			$tr->appendContent(
				( new Tag( 'td' ) )->appendContent(
					$round['round']
				)
			);

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

			$tally = ( new Tag( 'ol' ) )->addClasses( [ 'round-summary-tally-list' ] );
			$votesTransferred = false;
			foreach ( $round['rankings'] as $currentCandidate => $rank ) {
				$content = $lineItem = ( new Tag( 'li' ) );

				// Was the candidate eliminated this round?
				$candidateEliminatedThisRound = in_array( $currentCandidate, $round['eliminated'] );

				if ( $candidateEliminatedThisRound ) {
					$content = new Tag( 's' );
					$lineItem->appendContent( $content );
				}

				$name = $this->getCandidateName( $currentCandidate );
				$nameContent = ( new Tag( 'span' ) )
					->appendContent( wfMessage( 'securepoll-stv-result-candidate', $name ) )
					->addClasses( [ 'round-summary-candidate-name' ] );
				$nameContent->appendContent( ' ' );

				$content->appendContent( $nameContent );

				$candidateState = ( new Tag( 'span' ) )->addClasses( [ 'round-summary-candidate-votes' ] );

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
				$contentRound = '';
				if ( $round['round'] === 1 ) {
					$contentRound = wfMessage( 'securepoll-stv-result-votes-no-change' )
						->numParams( $formattedTotal );
				} elseif ( $roundedEarned > 0 ) {
					$contentRound = wfMessage( 'securepoll-stv-result-votes-gain' )
						->numParams(
							$formattedVotes,
							$formattedEarned,
							$formattedTotal
						);
					$votesTransferred = true;
				} elseif ( $roundedEarned < 0 ) {
					$contentRound = wfMessage( 'securepoll-stv-result-votes-surplus' )
						->numParams(
							$formattedVotes,
							$formattedEarned,
							$formattedTotal
						);
					$votesTransferred = true;
				} else {
					$contentRound = wfMessage( 'securepoll-stv-result-votes-no-change' )
						->numParams( $formattedTotal );
				}
				$candidateState->appendContent( $contentRound );

				if ( in_array( $currentCandidate, $round['elected'] ) ) {
					$content->addClasses( [ 'round-candidate-elected' ] );

					// Mark the candidate as having been previously elected (for display purposes only).
					$previouslyElected[] = $currentCandidate;
				} elseif ( in_array( $currentCandidate, $previouslyElected ) ) {
					$content->addClasses( [ 'previously-elected' ] );
					$formattedParams = $this->formatForNumParams( $round['keepFactors'][$currentCandidate] );
					$candidateState
						->appendContent( ' ' )
						->appendContent(
							wfMessage( 'securepoll-stv-result-round-keep-factor' )
							->numParams( $formattedParams )
						);
				} elseif ( $candidateEliminatedThisRound ) {
					// Mark the candidate as having been previously eliminated (for display purposes only).
					$previouslyEliminated[] = $currentCandidate;
				}

				$content->appendContent( $candidateState );

				$tally->appendContent( $lineItem );
			}
			$tr->appendContent(
				( new Tag( 'td' ) )->appendContent(
					$tally
				)
			);

			// Result
			$roundResults = new Tag( 'td' );

			// Quota
			$roundResults->appendContent(
				wfMessage( 'securepoll-stv-result-round-quota' )
					->numParams( $this->formatForNumParams( $round['quota'] ) )
			);
			$roundResults->appendContent( new Tag( 'br' ) );

			// Elected
			if ( count( $round['elected'] ) ) {
				$electCandidates = array_map( [ $this, 'getCandidateName' ], $round['elected'] );
				$formattedElectCandidates = implode(
					', ',
					$electCandidates
				);
				$roundResults
					->appendContent(
						wfMessage(
							'securepoll-stv-result-round-elected',
							$formattedElectCandidates
						)
					)
					->appendContent( new Tag( 'br' ) );
			}

			// Eliminated
			if ( $round['eliminated'] !== null && count( $round['eliminated'] ) > 0 ) {
				$eliminatedCandidates = array_map( [ $this, 'getCandidateName' ], $round['eliminated'] );
				$formattedElimCandidates = implode( ', ', $eliminatedCandidates );
				$roundResults
					->appendContent(
						wfMessage(
							'securepoll-stv-result-round-eliminated',
							$formattedElimCandidates
						)
					)
					->appendContent( new Tag( 'br' ) );
			}

			// Votes transferred
			if ( $votesTransferred ) {
				$roundResults
					->appendContent( wfMessage( 'securepoll-stv-votes-transferred' ) )
					->appendContent( new Tag( 'br' ) );
			}

			$tr->appendContent(
				$roundResults
			);

			$tbody->appendContent( $tr );
			$table->appendContent( $tbody );
		}
		return $table;
	}

	/**
	 * Prep numbers in advance to round before display.
	 *
	 * There's a lot to unpack here:
	 * 1. Check if the value is an integer by transforming it into a 6-precision string
	 *    representation and ensuring it's in the format x.000000
	 * 2. If it's an integer, set it to the rounded value so that numParams will display an integer
	 *    and not a floated value like 0.000000
	 * 3. If it's not an integer, force it into the decimal representation before passing it
	 *    to numParams. Not doing this will pass the number in scientific notation (eg. 1E-6)
	 *    which has the tendency to become 0 somewhere in the number formatting pipeline
	 *
	 * @param float $n
	 * @return string|float
	 */
	protected function formatForNumParams( float $n ) {
		$formatted = number_format( $n, self::DISPLAY_PRECISION, '.', '' );
		if ( preg_match( '/\.0+$/', $formatted ) ) {
			return round( $n, self::DISPLAY_PRECISION );
		}
		return $formatted;
	}

	/**
	 * Given a candidate id, return the candidate name
	 * @param int $id
	 * @return string
	 */
	protected function getCandidateName( $id ) {
		return $this->candidates[$id];
	}

	/**
	 * Given the rounds of an election, return the last set
	 * of eliminated candidates by their candidate name
	 * @param array $rounds
	 * @return string[]
	 */
	protected function getLastEliminated( $rounds ) {
		$eliminationRounds = array_filter( $rounds, static function ( $round ) {
			return $round['eliminated'];
		} );
		if ( $eliminationRounds ) {
			$eliminated = array_pop( $eliminationRounds )['eliminated'];
			return array_map( static function ( $candidateId ) {
				return $candidateId;
			}, $eliminated );
		}
		return [];
	}
}
