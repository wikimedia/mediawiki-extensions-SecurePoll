<?php

namespace MediaWiki\Extensions\SecurePoll\Talliers;

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Question;

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
		// TODO: Implement loadJSONResult() method.
	}

	public function getJSONResult() {
		// TODO: Implement getJSONResult() method.
		return [];
	}

	public function getHtmlResult() {
		// TODO: Implement getHtmlResult() method.
		return '';
	}

	public function getTextResult() {
		// TODO: Implement getTextResult() method.
		return '';
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
			$this->resultsLog['eliminated']
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

		foreach ( $ballots as $k => $ballot ) {
			$weight = 1;
			foreach ( $ballot['rank'] as $rank => $candidate ) {
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
		return ( $votes / ( $seats + 1 ) ) + 0.000001;
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
	 * @return array
	 */
	private function declareEliminated( $ranking, $surplus, $eliminated ) {
		// Make sure it's ordered by vote totals
		uasort(
			$ranking,
			static function ( $a, $b ) {
			if ( $a['total'] == $b['total'] ) {
				return 0;
			}
			return ( $a['total'] > $b['total'] ) ? -1 : 1;
			}
		);

		// Remove anyone who was already eliminated
		$ranking = array_filter( $ranking, static function ( $key ) use ( $eliminated ) {
			return !in_array( $key, $eliminated ) ? true : false;
		}, ARRAY_FILTER_USE_KEY );

		$voteTotals = array_unique( array_map( static function ( $candidate ) {
			return $candidate['total'];
		}, $ranking ) );

		// If everyone left is tied and no one made quota
		// Everyone left gets eliminated
		if ( count( $voteTotals ) === 1 ) {
			return array_keys( $ranking );
		}

		// Get the lowest ranking candidates
		list( $secondLowest, $lowest ) = array_slice( $voteTotals, -2 );

		// Check if we can eliminate the lowest candidate
		// using Hill's surplus-based short circuit elimination
		if ( $lowest + $surplus < $secondLowest ) {
			return array_keys( array_filter( $ranking, static function ( $ranked ) use ( $lowest ) {
				if ( $ranked['total'] === $lowest ) {
					return true;
				}
				return false;
			} ) );
		}
		return [];
	}
}
