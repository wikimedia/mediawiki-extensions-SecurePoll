<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use OOUI;

/**
 * A ballot used specifically for the Wikimedia Referendum on the personal image filter.
 * Allows voters to send comments in with their ballot
 */
class RadioRangeCommentBallot extends RadioRangeBallot {
	public function getForm( $prevStatus = false ) {
		$form = parent::getForm( $prevStatus );

		$fieldSet = new \OOUI\FieldsetLayout( [
			'label' => new \OOUI\HtmlSnippet( $this->election->parseMessage( 'comments' ) ),
			'items' => [
				new OOUI\MultilineTextInputWidget( [
					'name' => 'securepoll_comments_native',
					'value' => '',
					'rows' => 10,
				] ),
				new OOUI\MultilineTextInputWidget( [
					'name' => 'securepoll_comments_en',
					'value' => '',
					'rows' => 10,
				] )
			]
		] );
		$form[] = $fieldSet;
		return $form;
	}

	public function submitForm() {
		$status = parent::submitForm();

		if ( !$status->isGood() ) {
			return $status;
		}

		// Load comments
		global $wgRequest;

		$commentNative = $wgRequest->getText( 'securepoll_comments_native' );
		$commentEnglish = $wgRequest->getText( 'securepoll_comments_en' );

		$record = rtrim( $status->value );

		$record .= '/' . strlen( $commentNative ) . '/' . $commentNative;
		$record .= '--/' . strlen( $commentEnglish ) . '/' . $commentEnglish;

		$status->value = $record;

		return $status;
	}

	/**
	 * Copy and modify from parent function, complex to refactor.
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
			)
			) {
				// Allow comments
				if ( $record[$offset] == '/' ) {
					break;
				}

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
			list( $min, $max ) = $this->getMinMax( $questions[$qid] );
			if ( $score < $min || $score > $max ) {
				wfDebug( __METHOD__ . ": score out of range\n" );

				return false;
			}
			$scores[$qid][$oid] = $score;
		}

		// Read comments
		$scores['comment'] = [];

		$scores['comment']['native'] = $this->readComment( $record, $offset );

		if ( substr( $record, $offset, 2 ) !== '--' ) {
			wfDebug( __METHOD__ . ": Invalid format\n" );

			return false;
		}
		$offset += 2;

		$scores['comment']['en'] = $this->readComment( $record, $offset );

		if ( $offset < strlen( $record ) ) {
			wfDebug( __METHOD__ . ": Invalid format\n" );

			return false;
		}

		return $scores;
	}

	public function readComment( $record, &$offset ) {
		$commentOffset = strpos( $record, '/', $offset + 1 );
		$commentLength = intval(
			substr(
				$record,
				$offset + 1,
				( $commentOffset - $offset ) - 1
			)
		);
		$result = substr( $record, $commentOffset + 1, $commentLength );
		// Fast-forward
		$offset = $commentOffset + $commentLength + 1;

		return $result;
	}
}
