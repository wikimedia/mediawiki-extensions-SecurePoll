<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\PanelLayout;

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

	/** @inheritDoc */
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
		// The formatting to a fixed digit number here is important because
		// `page.vote.stv.js` sorts the form entries alphabetically.
		$name = 'securepoll_q' . $this->getFixedFormatValue( $question->getId() );
		$fieldset = new FieldsetLayout();
		$request = $this->getRequest();
		$this->seatsLimit = $question->getProperty( 'limit-seats' );
		$this->numberOfSeats = $question->getProperty( 'min-seats' );

		$data = [
			'questionId' => (int)$question->getId(),
			'maxSeats' => $this->seatsLimit ? $this->numberOfSeats : count( $options ),
			'selectedItems' => []
		];

		// Because the combobox -> boxmenu -> draggable pipeline doesn't pass along
		// an id (for ux reasons, passing the id would result in exposing the id to
		// the user), save an array of value => id pairs to re-associate the value with
		// the id on form submission. This will transform the user-visible values back
		// into ids the ballot knows how to process.
		$allCandidates = [];

		$allOptions = [
			[
				'data' => 0,
				'label' => $this->msg( 'securepoll-stv-droop-default-value' )->text(),
			]
		];
		foreach ( $options as $key => $option ) {
			$formattedKey = $this->getFixedFormatValue( $key );
			// Restore rankings from request if available
			$selectedVal = $request->getVal( $name . "_opt" . $formattedKey );
			if ( $selectedVal ) {
				$data[ 'selectedItems' ][] = [
					'option' => $name . "_opt" . $formattedKey,
					'itemKey' => $key
				];
			}

			$allCandidates[ $option->parseMessageInline( 'text' ) ] = $option->getId();

			// Pass the candidate's name as both the value and the label by setting it
			// as the data attribute.
			// It would be ideal to correctly use the name as the label and the option id as
			// the value but the combobox type will show the value instead of the label
			// in its textarea (eg. selecting a candidate will reveal the option id in the UI).
			// See https://stackoverflow.com/questions/42502268/label-data-mechanism-in-oo-ui-comboboxinputwidget
			$allOptions[] = [
				'data' => $option->getId(),
				'label' => $option->parseMessageInline( 'text' ),
			];
		}
		$data[ 'candidates' ] = $allCandidates;

		$numberOfOptions = count( $options );
		for ( $i = 0; $i < $numberOfOptions; $i++ ) {
			if ( $this->numberOfSeatsReached( $i ) ) {
				break;
			}
			// The formatting to a fixed digit number here is important because
			// `page.vote.stv.js` sorts the form entries alphabetically.
			$formattedKey = $this->getFixedFormatValue( $i );
			$inputId = "{$name}_opt{$formattedKey}";
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
					'classes' => [ 'securepoll-option-preferential', 'securepoll-option-stv-dropdown' ],
					'label' => $this->msg( 'securepoll-stv-droop-choice-rank', $i + 1 ),
					'errors' => isset( $this->prevErrorIds[$inputId] ) ? [
						$this->prevStatus->spGetMessageText( $inputId )
					] : null,
					'align' => 'top',
				]
			) );
		}

		$fieldset->appendContent( new PanelLayout(
			[
				'infusable' => true,
				'scrollable' => false,
				'expanded' => false,
				'data' => $data,
				'classes' => [
					'securepoll-option-preferential',
					'securepoll-option-stv-panel',
					'securepoll-option-stv-panel-outer',
				],
				'errors' => isset( $this->prevErrorIds[ $name ] ) ? [
					$this->prevStatus->spGetMessageText( $name )
				] : null,
				'align' => 'top'
			]
		) );

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
			$formattedId = $this->getFixedFormatValue( $question->getId() );
			$formattedRank = $this->getFixedFormatValue( $rank );
			$id = 'securepoll_q' . $formattedId . '_opt' . $formattedRank;
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

	/**
	 * @param Question $question
	 * @param string $choice
	 * @param int $rank
	 * @return string
	 */
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

	/**
	 * Formats to a fixed digit number (as string). This ensures consistency
	 * when sorting alphabetically and doesn't mis-sort IDs into an order
	 * like 1, 10, 2, ...
	 *
	 * @param string|int $value
	 * @return string
	 */
	private function getFixedFormatValue( $value ) {
		return sprintf( '%07d', $value );
	}
}
