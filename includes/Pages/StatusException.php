<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use Exception;

class StatusException extends Exception {
	/** @var \Status */
	public $status;

	public function __construct( ...$args ) {
		$this->status = call_user_func_array( 'Status::newFatal', $args );
	}
}
