<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Exception;

class StatusException extends Exception {
	public $status;

	public function __construct( ...$args ) {
		$this->status = call_user_func_array( 'Status::newFatal', $args );
	}
}
