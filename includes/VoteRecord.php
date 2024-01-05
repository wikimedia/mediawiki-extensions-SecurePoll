<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Status\Status;
use RuntimeException;

/**
 * Helper class for interpreting decrypted vote_record field values in a
 * backwards-compatible way.
 *
 * Previously this field stored only the fixed-length ballot records. Now
 * the ballot record may be wrapped in a JSON object, so that the vote
 * page may add free text comments. (T300087)
 */
class VoteRecord {
	/** @var string */
	private $ballotData = '';
	/** @var string */
	private $comment = '';

	/**
	 * Factory with Status
	 *
	 * @param string $blob
	 * @return Status
	 */
	public static function readBlob( string $blob ) {
		$status = Status::newGood();
		$voteRecord = self::newFromBlob( $blob );
		if ( !$blob ) {
			$status->fatal( 'securepoll-invalid-record' );
		} else {
			$status->value = $voteRecord;
		}
		return $status;
	}

	/**
	 * Interpret decrypted field and construct object, return null on error.
	 *
	 * @param string $blob
	 * @return VoteRecord|null
	 */
	public static function newFromBlob( string $blob ) {
		if ( ( $blob[0] ?? '' ) === '{' ) {
			return self::newFromJson( $blob );
		} else {
			return self::newFromOldBlob( $blob );
		}
	}

	/**
	 * @param string $json
	 * @return VoteRecord|null
	 */
	private static function newFromJson( $json ) {
		$record = new self;
		$data = json_decode( $json, true );
		if ( !is_array( $data ) ) {
			wfDebug( __METHOD__ . ': not array' );
			return null;
		}
		if ( !isset( $data['vote'] ) ) {
			wfDebug( __METHOD__ . ': no vote' );
			return null;
		}
		if ( !is_string( $data['vote'] ) ) {
			wfDebug( __METHOD__ . ': vote is not string' );
			return null;
		}
		if ( isset( $data['comment'] ) ) {
			if ( !is_string( $data['comment'] ) ) {
				wfDebug( __METHOD__ . ': comment is not string' );
				return null;
			}
		}
		$record->ballotData = $data['vote'];
		$record->comment = $data['comment'] ?? '';
		return $record;
	}

	/**
	 * @param string $blob
	 * @return VoteRecord
	 */
	private static function newFromOldBlob( $blob ) {
		$record = new self;
		$record->ballotData = rtrim( $blob );
		return $record;
	}

	/**
	 * Create a record from form data.
	 *
	 * @param string $ballotData
	 * @param string $comment
	 * @return VoteRecord
	 */
	public static function newFromBallotData( string $ballotData, string $comment ) {
		$record = new self;
		$record->ballotData = $ballotData;
		$record->comment = $comment;
		return $record;
	}

	/**
	 * Get the ballot data
	 *
	 * @return string
	 */
	public function getBallotData(): string {
		return $this->ballotData;
	}

	/**
	 * Get the comment. It may be an empty string.
	 *
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment;
	}

	/**
	 * Serialize the vote record
	 *
	 * @return string
	 */
	public function getBlob(): string {
		$voteData = [ 'vote' => $this->ballotData ];
		if ( $this->comment !== '' ) {
			$voteData['comment'] = $this->comment;
		}
		$json = json_encode(
			$voteData,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if ( !$json ) {
			throw new RuntimeException( 'JSON encoding of vote record failed' );
		}
		return $json;
	}
}
