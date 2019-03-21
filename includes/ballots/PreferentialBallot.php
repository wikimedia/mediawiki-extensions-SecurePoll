<?php

/**
 * Ballot for preferential voting
 * Properties:
 *     shuffle-questions
 *     shuffle-options
 *     must-rank-all
 */
class SecurePoll_PreferentialBallot extends SecurePoll_Ballot {
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
	 * @param SecurePoll_Question $question
	 * @param Array $options
	 * @return string
	 */
	public function getQuestionForm( $question, $options ) {
		global $wgRequest;
		$name = 'securepoll_q' . $question->getId();
		$s = '';
		foreach ( $options as $option ) {
			$optionHTML = $option->parseMessageInline( 'text' );
			$optionId = $option->getId();
			$inputId = "{$name}_opt{$optionId}";
			$oldValue = $wgRequest->getVal( $inputId, '' );
			$s .=
				'<div class="securepoll-option-preferential">' .
				Xml::input( $inputId, '3', $oldValue, [
					'id' => $inputId,
					'maxlength' => 3,
				] ) .
				'&#160;' .
				$this->errorLocationIndicator( $inputId ) .
				Xml::tags( 'label', [ 'for' => $inputId ], $optionHTML ) .
				'&#160;' .
				"</div>\n";
		}
		return $s;
	}

	/**
	 * @param SecurePoll_Question $question
	 * @param SecurePoll_BallotStatus $status
	 * @return string
	 */
	public function submitQuestion( $question, $status ) {
		global $wgRequest;

		$options = $question->getOptions();
		$record = '';
		$ok = true;
		foreach ( $options as $option ) {
			$id = 'securepoll_q' . $question->getId() . '_opt' . $option->getId();
			$rank = $wgRequest->getVal( $id );

			if ( is_numeric( $rank ) ) {
				if ( $rank <= 0 || $rank >= 1000 ) {
					$status->sp_fatal( 'securepoll-invalid-rank', $id );
					$ok = false;
					continue;
				} else {
					$rank = intval( $rank );
				}
			} elseif ( strval( $rank ) === '' ) {
				if ( $this->election->getProperty( 'must-rank-all' ) ) {
					$status->sp_fatal( 'securepoll-unranked-options', $id );
					$ok = false;
					continue;
				} else {
					$rank = 1000;
				}
			} else {
				$status->sp_fatal( 'securepoll-invalid-rank', $id );
				$ok = false;
				continue;
			}
			$record .= sprintf( 'Q%08X-A%08X-R%08X--',
				$question->getId(), $option->getId(), $rank );
		}
		if ( $ok ) {
			return $record;
		}
	}

	public function unpackRecord( $record ) {
		$ranks = [];
		$itemLength = 3 * 8 + 7;
		for ( $offset = 0, $len = strlen( $record ); $offset < $len; $offset += $itemLength ) {
			if ( !preg_match( '/Q([0-9A-F]{8})-A([0-9A-F]{8})-R([0-9A-F]{8})--/A',
				$record, $m, 0, $offset )
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
