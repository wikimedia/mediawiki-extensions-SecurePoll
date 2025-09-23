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

		return self::generateBltFromDb( $questions[0], $election );
	}

	/**
	 * Generate blt
	 * Reference: https://svn.apache.org/repos/asf/steve/trunk/stv_background/meekm.pdf
	 *
	 * @param string $title
	 * @param Question $question
	 * @param array $votes array of votes where each vote is formatted as [ id, id, id ]
	 * @param array $excludedCandidates array of candidate ids that have withdrawn
	 *
	 * @return string
	 */
	public static function generateBltFromData( $title, $question, $votes, $excludedCandidates = [] ) {
		// Limit title to 20 characters
		if ( $title && strlen( $title ) > self::MAX_BLT_NAME_LENGTH ) {
			$title = substr( $title, 0, self::MAX_BLT_NAME_LENGTH );
		}
		$title = self::ensureDoubleQuoted( $title );

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

		// If candidates were excluded, generate that representation:
		// A single line of as many candidates as necessary, each declared as `-{$id}`
		$candidateExclusions = '';
		foreach ( $excludedCandidates as $excludedCandidate ) {
			$candidateExclusions .= '-' . $candidateNumberMapping[$excludedCandidate] . ' ';
		}
		$candidateExclusions = trim( $candidateExclusions );

		// Convert ranked votes to BLT format
		// eg. 2 1 0 representing "2 voters voted for candidate 1" with a 0 end delineator
		$voteCounts = [];
		foreach ( $votes as $vote ) {
			// Votes come in as an ordered array of candidate ids, the blt will map this
			// so that it's fully self-enclosed. Use $candidateNumberMapping to convert
			// candidate ids to candidate indexes.
			$vote = array_map( static function ( $candidateId ) use ( $candidateNumberMapping ) {
				// Sometimes the vote record is already the candidate number instead of the option ID
				if ( isset( $candidateNumberMapping[$candidateId] ) ) {
					return $candidateNumberMapping[$candidateId];
				}
				return $candidateId;
			}, $vote );
			$vote = implode( " ", $vote );

			// Each row must end with a zero
			$vote .= " 0";

			// Count the number of times each equal row appears
			if ( !isset( $voteCounts[$vote] ) ) {
				$voteCounts[$vote] = 1;
			} else {
				$voteCounts[$vote]++;
			}
		}

		// Put the number of appearances at the front of each row
		foreach ( $voteCounts as $voteCount => $count ) {
			$voteCounts[$voteCount] = "$count $voteCount";
		}

		$voteCounts = array_values( $voteCounts );

		// Create BLT format
		$blt = "$numberOfCandidates $availableSeats\n";
		if ( $candidateExclusions ) {
			$blt .= "$candidateExclusions\n";
		}
		if ( count( $voteCounts ) ) {
			$blt .= implode( "\n", $voteCounts );
			$blt .= "\n";
		}
		$blt .= "0\n";
		foreach ( $candidateNumberMapping as $candidateId => $number ) {
			$blt .= "$candidates[$candidateId]\n";
		}
		$blt .= "$title\n";

		return $blt;
	}

	/**
	 * Get votes and pass through to blt generator
	 *
	 * @param Question $question
	 * @param Election $election
	 *
	 * @return string
	 * @throws InvalidDataException
	 */
	public static function generateBltFromDb( $question, $election ) {
		// Pull votes from database
		// Encrypted elections are not supported via this pathway. Instead, manually decrypt the votes
		// and use self::generateBltFromData
		$ballot = $election->getBallot();
		$questionId = $question->getId();
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

		// $records can be empty if election has no valid vote records or is encrypted.
		if ( !count( $records ) ) {
			throw new InvalidDataException( wfMessage( 'securepoll-dump-blt-error-no-votes' )->plain() );
		}

		return self::generateBltFromData( $election->title, $question, $records );
	}

	private static function ensureDoubleQuoted( string $string ): string {
		if ( !$string ) {
			return "";
		}

		// Check if the string is already enclosed in double quotes
		if ( strlen( $string ) >= 2 && $string[0] === '"' && str_ends_with( $string, '"' ) ) {
			return $string;
		}

		// If not, add double quotes around the string
		return '"' . $string . '"';
	}
}
