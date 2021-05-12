<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWiki\Extensions\SecurePoll\Pages\CreatePage;
use OOUI\FieldsetLayout;

/**
 * A STVBallot class,
 * Currently work in progress see T282015.
 */
class STVBallot extends Ballot {
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
		];
		return $description;
	}

	/**
	 * @param Question $question
	 * @param array $options
	 * @return FieldsetLayout
	 */
	public function getQuestionForm( $question, $options ): FieldsetLayout {
		// TODO: Implement getQuestionForm() method.
		/** @var TYPE_NAME $fieldset */
		return new FieldsetLayout();
	}

	/**
	 * @param Question $question
	 * @param BallotStatus $status
	 * @return string|null
	 */
	public function submitQuestion( $question, $status ): ?string {
		return null;
	}

	/**
	 * @param string $record
	 * @return array|bool
	 */
	public function unpackRecord( $record ) {
		// TODO: Implement unpackRecord() method.
		return [];
	}

	/**
	 * @param array $scores
	 * @param array $options
	 * @return string|array
	 */
	public function convertScores( $scores, $options = [] ) {
		// TODO: Implement convertScores() method.
		return [];
	}
}
