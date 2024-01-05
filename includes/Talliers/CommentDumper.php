<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Crypt\Crypt;
use MediaWiki\Extension\SecurePoll\Store\Store;
use MediaWiki\Status\Status;

/**
 * A class that dumps the comments from an election
 */
class CommentDumper extends ElectionTallier {
	/** @var Ballot|null */
	public $ballot;
	/** @var resource|null */
	public $csvHandle;
	/** @var Crypt|null */
	public $crypt;
	/** @var bool */
	public $skipEmptyComments;
	/** @var int|null */
	private $countSoFar;

	public function __construct( $context, $election, $skipEmptyComments = true ) {
		parent::__construct( $context, $election );
		$this->skipEmptyComments = $skipEmptyComments;
	}

	public function execute() {
		$this->csvHandle = fopen( 'php://temp', 'r+' );
		$this->countSoFar = 0;

		return parent::execute();
	}

	/**
	 * Add a record. This is the callback function for SecurePoll_Store::callbackValidVotes().
	 * On error, the Status object returned here will be passed through back to
	 * the caller of callbackValidVotes().
	 *
	 * @param Store $store
	 * @param string $record Encrypted, packed record.
	 * @return Status
	 */
	public function addRecord( $store, $record ) {
		$this->countSoFar++;
		wfDebug( "Processing vote {$this->countSoFar}\n" );
		# Decrypt and unpack
		if ( $this->crypt ) {
			$status = $this->crypt->decrypt( $record );
			if ( !$status->isOK() ) {
				return $status;
			}
			$record = $status->value;
		}
		$record = rtrim( $record );
		$scores = $this->ballot->unpackRecord( $record );

		$comments = $scores['comment'];
		unset( $scores['comment'] );

		// Short circuit if the comments are empty
		if ( $this->skipEmptyComments && $comments['native'] == '' && $comments['en'] == '' ) {
			return Status::newGood();
		}

		$output = [
			$comments['native'],
			$comments['en']
		];

		ksort( $output );

		foreach ( $scores as $question ) {
			ksort( $question );
			$output = array_merge( $output, $question );
		}

		fputcsv( $this->csvHandle, $output );

		return Status::newGood();
	}

	/**
	 * @inheritDoc
	 * Get text formatted results for this tally. Should only be called after
	 * execute().
	 */
	public function getHtmlResult() {
		return $this->getTextResult();
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getTextResult() {
		return stream_get_contents( $this->csvHandle, -1, 0 );
	}
}
