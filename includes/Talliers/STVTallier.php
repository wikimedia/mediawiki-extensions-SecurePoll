<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\ResourceLoader\OOUIModule;
use OOUI\Element;
use OOUI\Tag;
use OOUI\Theme;
use RequestContext;

/**
 * A STVTallier class,
 * This implementation is based mainly on
 * https://web.archive.org/web/20210225045400/https://prfound.org/resources/reference/reference-meek-rule/
 * and
 * Hill's programmatic implementation - We follow NZ implementation found in this paper
 * https://web.archive.org/web/20210723092928/https://ucid.es/system/files/meekm_0.pdf
 * Major differences being
 * 1. We do not use pseudo random elimination of candidates.
 *
 * Other sources
 * https://web.archive.org/web/20210723092923/https://rangevoting.org/MeekSTV2.html
 * https://web.archive.org/web/20200712142326/http://www.votingmatters.org.uk/ISSUE1/P1.HTM
 */
class STVTallier extends Tallier {
	/**
	 * The precision with which to render numbers in `::getHTMLResult()` and `::getTextResult()`.
	 *
	 * @var int
	 */
	private const DISPLAY_PRECISION = 6;

	/**
	 * An array of vote combinations keyed by underscore-delimited
	 * and ranked options. Each vote has a rank array (which allows
	 * index-1 access to each ranked option) and a count
	 * @var array
	 */
	public $rankedVotes = [];

	/**
	 * An array of results, gets populated per round
	 * holds current round, both elected and eliminated candidate and the total votes per round.
	 * @var array[]
	 */
	public $resultsLog = [
		'elected' => [],
		'eliminated' => [],
		'rounds' => []
	];

	/**
	 * Number of seats to be filled.
	 * @var int
	 */
	private $seats;

	/**
	 * An array of all candidates in the election.
	 * @var array<int, string>
	 */
	private $candidates = [];

	/**
	 * @param Context $context
	 * @param ElectionTallier $electionTallier
	 * @param Question $question
	 */
	public function __construct( $context, $electionTallier, $question ) {
		parent::__construct( $context, $electionTallier, $question );
		foreach ( $question->getOptions() as $option ) {
			$this->candidates[ $option->getId() ] = $option->getMessage( 'text' );
		}
		$this->seats = $question->getProperty( 'min-seats' );
	}

	/**
	 * @inheritDoc
	 */
	public function addVote( $scores ) {
		$id = implode( '_', $scores );
		$rank = [];
		foreach ( $scores as $ranked => $optionId ) {
			$rank[ $ranked + 1 ] = $optionId;
		}

		if ( !isset( $this->rankedVotes[ $id ] ) ) {
			$this->rankedVotes[ $id ] = [
				'count' => 1,
				'rank' => $rank,
			];
		} else {
			$this->rankedVotes[ $id ][ 'count' ] += 1;
		}

		return true;
	}

	public function loadJSONResult( array $data ) {
		$this->resultsLog = $data['resultsLog'];
		$this->rankedVotes = $data['rankedVotes'];
	}

