<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;

class DumpElection {

	private const MAX_BLT_NAME_LENGTH = 20;

	/**
	 * @param Election $election
	 * @param array $confOptions
	 * @param bool $withVotes
	 *
	 *  Election conf xml options are:
	 *  - jump: boolean
	 *  - langs: array
	 *  - private: boolean
	 *
	 * @return string
	 * @throws InvalidDataException
	 */
	public static function createXMLDump( $election, $confOptions = [], $withVotes = true ) {
		$confXml = $election->getConfXml( $confOptions );
		$dump = "<SecurePoll>\n<election>\n$confXml";

		if ( $withVotes ) {
			$status = $election->dumpVotesToCallback( static function ( $election, $row ) use ( &$dump ) {
				$dump .= "<vote>\n" . htmlspecialchars( rtrim( $row->vote_record ) ) . "\n</vote>\n";
			} );

			if ( !$status->isOK() ) {
				throw new InvalidDataException( $status->getWikiText() );
			}
		}

		$dump .= "</election>\n</SecurePoll>\n";

		return $dump;
	}

	/**
	 * @param Election $election
	 *
	 * @return string
	 * @throws InvalidDataException
	 */
	public static function createBLTDump( $election ) {
		if ( $election->ballotType !== 'stv' ) {
			throw new InvalidDataException( wfMessage( 'securepoll-dump-blt-error-not-stv' )->plain() );
		}

		$questions = $election->getQuestions();
		if ( count( $questions ) !== 1 ) {
			throw new InvalidDataException( wfMessage( 'securepoll-dump-blt-error-multiple-questions' )->plain() );
		}

		return self::generateBlt( $questions[0], $election );
	}

	/**
	 * Dump in BLT format.
	 * Reference: https://svn.apache.org/repos/asf/steve/trunk/stv_background/meekm.pdf
	 *
	 * @param Question $question
	 * @param Election $election
	 *
	 * @return string
	 * @throws InvalidDataException
	 */
	private static function generateBlt( $question, $election ) {
		$title = $election->title;

		// Limit title to 20 characters
		if ( $title && strlen( $title ) > self::MAX_BLT_NAME_LENGTH ) {
			$title = substr( $title, 0, self::MAX_BLT_NAME_LENGTH );
		}
		$title = self::ensureDoubleQuoted( $title );

		$questionId = $question->getId();
		$candidates = [];
		foreach ( $question->getOptions() as $option ) {
			$candidates[$option->getId()] = self::ensureDoubleQuoted( $option->getMessage( 'text' ) );

			// Limit candidate name to 20 characters
			if ( strlen( $candidates[$option->getId()] ) > self::MAX_BLT_NAME_LENGTH ) {
				$candidates[$option->getId()] = substr( $candidates[$option->getId()], 0, self::MAX_BLT_NAME_LENGTH );
			}
		}
		// Create candidate number mapping list
		$candidateNumberMapping = [];
		for ( $i = 0; $i < count( $candidates ); $i++ ) {
			$candidateNumberMapping[array_keys( $candidates )[$i]] = $i + 1;
		}

		$availableSeats = (int)$question->getProperty( 'min-seats' );
		$numberOfCandidates = count( $candidates );
		$voteRows = self::createBltVoteRows( $election, $questionId, $candidateNumberMapping );

		// $voteRows can be empty if vote has no valid vote records.
		// @phan-suppress-next-line MediaWikiNoEmptyIfDefined
		if ( empty( $voteRows ) ) {
			throw new InvalidDataException( wfMessage( 'securepoll-dump-blt-error-no-votes' )->plain() );
		}

		// Create BLT format
		$blt = "$numberOfCandidates $availableSeats\n";
		$blt .= implode( "\n", $voteRows );
		$blt .= "\n0\n";
		foreach ( $candidateNumberMapping as $candidateId => $number ) {
			$blt .= "$candidates[$candidateId]\n";
		}
		$blt .= "$title\n";

		return $blt;
	}

	/**
	 * Converts database vote records to BLT format.
	 * Result example of one row:
	 *
	 * 2 2 4 3 0
	 *
	 * 2 voters put
	 * candidate 2 first,
	 * candidate 4 second,
	 * candidate 3 third,
	 * and no more.
	 * Each such list must end with a zero.
	 *
	 * @param Election $election
	 * @param int $questionId
	 * @param array $candidateNumberMapping
	 *
	 * @return array
	 */
	private static function createBltVoteRows( $election, $questionId, $candidateNumberMapping ) {
		$ballot = $election->getBallot();

		// Unpack all records into ranked votes.
		// Votes are in random order.
		$records = [];
		$election->dumpVotesToCallback( static function ( $election, $row ) use ( $questionId, $ballot, &$records ) {
			$voteRecord = $row->vote_record;

			// Sometimes the vote record is an JSON string with key "vote" in database
			$jsonVoteRecord = json_decode( $voteRecord );
			if ( $jsonVoteRecord && isset( $jsonVoteRecord->vote ) ) {
				$voteRecord = $jsonVoteRecord->vote;
			}

			$record = $ballot->unpackRecord( $voteRecord );

			if ( !isset( $record[$questionId] ) ) {
				return;
			}

			$records[] = $record[$questionId];
		} );

		// Convert ranked votes to BLT format
		$bltVoteRows = [];
		foreach ( $records as $record ) {
			$bltVoteRow = implode( " ", array_map( static function ( $optionId ) use ( $candidateNumberMapping ) {
				// Sometimes the vote record is already the candidate number instead of the option ID
				if ( isset( $candidateNumberMapping[$optionId] ) ) {
					return $candidateNumberMapping[$optionId];
				}

				return $optionId;
			}, $record ) );

			// Each row must end with a zero
			$bltVoteRow .= " 0";

			// Count the number of times each equal row appears
			if ( !isset( $bltVoteRows[$bltVoteRow] ) ) {
				$bltVoteRows[$bltVoteRow] = 1;

				continue;
			}

			$bltVoteRows[$bltVoteRow]++;
		}

		// Put the number of appearances at the front of each row
		foreach ( $bltVoteRows as $voteRow => $count ) {
			$bltVoteRows[$voteRow] = "$count $voteRow";
		}

		return array_values( $bltVoteRows );
	}

	private static function ensureDoubleQuoted( $string ) {
		if ( !$string ) {
			return "";
		}

		// Check if the string is already enclosed in double quotes
		if ( strlen( $string ) >= 2 && $string[0] === '"' && $string[strlen( $string ) - 1] === '"' ) {
			return $string;
		}

		// If not, add double quotes around the string
		return '"' . $string . '"';
	}
}
