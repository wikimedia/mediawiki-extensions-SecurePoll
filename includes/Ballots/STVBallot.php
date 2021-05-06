<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use MediaWiki\Extensions\SecurePoll\Entities\Question;
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
		// still TBC, we might add more, or remove some.
		return [
			'hare-qouta',
			'droop-qouta'
		];
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