	public function getJSONResult() {
		return [
			'resultsLog' => $this->resultsLog,
			'rankedVotes' => $this->rankedVotes,
		];
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
	private function formatForNumParams( float $n ) {
		$formatted = number_format( $n, self::DISPLAY_PRECISION, '.', '' );

		if ( preg_match( '/\.0+$/', $formatted ) ) {
			return round( $n, self::DISPLAY_PRECISION );
		}

		return $formatted;
	}

	public function getHtmlResult() {
		// Generate overview of elected candidates
		$electionSummary = new \OOUI\PanelLayout( [
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
			if ( isset( $this->resultsLog['elected'][$i] ) ) {
				$currentCandidate = $this->resultsLog['elected'][$i];
				$electedList->appendContent(
					( new Tag( 'li' ) )->appendContent(
						$this->getCandidateName( $currentCandidate )
					)
				);
			} else {
				$electedList->appendContent(
					( new Tag( 'li' ) )->appendContent(
						( new Tag( 'i' ) )->appendContent(
							wfMessage(
								'securepoll-stv-result-elected-list-unfilled-seat',
								implode(
									wfMessage( 'comma-separator' ),
									$this->getLastEliminated( $this->resultsLog['rounds'] )
								)
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
		foreach ( $this->resultsLog['eliminated'] as $eliminatedCandidate ) {
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
				array_merge( $this->resultsLog['elected'], $this->resultsLog['eliminated'] )
		) as $remainingCandidate ) {
			$eliminatedList->appendContent(
				( new Tag( 'li' ) )->appendContent(
					$this->getCandidateName( $remainingCandidate )
				)
			);
		}

		$electionSummary->appendContent( $eliminatedList );

		$electionRounds = new \OOUI\PanelLayout( [
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
				new \OOUI\HtmlSnippet( wfMessage( 'securepoll-stv-help-text' )->parse() )
			)
		);

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
				if ( !in_array( $currentCandidate, $previouslyEliminated ) ) {
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
						$candidateState->appendContent(
							wfMessage( 'securepoll-stv-result-votes-no-change' )
								->numParams( $formattedTotal )
						);
					} elseif ( $roundedEarned > 0 ) {
						$candidateState->appendContent(
							wfMessage( 'securepoll-stv-result-votes-gain' )
								->numParams(
									$formattedVotes,
									$formattedEarned,
									$formattedTotal
								)
						);
						$votesTransferred = true;
					} elseif ( $roundedEarned < 0 ) {
						$candidateState->appendContent(
						wfMessage( 'securepoll-stv-result-votes-surplus' )
							->numParams(
								$formattedVotes,
								$formattedEarned,
								$formattedTotal
							)
						);
						$votesTransferred = true;
					} else {
						$candidateState->appendContent(
							wfMessage( 'securepoll-stv-result-votes-no-change' )
								->numParams( $formattedTotal )
						);
					}

					if ( in_array( $currentCandidate, $round['elected'] ) ) {
						$content->addClasses( [ 'round-candidate-elected' ] );

						// Mark the candidate as having been previously elected (for display purposes only).
						$previouslyElected[] = $currentCandidate;
					} elseif ( in_array( $currentCandidate, $previouslyElected ) ) {
						$content->addClasses( [ 'previously-elected' ] );
						$candidateState
							->appendContent( ' ' )
							->appendContent(
								wfMessage( 'securepoll-stv-result-round-keep-factor' )
								->numParams( $this->formatForNumParams( $round['keepFactors'][$currentCandidate] ) )
							);
					} elseif ( $candidateEliminatedThisRound ) {
						// Mark the candidate as having been previously eliminated (for display purposes only).
						$previouslyEliminated[] = $currentCandidate;
					}

					$content->appendContent( $candidateState );

					$tally->appendContent( $lineItem );
				}
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
				$roundResults
					->appendContent(
						wfMessage(
							'securepoll-stv-result-round-elected',
							implode(
								wfMessage( 'comma-separator' ),
								array_map( [ $this, 'getCandidateName' ], $round['elected'] )
							)
						)
					)
					->appendContent( new Tag( 'br' ) );
			}

			// Eliminated
			if ( count( $round['eliminated'] ) ) {
				$roundResults
					->appendContent(
						wfMessage(
							'securepoll-stv-result-round-eliminated',
							implode(
								wfMessage( 'comma-separator' ),
								array_map( [ $this, 'getCandidateName' ], $round['eliminated'] )
							)
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
		}
		$table->appendContent( $tbody );

		$electionRounds->appendContent( $table );
		return new \OOUI\StackLayout( [
			'items' => [
				$electionSummary,
				$electionRounds,
			],
			'continuous' => true,
			'expanded' => false,
			'classes' => [ 'election-tally-results--stv' ]
		] );
	}

	public function getTextResult() {
		// Lifted from OutputPage->setupOOUI()
		// Since this is only output from the cli, we have to
		// manually set up OOUI before it will return a markup
		$skinName = strtolower( RequestContext::getMain()->getSkin()->getSkinName() );
		$dir = RequestContext::getMain()->getLanguage()->getDir();
		// @phan-suppress-next-line PhanCompatibleAccessMethodOnTraitDefinition XXX FIXME
		$themes = OOUIModule::getSkinThemeMap();
		$theme = $themes[$skinName] ?? $themes['default'];
		// For example, 'OOUI\WikimediaUITheme'.
		$themeClass = "OOUI\\{$theme}Theme";
		Theme::setSingleton( new $themeClass() );
		Element::setDefaultDir( $dir );
		return $this->getHtmlResult();
	}

	/**
	 * @inheritDoc
	 */
	public function finishTally() {
		// Instantiate first round for the iterator
		$keepFactors = array_fill_keys( array_keys( $this->candidates ), 1 );
		$voteCount = $this->distributeVotes( $this->rankedVotes, $keepFactors );
		$quota = $this->calculateDroopQuota( $voteCount['totalVotes'], $this->seats );
		$round = [
			'round' => 1,
			'surplus' => 0,
			'rankings' => $voteCount['ranking'],
			'totalVotes' => $voteCount['totalVotes'],
			'keepFactors' => $keepFactors,
			'quota' => $quota,
			'elected' => [],
			'eliminated' => []
		];
		$this->resultsLog['rounds'][] = $round;

		// Iterate
		$this->calculateRound( $round );
	}

	/**
	 * @param array $prevRound
	 */
	private function calculateRound( $prevRound ) {
		if ( count( $this->resultsLog['elected'] ) >= $this->seats ) {
			return;
		}

		// Advance the round
		$round = [
			'round' => $prevRound['round'] + 1,
			'elected' => [],
			'eliminated' => [],
			'surplus' => 0,
		];

		$keepFactors =
			$round['keepFactors'] =
			$this->calculateKeepFactors(
				$this->candidates,
				$prevRound['quota'],
				$prevRound['keepFactors'],
				$this->resultsLog['elected'],
				$this->resultsLog['eliminated'],
				$prevRound['rankings']
			);

		$voteCount = $this->distributeVotes( $this->rankedVotes, $keepFactors, $prevRound['rankings'] );
		$round['quota'] = $this->calculateDroopQuota( $voteCount['totalVotes'], $this->seats );
		$round['rankings'] = $voteCount['ranking'];
		$round['totalVotes'] = $voteCount['totalVotes'];

		// Check for unique winners
		$allWinners = $this->declareWinners( $round['rankings'], $round['quota'] );
		$roundWinners = $round['elected'] = array_diff( $allWinners, $this->resultsLog['elected'] );

		// If no winners, check for eliminated (no distribution happens here)
		$round['surplus'] = $this->calculateSurplus( $round['rankings'], $allWinners, $round['quota'] );
		$allEliminated = $this->declareEliminated(
			$round['rankings'],
			$round['surplus'],
			$this->resultsLog['eliminated'],
			$this->resultsLog['elected'],
			$prevRound['surplus']
		);
		$roundEliminated = $round['eliminated'] = !$roundWinners ?
			array_diff( $allEliminated, $this->resultsLog['eliminated'] ) : [];

		$this->resultsLog['rounds'][] = $round;
		$this->resultsLog['elected'] = array_merge( $this->resultsLog['elected'], $roundWinners );
		$this->resultsLog['eliminated'] = array_merge( $this->resultsLog['eliminated'], $roundEliminated );

		// Recurse?
		$hopefuls = array_diff(
			array_keys( $this->candidates ),
			array_merge( $this->resultsLog['elected'], $this->resultsLog['eliminated'] )
		);
		if ( $hopefuls ) {
			$this->calculateRound( $round );
		}
	}

	/**
	 * Distribute votes per ballot
	 * For each ballot: set ballot weight($weight) to 1,
	 * and then for each candidate,
	 * in order of rank on that ballot:
	 * add $weight multiplied by the keep factor ($voteValue)  of the candidate ($keepFactors[$candidate])
	 * to that candidate’s vote $voteTotals[$candidate]['total'], and reduce $weight by $voteValue,
	 * until no further candidate remains on the ballot.
	 * @param array $ballots
	 * @param array $keepFactors
	 * @param null $prevDistribution
	 * @return array
	 */
	private function distributeVotes( $ballots, $keepFactors, $prevDistribution = null ): array {
		$voteTotals = [];
		$totalVotes = 0;
		foreach ( $keepFactors as $candidate => $kf ) {
			$voteTotals[$candidate] = [
				'votes' => 0,
				'earned' => 0,
				'total' => 0,
			];
		}

		// Apply previous round's votes to the count to get "earned" votes
		if ( $prevDistribution ) {
			foreach ( $prevDistribution as $candidate => $candidateVotes ) {
				$voteTotals[$candidate]['votes'] = $candidateVotes['total'];
				$voteTotals[$candidate]['earned'] -= $candidateVotes['total'];
			}

		}

		foreach ( $ballots as $ballot ) {
			$weight = 1;
			foreach ( $ballot['rank'] as $candidate ) {
				if ( $weight > 0 ) {
					$voteValue = $weight * $keepFactors[$candidate];
					$voteTotals[$candidate]['earned'] += ( $voteValue * $ballot['count'] );
					$voteTotals[$candidate]['total'] += ( $voteValue * $ballot['count'] );
					$weight -= $voteValue;
					$totalVotes += ( $voteValue * $ballot['count'] );
				} else {
					break;
				}
			}

		}

		return [
			'ranking' => $voteTotals,
			'totalVotes' => $totalVotes,
		];
	}

	/**
	 * @param int|float $votes
	 * @param int $seats
	 * @return float
	 */
	private function calculateDroopQuota( $votes, $seats ): float {
		return ( $votes / ( (float)$seats + 1 ) ) + 0.000001;
	}

	/**
	 * Calculates keep factors of all elected candidate at every round
	 * calculated as: current keepfactor multiplied by current quota divided by candidates current vote($voteTotals)
	 * @param array $candidates
	 * @param float $quota
	 * @param array $currentFactors
	 * @param array $winners
	 * @param array $eliminated
	 * @param array $voteTotals
	 * @return array
	 */
	private function calculateKeepFactors( $candidates, $quota, $currentFactors, $winners, $eliminated, $voteTotals ) {
		$keepFactors = [];
		foreach ( $candidates as $candidateId => $candidateName ) {
			$keepFactors[$candidateId] = in_array( $candidateId, $eliminated ) ? 0 : 1;
		}

		foreach ( $winners as $winner ) {
			$voteCount = $voteTotals[$winner]['total'];
			$prevKeepFactor = $currentFactors[$winner];
			$keepFactors[$winner] = ( $prevKeepFactor * $quota ) / $voteCount;
		}
		return $keepFactors;
	}

	/**
	 * @param array $ranking
	 * @param array $winners
	 * @param float $quota
	 * @return int|float
	 */
	private function calculateSurplus( $ranking, $winners, $quota ) {
		$voteSurplus = 0;
		foreach ( $winners as $winner ) {
			$voteTotal = $ranking[$winner]['total'];
			$voteSurplus += $voteTotal - $quota;
		}
		return $voteSurplus;
	}

	/**
	 * @param array $ranking
	 * @param float $quota
	 * @return array
	 */
	private function declareWinners( $ranking, $quota ) {
		$winners = [];
		foreach ( $ranking as $option => $votes ) {
			if ( $votes['total'] >= $quota ) {
				$winners[] = $option;
			}
		}
		return $winners;
	}

	/**
	 * @param array $ranking
	 * @param int|float $surplus
	 * @param array $eliminated
	 * @param array $elected
	 * @param int|float $prevSurplus
	 * @return array
	 */
	private function declareEliminated( $ranking, $surplus, $eliminated, $elected, $prevSurplus ) {
		// Make sure it's ordered by vote totals
		uksort(
			$ranking,
			static function ( $aKey, $bKey ) use ( $ranking ) {
				$a = $ranking[$aKey];
				$b = $ranking[$bKey];
				if ( $a['total'] === $b['total'] ) {
					// ascending sort
					return $aKey <=> $bKey;
				}
				// descending sort
				return $b['total'] <=> $a['total'];
			}
		);

		// Remove anyone who was already eliminated or elected
		$ranking = array_filter( $ranking, static function ( $key ) use ( $eliminated ) {
			return !in_array( $key, $eliminated );
		}, ARRAY_FILTER_USE_KEY );

		// Manually implement array_unique with higher precision than the default function
		$voteTotals = [];
		foreach ( $ranking as $candidate ) {
			// Since it's already been ordered by vote totals, we only need to check the
			// difference against the last recorded unique value
			if ( count( $voteTotals ) === 0 ) {
				// First value is always unique
				$voteTotals[] = $candidate['total'];
			} elseif ( isset( $voteTotals[ count( $voteTotals ) - 1 ] ) ) {
				if ( abs( $candidate['total'] - $voteTotals[ count( $voteTotals ) - 1 ] ) > PHP_FLOAT_EPSILON ) {
					$voteTotals[] = $candidate['total'];
				}
			}
		}

		// If everyone left is tied and no one made quota
		// Everyone left gets eliminated
		if ( count( $voteTotals ) === 1 ) {
			return array_keys( $ranking );
		}

		// Get the lowest ranking candidates
		[ $secondLowest, $lowest ] = array_slice( $voteTotals, -2 );

		// Check if we can eliminate the lowest candidate
		// using Hill's surplus-based short circuit elimination
		$lastPlace = array_keys( array_filter( $ranking, static function ( $ranked ) use ( $lowest, $elected ) {
			return abs( $ranked['total'] - $lowest ) < PHP_FLOAT_EPSILON && !in_array( key( $ranked ), $elected );
		} ) );
		if ( ( $lowest * count( $lastPlace ) ) + $surplus < $secondLowest ||
			abs( $surplus - $prevSurplus ) < PHP_FLOAT_EPSILON ) {
			return $lastPlace;
		}
		return [];
	}

	/**
	 * Given a candidate id, return the candidate name
	 * @param int $id
	 * @return string
	 */
	private function getCandidateName( $id ) {
		return $this->candidates[$id];
	}

	/**
	 * Given the rounds of an election, return the last set
	 * of eliminated candidates by their candidate name
	 * @param array $rounds
	 * @return string[]
	 */
	private function getLastEliminated( $rounds ) {
		$eliminationRounds = array_filter( $rounds, static function ( $round ) {
			return $round['eliminated'];
		} );
		if ( $eliminationRounds ) {
			$eliminated = array_pop( $eliminationRounds )['eliminated'];
			return array_map( function ( $candidateId ) {
				return $this->getCandidateName( $candidateId );
			}, $eliminated );
		}
		return [];
	}
}
