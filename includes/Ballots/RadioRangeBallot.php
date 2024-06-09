<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Entities\Entity;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\HtmlForm\HTMLFormRadioRangeColumnLabels;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Parser\Sanitizer;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\RadioInputWidget;
use OOUI\Tag;

/**
 * A ballot form for range voting where the number of allowed responses is small,
 * allowing a radio button table interface and histogram tallying.
 *
 * Election properties:
 *     must-answer-all
 *
 * Question properties:
 *     min-score
 *     max-score
 *     column-label-msgs
 *     column-order
 *
 * Question messages:
 *     column-1, column0, column+1, etc.
 */
class RadioRangeBallot extends Ballot {
	/** @var string[]|null */
	public $columnLabels;
	/** @var int[]|null */
	public $minMax;

	public static function getTallyTypes() {
		return [
			'plurality',
			'histogram-range'
		];
	}

	public static function getCreateDescriptors() {
		$ret = parent::getCreateDescriptors();
		$ret['election'] += [
			'must-answer-all' => [
				'label-message' => 'securepoll-create-label-must_answer_all',
				'type' => 'check',
				'hidelabel' => true,
				'SecurePoll_type' => 'property',
			],
		];
		$ret['question'] += [
			'min-score' => [
				'label-message' => 'securepoll-create-label-min_score',
				'type' => 'int',
				'validation-callback' => [
					CreatePage::class,
					'checkRequired',
				],
				'SecurePoll_type' => 'property',
			],
			'max-score' => [
				'label-message' => 'securepoll-create-label-max_score',
				'type' => 'int',
				'validation-callback' => [
					CreatePage::class,
					'checkRequired',
				],
				'SecurePoll_type' => 'property',
			],
			'default-score' => [
				'label-message' => 'securepoll-create-label-default_score',
				'type' => 'int',
				'SecurePoll_type' => 'property',
			],
			'column-order' => [
				'label-message' => 'securepoll-create-label-column_order',
				'type' => 'select',
				'options-messages' => [
					'securepoll-create-option-column_order-asc' => 'asc',
					'securepoll-create-option-column_order-desc' => 'desc',
				],
				'SecurePoll_type' => 'property',
			],
			'column-label-msgs' => [
				'label-message' => 'securepoll-create-label-column_label_msgs',
				'type' => 'check',
				'hidelabel' => true,
				'SecurePoll_type' => 'property',
			],
			'column-messages' => [
				'hide-if' => [
					'!==',
					'column-label-msgs',
					'1'
				],
				'class' => HTMLFormRadioRangeColumnLabels::class,
				'SecurePoll_type' => 'messages',
			],
		];

		return $ret;
	}

	/**
	 * @param Question $question
	 * @return array
	 * @throws InvalidDataException
	 */
	public function getMinMax( $question ) {
		$min = intval( $question->getProperty( 'min-score' ) );
		$max = intval( $question->getProperty( 'max-score' ) );
		if ( $max <= $min ) {
			throw new InvalidDataException( __METHOD__ . ': min/max not configured' );
		}

		return [
			$min,
			$max
		];
	}

	/**
	 * @param Question $question
	 * @return int
	 * @throws InvalidDataException
	 */
	public function getColumnDirection( $question ) {
		$order = $question->getProperty( 'column-order' );
		if ( !$order ) {
			return 1;
		} elseif ( preg_match( '/^asc/i', $order ) ) {
			return 1;
		} elseif ( preg_match( '/^desc/i', $order ) ) {
			return -1;
		} else {
			throw new InvalidDataException( __METHOD__ . ': column-order configured incorrectly' );
		}
	}

	/**
	 * @param Question $question
	 * @return array
	 */
	public function getScoresLeftToRight( $question ) {
		$incr = $this->getColumnDirection( $question );
		[ $min, $max ] = $this->getMinMax( $question );
		if ( $incr > 0 ) {
			$left = $min;
			$right = $max;
		} else {
			$left = $max;
			$right = $min;
		}

		return range( $left, $right );
	}

	/**
	 * @param Question $question
	 * @return array
	 */
	public function getColumnLabels( $question ) {
		// list( $min, $max ) = $this->getMinMax( $question );
		$labels = [];
		$useMessageLabels = $question->getProperty( 'column-label-msgs' );
		$scores = $this->getScoresLeftToRight( $question );
		if ( $useMessageLabels ) {
			foreach ( $scores as $score ) {
				$signedScore = $this->addSign( $question, $score );
				$labels[$score] = $question->parseMessageInline( "column$signedScore" );
			}
		} else {
			foreach ( $scores as $score ) {
				$labels[$score] = $this->getUserLang()->formatNum( $score );
			}
		}

		return $labels;
	}

	public function getMessageNames( Entity $entity = null ) {
		if ( $entity === null || $entity->getType() !== 'question' ) {
			return [];
		}
		if ( !$entity->getProperty( 'column-label-msgs' ) ) {
			return [];
		}
		$msgs = [];
		if ( !$entity instanceof Question ) {
			$class = get_class( $entity );
			throw new InvalidArgumentException(
				"Expecting instance of Question, got $class instead"
			);
		}
		[ $min, $max ] = $this->getMinMax( $entity );
		for ( $score = $min; $score <= $max; $score++ ) {
			$signedScore = $this->addSign( $entity, $score );
			$msgs[] = "column$signedScore";
		}

		return $msgs;
	}

