<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use Exception;
use MediaWiki\Status\Status;

class StatusException extends Exception {
	/** @var Status */
	public $status;

	public function __construct( $message, ...$parameters ) {
		$this->status = Status::newFatal( $message, ...$parameters );
	}
}
