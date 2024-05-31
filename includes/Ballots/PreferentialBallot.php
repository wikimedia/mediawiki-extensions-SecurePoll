<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Extension\SecurePoll\Entities\Question;
use OOUI\FieldsetLayout;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\NumberInputWidget;

/**
 * Ballot for preferential voting
 * Properties:
 *     shuffle-questions
 *     shuffle-options
 *     must-rank-all
 */
class PreferentialBallot extends Ballot {
	public static function getTallyTypes() {
		return [ 'schulze' ];
	}

	public static function getCreateDescriptors() {
		$ret = parent::getCreateDescriptors();
		$ret['election'] += [
			'must-rank-all' => [
				'label-message' => 'securepoll-create-label-must_rank_all',
				'type' => 'check',
				'hidelabel' => true,
				'SecurePoll_type' => 'property',
			],
		];

		return $ret;
	}

	/**
	 * @param Question $question
	 * @param array $options
	 * @return FieldsetLayout
	 */
	public function getQuestionForm( $question, $options ) {
		$name = 'securepoll_q' . $question->getId();
		$fieldset = new FieldsetLayout();
		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();
			$inputId = "{$name}_opt{$optionId}";
			$oldValue = $this->getRequest()->getVal( $inputId, '' );

			$widget = new NumberInputWidget( [
				'name' => $inputId,
				'default' => $oldValue,
				'min' => 1,
				'max' => 999,
				'required' => $this->election->getProperty( 'must-rank-all' ),
			] );

			$label = new LabelWidget( [
				'label' => new HtmlSnippet( $this->errorLocationIndicator( $inputId ) . $optionHTML ),
				'input' => $widget,
			] );

			$fieldset->appendContent( new HorizontalLayout(
				[
					'classes' => [ 'securepoll-option-preferential' ],
					'items' => [
						$widget,
						$label,
					],
				]
			) );
		}

		return $fieldset;
	}

	/**
	 * @param Question $question
	 * @param BallotStatus $status
	 * @return string
	 */
	public function submitQuestion( $question, $status ) {
		$options = $question->getOptions();
		$record = '';
		$ok = true;
		foreach ( $options as $option ) {
			$id = 'securepoll_q' . $question->getId() . '_opt' . $option->getId();
			$rank = $this->getRequest()->getVal( $id );

			if ( is_numeric( $rank ) ) {
				if ( $rank <= 0 || $rank >= 1000 ) {
					$status->spFatal( 'securepoll-invalid-rank', $id, false );
					$ok = false;
					continue;
				} else {
					$rank = intval( $rank );
				}
			} elseif ( strval( $rank ) === '' ) {
				if ( $this->election->getProperty( 'must-rank-all' ) ) {
					$status->spFatal( 'securepoll-unranked-options', $id, false );
					$ok = false;
					continue;
				} else {
					$rank = 1000;
				}
			} else {
				$status->spFatal( 'securepoll-invalid-rank', $id, false );
				$ok = false;
				continue;
			}
			$record .= $this->packRecord( $question, $option, $rank );
		}
		if ( $ok ) {
			return $record;
		}
	}

	public function packRecord( $question, $option, $rank ) {
		return sprintf(
			'Q%08X-A%08X-R%08X--',
			$question->getId(),
			$option->getId(),
			$rank
		);
	}

	public function unpackRecord( $record ) {
		$ranks = [];
		$itemLength = 3 * 8 + 7;
		for ( $offset = 0, $len = strlen( $record ); $offset < $len; $offset += $itemLength ) {
			if ( !preg_match(
				'/Q([0-9A-F]{8})-A([0-9A-F]{8})-R([0-9A-F]{8})--/A',
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
			$ranks[$qid][$oid] = $rank;
		}

		return $ranks;
	}

	public function convertScores( $scores, $params = [] ) {
		$result = [];
		foreach ( $this->election->getQuestions() as $question ) {
			$qid = $question->getId();
			if ( !isset( $scores[$qid] ) ) {
				return false;
			}
			$s = '';
			$qscores = $scores[$qid];
			ksort( $qscores );
			$first = true;
			foreach ( $qscores as $rank ) {
				if ( $first ) {
					$first = false;
				} else {
					$s .= ', ';
				}
				if ( $rank == 1000 ) {
					$s .= '-';
				} else {
					$s .= $rank;
				}
			}
			$result[$qid] = $s;
		}

		return $result;
	}
}
