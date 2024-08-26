<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;

/**
 * A STVBallot class,
 * Currently work in progress see T282015.
 */
class STVBallot extends Ballot {

	/** @var bool */
	private $seatsLimit = false;

	/** @var int */
	private $numberOfSeats = 1;

	/**
	 * Get a list of names of tallying methods, which may be used to produce a
	 * result from this ballot type.
	 * @return array
	 */
	public static function getTallyTypes(): array {
		return [
			'droop-quota'
		];
	}

	public static function getCreateDescriptors() {
		$description = parent::getCreateDescriptors();
		$description['question'] += [
			'min-seats' => [
				'label-message' => 'securepoll-create-label-seat_count',
				'type' => 'int',
				'min' => 1,
				'validation-callback' => [
					CreatePage::class,
					'checkRequired',
				],
				'SecurePoll_type' => 'property',
			],
			'limit-seats' => [
				'label-message' => 'securepoll-create-label-limit-seats_input',
				'type' => 'check',
				'hidelabel' => true,
				'SecurePoll_type' => 'property',
			],
		];
		return $description;
	}

	/**
	 * @param Question $question
	 * @param array $options
	 * @return FieldsetLayout
	 */
	public function getQuestionForm( $question, $options ): FieldsetLayout {
		$name = 'securepoll_q' . $question->getId();
		$fieldset = new FieldsetLayout();
		$request = $this->getRequest();
		$this->seatsLimit = $question->getProperty( 'limit-seats' );
		$this->numberOfSeats = $question->getProperty( 'min-seats' );

		$allOptions = [
			[
				'data' => 0,
				'label' => $this->msg( 'securepoll-stv-droop-default-value' )->text(),
			]
		];
		foreach ( $options as $option ) {
			$allOptions[] = [
				'data' => $option->getId(),
				'label' => $option->parseMessageInline( 'text' ),
			];
		}
		$numberOfOptions = count( $options );
		for ( $i = 0; $i < $numberOfOptions; $i++ ) {
			if ( $this->numberOfSeatsReached( $i ) ) {
				break;
			}
			$inputId = "{$name}_opt{$i}";
			$widget = new DropdownInputWidget( [
				'infusable' => true,
				'name' => $inputId,
				'options' => $allOptions,
				'classes' => [ 'securepoll-stvballot-option-dropdown' ],
				'value' => $request->getVal( $inputId, '0' ),
			] );
			$fieldset->appendContent( new FieldLayout(
				$widget,
				[
					'classes' => [ 'securepoll-option-preferential' ],
					'label' => $this->msg( 'securepoll-stv-droop-choice-rank', $i + 1 ),
					'errors' => isset( $this->prevErrorIds[$inputId] ) ? [
						$this->prevStatus->spGetMessageText( $inputId )
						] : null,
					'align' => 'top',
				]
			) );
		}

		return $fieldset;
	}

	/**
	 * @param Question $question
	 * @param BallotStatus $status
	 * @return string|null
	 */
	public function submitQuestion( $question, $status ): ?string {
		$ok = true;
		// Construct the ranking array
		$options = $question->getOptions();
		$rankedChoices = [];
		foreach ( $options as $rank => $option ) {
			$id = 'securepoll_q' . $question->getId() . '_opt' . $rank;
			$rankedChoices[] = $this->getRequest()->getVal( $id );
		}

		// Remove trailing blank options
		$i = count( $rankedChoices ) - 1;
		while ( $i >= 0 ) {
			if ( !$rankedChoices[$i] ) {
				array_pop( $rankedChoices );
				$i--;
			} else {
				break;
			}
		}

		// Check that at least one choice was selected
		if ( !count( $rankedChoices ) ) {
			$status->fatal( 'securepoll-stv-invalid-rank-empty' );
			$ok = false;
		}

		// Check that choices are ranked sequentially
		if ( count( array_filter( $rankedChoices ) ) !== count( $rankedChoices ) ) {
			// Get ids of empty options
			$emptyRanks = [];
			foreach ( $rankedChoices as $i => $choice ) {
				if ( $choice === '0' ) {
					$emptyRanks[] = $this->msg( 'securepoll-stv-droop-choice-rank', $i + 1 );
					$status->spFatal(
						'securepoll-stv-invalid-input-empty',
						'securepoll_q' . $question->getId() . '_opt' . $i,
						true
					);
				}
			}
			$emptyRanks = implode( ', ', $emptyRanks );
			$status->fatal( 'securepoll-stv-invalid-rank-order', $emptyRanks );
			$ok = false;
		}

		// Check that choices are unique
		$uniqueChoices = array_unique( $rankedChoices );
		if ( count( $uniqueChoices ) !== count( $rankedChoices ) ) {
			// Get ids of duplicate options
			$duplicateChoiceIds = array_keys( array_diff_assoc( $rankedChoices, $uniqueChoices ) );
			$duplicateChoices = [];
			foreach ( $duplicateChoiceIds as $id ) {
				if ( $rankedChoices[ $id ] !== '0' ) {
					$duplicateChoices[] = $this->msg(
						'securepoll-stv-droop-choice-rank', $id + 1
					);
					$status->spFatal(
						'securepoll-stv-invalid-input-duplicate',
						'securepoll_q' . $question->getId() . '_opt' . $id,
						true
					);
				}
			}
			// Check against the count to avoid edge case of only multiple empty inputs
			if ( count( $duplicateChoices ) ) {
				$duplicateChoices = implode( ', ', $duplicateChoices );
				$status->fatal( 'securepoll-stv-invalid-rank-duplicate', $duplicateChoices );
			}
			$ok = false;
		}

		if ( !$ok ) {
			return null;
		}

		// Input ok; write the record
		// Q{question id in hexadecimal, padded to 8 chars}-C{choice id in hex, padded}-R{rank in hex, padded}--
		$record = '';
		foreach ( $rankedChoices as $rank => $choice ) {
			$record .= $this->packRecord( $question, $choice, $rank );
		}

		return $record;
	}

	public function packRecord( $question, $choice, $rank ) {
		return sprintf(
			'Q%08X-C%08X-R%08X--',
			$question->getId(),
			$choice,
			$rank
		);
	}

	/**
	 * If the record is valid, return an array of optionIds in ranked order for each questionId
	 * @param string $record
	 * @return array|bool
	 */
	public function unpackRecord( $record ) {
		$ranks = [];
		$itemLength = 3 * 8 + 7;
		for ( $offset = 0, $len = strlen( $record ); $offset < $len; $offset += $itemLength ) {
			if ( !preg_match(
				'/Q([0-9A-F]{8})-C([0-9A-F]{8})-R([0-9A-F]{8})--/A',
				$record,
				$m,
				0,
				$offset
			)
			) {
				wfDebug( __METHOD__ . ": regex doesn't match\n" );

				return false;
			}
			$qid = intval( base_convert( $m[1], 16, 10 ) );
			$oid = intval( base_convert( $m[2], 16, 10 ) );
			$rank = intval( base_convert( $m[3], 16, 10 ) );
			$ranks[$qid][$rank] = $oid;
		}

		return $ranks;
	}

	/**
	 * @param array $scores
	 * @param array $options
	 * @return string|string[]|false
	 */
	public function convertScores( $scores, $options = [] ) {
		// TODO: Implement convertScores() method.
		return [];
	}

	/**
	 *
	 * @param int $count
	 * @return bool
	 */
	private function numberOfSeatsReached( $count ) {
		if ( $this->seatsLimit && $count >= $this->numberOfSeats ) {
			return true;
		}
		return false;
	}
}
