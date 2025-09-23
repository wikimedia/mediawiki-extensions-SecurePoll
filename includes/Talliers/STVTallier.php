<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\DumpElection;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\HtmlFormatter;
use MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter;
use OOUI\PanelLayout;
use OOUI\StackLayout;
use RuntimeException;

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
	 * An array noting that modifiers were applied to this caldulation.
	 * STV supports:
	 *   1. pre-eliminating candidates
	 * @var array[]
	 */
	public $modifiers = [];

	/**
	 * The precision for all fixed-point arithmetic done while tallying. All
	 * arithmetic done in this class must use bc math function paired with this
	 * precision value (T361077).
	 */
	public const PRECISION = 10;

	/**
	 * Number of seats to be filled.
	 * @var int
	 */
	private $seats;

	/**
	 * An array of eligible candidates in the election.
	 * @var array<int, string>
	 */
	private $candidates = [];

	/**
	 * An array of all candidates in the election (including manually excluded).
	 * @var array<int, string>
	 */
	private $allCandidates = [];

	/**
	 * blt representation of the votes; see https://svn.apache.org/repos/asf/steve/trunk/stv_background/meekm.pdf
	 * @var string
	 */
	public $blt = '';

	/**
	 * @param Context $context
	 * @param ElectionTallier $electionTallier
	 * @param Question $question
	 */
	public function __construct( $context, $electionTallier, $question ) {
		// Pull the modifier data, if any, from the job details saved when the job was queued.
		// We know the data in 'tally-job-enqueued' belongs to this tally calculation because
		// 1. tallies occur serially
		// 2. tallies are controlled by a single pipeline (the jobs queue)
		// This esures that if one is in progress, no other tally can be started for the election.
		$modifiers = $electionTallier->election->getProperty( 'tally-job-enqueued', null );
		if ( $modifiers ) {
			$modifiers = unserialize( $modifiers );
		}

		$excludedCandidates = [];
		if ( isset( $modifiers['stv-candidate-excluded'] ) ) {
			foreach ( $modifiers['stv-candidate-excluded'] as $optionId => $candidateExcluded ) {
				if ( $candidateExcluded ) {
					array_push( $excludedCandidates, $optionId );
				}
			}
		}
		$this->modifiers[ 'excludedCandidates' ] = $excludedCandidates;

		foreach ( $question->getOptions() as $option ) {
			if ( !in_array( $option->getId(), $excludedCandidates ) ) {
				$this->candidates[ $option->getId() ] = $option->getMessage( 'text' );
			}
			$this->allCandidates[ $option->getId() ] = $option->getMessage( 'text' );
		}

		parent::__construct( $context, $electionTallier, $question );

		$this->seats = $question->getProperty( 'min-seats' );
	}

	/**
	 * @inheritDoc
	 */
	public function addVote( $scores ) {
		// Remove votes for pre-eliminated candidates so that they can be
		// redistributed to other preferred candidates when the tally begins.
		$candidates = $this->candidates;
		$scores = array_values(
			array_filter( $scores, static function ( $optionId ) use ( $candidates ) {
				return isset( $candidates[$optionId] );
			} )
		);

		// In the unlikely case that a vote only contains eliminated options
		// then it must be discarded.
		if ( count( $scores ) === 0 ) {
			return true;
		}

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

	/** @inheritDoc */
	public function loadJSONResult( array $data ) {
		$this->resultsLog = $data['resultsLog'];
		$this->rankedVotes = $data['rankedVotes'];
		$this->modifiers = $data['modifiers'] ?? [];
		$this->blt = $data['blt'] ?? '';
	}

	/** @inheritDoc */
	public function getJSONResult() {
		return [
			'resultsLog' => $this->resultsLog,
			'rankedVotes' => $this->rankedVotes,
			'modifiers' => $this->modifiers,
			'blt' => $this->blt,
		];
	}

	/** @inheritDoc */
	public function getHtmlResult() {
		$htmlFormatter = new HtmlFormatter(
			$this->resultsLog,
			$this->rankedVotes,
			$this->seats,
			$this->allCandidates,
			$this->modifiers,
			$this->blt
		);
		$htmlPreamble = $htmlFormatter->formatPreamble(
			$this->resultsLog['elected'],
			$this->resultsLog['eliminated'],
			$this->modifiers
		);
		$htmlRounds = $htmlFormatter->formatRoundsPreamble();
		$htmlRounds->appendContent( $htmlFormatter->formatRound() );
		$htmlBlt = $htmlFormatter->formatBlt();

		$htmlItems = [
				$htmlPreamble,
				$htmlRounds,
		];

		// Historical tallies won't have a blt calculated;
		// add it only if it's a PanelLayout
		if ( $htmlBlt instanceof PanelLayout ) {
			$htmlItems[] = $htmlBlt;
		}

		return new StackLayout( [
			'items' => $htmlItems,
			'continuous' => true,
			'expanded' => false,
			'classes' => [ 'election-tally-results--stv' ]
		] );
	}

	/** @inheritDoc */
	public function getTextResult() {
		$wikitextFormatter = new WikitextFormatter(
			$this->resultsLog,
			$this->rankedVotes,
			$this->seats,
			$this->candidates,
			$this->modifiers,
			$this->blt
		);
		$wikitext = $wikitextFormatter->formatPreamble(
			$this->resultsLog['elected'],
			$this->resultsLog['eliminated'],
			$this->modifiers
		);
		$wikitext .= $wikitextFormatter->formatRoundsPreamble();
		$wikitext .= $wikitextFormatter->formatRound();
		$wikitext .= $wikitextFormatter->formatBlt();
		return $wikitext;
	}

	/**
	 * @inheritDoc
	 * @throws RuntimeException
	 */
	public function finishTally() {
		// Generate the corresponding blt here alongside the tally, as
		// this is the most convenient access point to the decrypted votes
		$bltFormattedVotes = [];

		// Convert the votes into a format understood by the blt generator. STVTallier condenses
		// votes into counts of ballot variants but DumpElection::createBltVoteRows requires each
		// vote record be a separate entry.
		foreach ( $this->rankedVotes as $vote ) {
			for ( $i = 0; $i < $vote['count']; $i++ ) {
				// Votes start from index 1, reset to index 0 before adding
				$bltFormattedVotes[] = array_values( $vote['rank'] );
			}
		}

		// These votes need to be randomized before conversion similar to how dumps output votes
		$randomizedVotes = [];
		$random = $this->context->getRandom();
		$status = $random->open();
		if ( !$status->isOK() ) {
			throw new RuntimeException(
				"Cannot open randomizer; abort because vote order must be randomized to continue"
			);
		}
		if ( count( $bltFormattedVotes ) ) {
			$order = $random->shuffle( range( 0, count( $bltFormattedVotes ) - 1 ) );
			foreach ( $order as $i ) {
				$randomizedVotes[] = $bltFormattedVotes[$i];
			}
		}

		$this->blt = DumpElection::generateBltFromData(
			$this->election->title,
			$this->question,
			$randomizedVotes,
			$this->modifiers[ 'excludedCandidates' ]
		);

		// Instantiate first round for the iterator
		$keepFactors = array_fill_keys( array_keys( $this->candidates ), '1.0' );
		$voteCount = $this->distributeVotes( $this->rankedVotes, $keepFactors );
		$quota = $this->calculateDroopQuota( $voteCount['totalVotes'], (string)$this->seats );
		$round = [
			'round' => 1,
			'surplus' => '0',
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
			'surplus' => '0.0',
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
		$round['quota'] = $this->calculateDroopQuota( $voteCount['totalVotes'], (string)$this->seats );
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
		$totalVotes = '0.0';
		foreach ( $keepFactors as $candidate => $kf ) {
			$voteTotals[$candidate] = [
				'votes' => '0',
				'earned' => '0',
				'total' => '0',
			];
		}

		// Apply previous round's votes to the count to get "earned" votes
		if ( $prevDistribution ) {
			foreach ( $prevDistribution as $candidate => $candidateVotes ) {
				$voteTotals[$candidate]['votes'] = $candidateVotes['total'];

				// earned = earned - total
				$voteTotals[$candidate]['earned'] = bcsub(
					$voteTotals[$candidate]['earned'],
					$candidateVotes['total'],
					self::PRECISION
				);
			}
		}

		foreach ( $ballots as $ballot ) {
			$weight = '1.0';

			foreach ( $ballot['rank'] as $candidate ) {
				// weight > 0
				if ( bccomp( $weight, '0.0', self::PRECISION ) === 1 ) {
					// voteValue = weight * keepFactor
					$voteValue = bcmul( $weight, $keepFactors[$candidate], self::PRECISION );

					// earned = earned + (voteValue * ballotCount)
					$voteTotals[$candidate]['earned'] = bcadd(
						$voteTotals[$candidate]['earned'],
						bcmul( $voteValue, (string)$ballot['count'], self::PRECISION ),
						self::PRECISION
					);

					// total = total + (voteValue * ballotCount)
					$voteTotals[$candidate]['total'] = bcadd(
						$voteTotals[$candidate]['total'],
						bcmul( $voteValue, (string)$ballot['count'], self::PRECISION ),
						self::PRECISION
					);

					// weight = weight - voteValue
					$weight = bcsub( $weight, $voteValue, self::PRECISION );

					// totalVotes = totalVotes + (voteValue + ballotCount)
					$totalVotes = bcadd(
						$totalVotes,
						bcmul( $voteValue, $ballot['count'], self::PRECISION ),
						self::PRECISION
					);
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
	 * @param string $votes
	 * @param string $seats
	 * @return string
	 */
	private function calculateDroopQuota( $votes, $seats ): string {
		// droopQuota = (votes / (seats + 1)) + 0.000001
		return bcadd(
			bcdiv( $votes, bcadd( $seats, '1.0', self::PRECISION ), self::PRECISION ),
			'0.000001',
			self::PRECISION
		);
	}

	/**
	 * Calculates keep factors of all elected candidate at every round
	 * calculated as: current keepfactor multiplied by current quota divided by candidates current vote($voteTotals)
	 * @param array $candidates
	 * @param string $quota
	 * @param array $currentFactors
	 * @param array $winners
	 * @param array $eliminated
	 * @param array $voteTotals
	 * @return array
	 */
	private function calculateKeepFactors( $candidates, $quota, $currentFactors, $winners, $eliminated, $voteTotals ) {
		$keepFactors = [];

		foreach ( $candidates as $candidateId => $candidateName ) {
			$keepFactors[$candidateId] = in_array( $candidateId, $eliminated ) ? '0.0' : '1.0';
		}

		foreach ( $winners as $winner ) {
			$voteCount = $voteTotals[$winner]['total'];
			$prevKeepFactor = $currentFactors[$winner];

			// keepFactor = (prevKeepFactor * quota) / voteCount
			$keepFactors[$winner] = bcdiv(
				bcmul( $prevKeepFactor, $quota, self::PRECISION ),
				$voteCount,
				self::PRECISION
			);
		}

		return $keepFactors;
	}

	/**
	 * @param array $ranking
	 * @param array $winners
	 * @param string $quota
	 * @return string
	 */
	private function calculateSurplus( $ranking, $winners, $quota ) {
		$voteSurplus = '0.0';
		foreach ( $winners as $winner ) {
			$voteTotal = $ranking[$winner]['total'];

			// voteSurplus = (voteSurplus + voteTotal) - quota
			$voteSurplus = bcsub( bcadd( $voteSurplus, $voteTotal, self::PRECISION ), $quota, self::PRECISION );
		}
		return $voteSurplus;
	}

	/**
	 * @param array $ranking
	 * @param string $quota
	 * @return array
	 */
	private function declareWinners( $ranking, $quota ) {
		$winners = [];
		foreach ( $ranking as $option => $votes ) {
			// voteTotal >= quota
			if ( bccomp( $votes['total'], $quota, self::PRECISION ) >= 0 ) {
				   $winners[] = $option;
			}
		}
		return $winners;
	}

	/**
	 * @param array $ranking
	 * @param string $surplus
	 * @param array $eliminated
	 * @param array $elected
	 * @param string $prevSurplus
	 * @return array
	 */
	private function declareEliminated( $ranking, $surplus, $eliminated, $elected, $prevSurplus ) {
		// Make sure it's ordered by vote totals
		uksort(
			$ranking,
			static function ( $aKey, $bKey ) use ( $ranking ) {
				$a = $ranking[$aKey];
				$b = $ranking[$bKey];
				// $a['total'] === $b['total']
				if ( bccomp( $a['total'], $b['total'], self::PRECISION ) === 0 ) {
					// ascending sort ($aKey <=> $bKey)
					return bccomp( $aKey, $bKey, self::PRECISION );
				}
				// descending sort ($b['total'] <=> $a['total'])
				return bccomp( $b['total'], $a['total'], self::PRECISION );
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
				if (
					// abs(candidateTotal - voteTotal[lastSeat]) > 0
					bccomp( $this->bcabs( bcsub(
						$candidate['total'],
						$voteTotals[ count( $voteTotals ) - 1 ],
						self::PRECISION
					) ), '0.0', self::PRECISION ) === 1
				) {
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
		$bcabs = [ $this, 'bcabs' ];
		$lastPlace = array_keys( array_filter( $ranking, static function ( $ranked ) use ( $bcabs, $lowest, $elected ) {
			// abs(rankedTotal - lowest) <= 0
			return bccomp( $bcabs( bcsub( $ranked['total'], $lowest, self::PRECISION ) ), '0.0', self::PRECISION ) <= 0
				&& !in_array( key( $ranked ), $elected );
		} ) );

		if (
			// (lowest * lastPlaceCount) + surplus < secondLowest
			bccomp( bcadd( bcmul( $lowest, (string)count( $lastPlace ) ), $surplus ), $secondLowest ) === -1 ||
			// abs(surplus - prevSurplus) <= 0
			bccomp( $this->bcabs( bcsub( $surplus, $prevSurplus, self::PRECISION ) ), '0.0', self::PRECISION ) <= 0
		) {
			return $lastPlace;
		}

		return [];
	}

	/**
	 * A custom fixed-point abs function since PHP doesn't provide one.
	 *
	 * @param string $number
	 * @return string
	 */
	private function bcabs( $number ) {
		// number < 0
		if ( bccomp( $number, '0.0', self::PRECISION ) === -1 ) {
			// number * -1
			return bcmul( $number, '-1.0', self::PRECISION );
		}
		return $number;
	}
}
