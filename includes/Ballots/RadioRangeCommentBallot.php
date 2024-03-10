<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use LogicException;

/**
 * A ballot used specifically for the Wikimedia Referendum on the personal image filter.
 * Allows voters to send comments in with their ballot.
 *
 * Now deprecated in favour of the comment feature in VotePage.
 */
class RadioRangeCommentBallot extends RadioRangeBallot {
	public function getForm( $prevStatus = false ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new LogicException( 'This ballot type has been archived and can no longer be used for voting.' );
	}

	public function submitForm() {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new LogicException( 'This ballot type has been archived and can no longer be used for voting.' );
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
			[ $min, $max ] = $this->getMinMax( $questions[$qid] );
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
