<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Extension\SecurePoll\Entities\Question;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\RadioInputWidget;

/**
 * A ballot class which asks the user to choose one answer only from the
 * given options, for each question.
 */
class ChooseBallot extends Ballot {
	/**
	 * Get a list of names of tallying methods, which may be used to produce a
	 * result from this ballot type.
	 * @return array
	 */
	public static function getTallyTypes() {
		return [ 'plurality' ];
	}

	public static function getCreateDescriptors() {
		$ret = parent::getCreateDescriptors();
		$ret['option'] += [
			'name' => [
				'label-message' => 'securepoll-create-label-option-name',
				'type' => 'text',
				'SecurePoll_type' => 'message',
			],
		];

		return $ret;
	}

	/**
	 * Get the HTML form segment for a single question
	 * @param Question $question
	 * @param array $options Array of options, in the order they should be displayed
	 * @return FieldsetLayout
	 */
	public function getQuestionForm( $question, $options ) {
		$name = 'securepoll_q' . $question->getId();
		$fieldset = new FieldsetLayout();
		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();

			$fieldset->appendContent( [
				new FieldLayout( new RadioInputWidget( [
					'name' => $name,
					'value' => $optionId,
					'required' => true,
					] ), [
						'classes' => [ 'securepoll-option-choose' ],
						'label' => new HtmlSnippet( $optionHTML ),
						'align' => 'inline'
					] )
			] );
		}

		return $fieldset;
	}

	/**
	 * @param Question $question
	 * @param BallotStatus $status
	 * @return string
	 */
	public function submitQuestion( $question, $status ) {
		$result = $this->getRequest()->getInt( 'securepoll_q' . $question->getId() );
		if ( !$result ) {
			$status->fatal( 'securepoll-unanswered-questions' );
		} else {
			return $this->packRecord( $question->getId(), $result );
		}
	}

	public function packRecord( $qid, $oid ) {
		return sprintf( 'Q%08XA%08X', $qid, $oid );
	}

	public function unpackRecord( $record ) {
		$result = [];
		$record = trim( $record );
		for ( $offset = 0, $len = strlen( $record ); $offset < $len; $offset += 18 ) {
			if ( !preg_match( '/Q([0-9A-F]{8})A([0-9A-F]{8})/A', $record, $m, 0, $offset ) ) {
				wfDebug( __METHOD__ . ": regex doesn't match\n" );

				return false;
			}
			$qid = intval( base_convert( $m[1], 16, 10 ) );
			$oid = intval( base_convert( $m[2], 16, 10 ) );
			$result[$qid] = [ $oid => 1 ];
		}

		return $result;
	}

	public function convertScores( $scores, $params = [] ) {
		$s = '';
		foreach ( $this->election->getQuestions() as $question ) {
			$qid = $question->getId();
			if ( !isset( $scores[$qid] ) ) {
				return false;
			}
			if ( $s !== '' ) {
				$s .= '; ';
			}
			$oid = key( $scores );
			// FIXME: getOption doesn't exist
			// @phan-suppress-next-line PhanUndeclaredMethod
			$option = $this->election->getOption( $oid );
			$s .= $option->getMessage( 'name' );
		}

		return $s;
	}
}
