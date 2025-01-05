<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use Exception;
use MediaWiki\Status\Status;
use Wikimedia\Message\MessageParam;
use Wikimedia\Message\MessageSpecifier;

class StatusException extends Exception {
	/** @var Status */
	public $status;

	/**
	 * @param string|MessageSpecifier $message
	 * @phpcs:ignore Generic.Files.LineLength
	 * @param MessageParam|MessageSpecifier|string|int|float|list<MessageParam|MessageSpecifier|string|int|float> ...$parameters
	 */
	public function __construct( $message, ...$parameters ) {
		$this->status = Status::newFatal( $message, ...$parameters );
	}
}
