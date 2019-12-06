<?php

class SecurePoll_StatusException extends Exception {
	public $status;

	public function __construct( ...$args ) {
		$this->status = call_user_func_array( 'Status::newFatal', $args );
	}
}
