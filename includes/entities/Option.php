<?php

/**
 * Class representing the options which the voter can choose from when they are
 * answering a question.
 */
class SecurePoll_Option extends SecurePoll_Entity {
	/**
	 * Constructor
	 * @param SecurePoll_Context $context
	 * @param array $info Associative array of entity info
	 */
	function __construct( $context, $info ) {
		parent::__construct( $context, 'option', $info );
	}

	/**
	 * Get a list of localisable message names. This is used to provide the
	 * translate subpage with a list of messages to localise.
	 * @return array
	 */
	function getMessageNames() {
		return [ 'text' ];
	}
}