	public function addSign( $question, $score ) {
		[ $min, ] = $this->getMinMax( $question );
		if ( $min < 0 && $score > 0 ) {
			return "+$score";
		}

		return $score;
	}

	/**
	 * @param Question $question
	 * @param array $options
	 * @return FieldsetLayout
	 */
	public function getQuestionForm( $question, $options ) {
		$name = 'securepoll_q' . $question->getId();
		$labels = $this->getColumnLabels( $question );

		$table = new Tag( 'table' );
		$table->addClasses( [ 'securepoll-ballot-table' ] );

		$thead = new Tag( 'thead' );
		$table->appendContent( $thead );
		$tr = new Tag( 'tr' );
		$tr->appendContent( new Tag( 'th' ) );
		foreach ( $labels as $lab ) {
			$tr->appendContent( ( new Tag( 'th' ) )->appendContent( $lab ) );
		}
		$thead->appendContent( $tr );
		$tbody = new Tag( 'tbody' );
		$table->appendContent( $tbody );

		$defaultScore = $question->getProperty( 'default-score' );

		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();
			$inputId = "{$name}_opt{$optionId}";
			$oldValue = $this->getRequest()->getVal( $inputId, $defaultScore );

			$tr = ( new Tag( 'tr' ) )->addClasses( [ 'securepoll-ballot-row', $inputId ] );
			$tr->appendContent(
				( new Tag( 'td' ) )
					->appendContent( new HtmlSnippet( $optionHTML ) )
			);
			foreach ( $labels as $score => $label ) {
				$tr->appendContent( ( new Tag( 'td' ) )->appendContent(
					new RadioInputWidget( [
						'name' => $inputId,
						'value' => $score,
						'selected' => !strcmp( $oldValue, $score ),
						'title' => Sanitizer::stripAllTags( $label ),
					] )
				) );
			}
			$tr->appendContent(
				( new Tag( 'td' ) )
					->addClasses( [ 'securepoll-ballot-optlabel' ] )
					->appendContent( new HtmlSnippet( $this->errorLocationIndicator( $inputId ) . "" ) )
			);
			$tbody->appendContent( $tr );
		}
		return new FieldsetLayout( [
			'items' => [ new Element( [ 'content' => [ $table ] ] ) ],
			'classes' => [ $name ]
		] );
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
		[ $min, $max ] = $this->getMinMax( $question );
		$defaultScore = $question->getProperty( 'default-score' );
		foreach ( $options as $option ) {
			$id = 'securepoll_q' . $question->getId() . '_opt' . $option->getId();
			$score = $this->getRequest()->getVal( $id );

			if ( is_numeric( $score ) ) {
				if ( $score < $min || $score > $max ) {
					$status->spFatal(
						'securepoll-invalid-score',
						$id,
						false,
						$this->getUserLang()->formatNum( $min ),
						$this->getUserLang()->formatNum( $max )
					);
					$ok = false;
					continue;
				}

				$score = intval( $score );
			} elseif ( strval( $score ) === '' ) {
				if ( $this->election->getProperty( 'must-answer-all' ) ) {
					$status->spFatal( 'securepoll-unanswered-options', $id, false );
					$ok = false;
					continue;
				}

				$score = $defaultScore;
			} else {
				$status->spFatal(
					'securepoll-invalid-score',
					$id,
					false,
					$this->getUserLang()->formatNum( $min ),
					$this->getUserLang()->formatNum( $max )
				);
				$ok = false;
				continue;
			}
			$record .= sprintf(
				'Q%08X-A%08X-S%+011d--',
				$question->getId(),
				$option->getId(),
				$score
			);
		}
		if ( $ok ) {
			return $record;
		} else {
			return '';
		}
	}

	/**
	 * @param string $record
	 * @return array|bool
	 */
	public function unpackRecord( $record ) {
		$scores = [];
		$itemLength = 8 + 8 + 11 + 7;
		$questions = [];
		foreach ( $this->election->getQuestions() as $question ) {
			$questions[$question->getId()] = $question;
		}
		for ( $offset = 0, $len = strlen( $record ); $offset < $len; $offset += $itemLength ) {
			if ( !preg_match(
				'/Q([0-9A-F]{8})-A([0-9A-F]{8})-S([+-][0-9]{10})--/A',
				$record,
				$m,
				0,
				$offset
			) ) {
				wfDebug( __METHOD__ . ": regex doesn't match\n" );
				return false;
			}

			$qid = intval( base_convert( $m[1], 16, 10 ) );
			$oid = intval( base_convert( $m[2], 16, 10 ) );
			$score = intval( $m[3] );
			if ( !isset( $questions[$qid] ) ) {
				wfDebug( __METHOD__ . ": invalid question ID\n" );
				return false;
			}

			[ $min, $max ] = $this->getMinMax( $questions[$qid] );
			if ( $score < $min || $score > $max ) {
				wfDebug( __METHOD__ . ": score out of range\n" );
				return false;
			}

			$scores[$qid][$oid] = $score;
		}

		return $scores;
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
			foreach ( $qscores as $score ) {
				if ( $first ) {
					$first = false;
				} else {
					$s .= ', ';
				}
				$s .= $score;
			}
			$result[$qid] = $s;
		}

		return $result;
	}
}
