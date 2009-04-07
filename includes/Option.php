<?php

/**
 * Class representing the options which the voter can choose from when they are
 * answering a question.
 */
class SecurePoll_Option extends SecurePoll_Entity {
	/**
	 * Create a new option from a DB row
	 * @param $row object
	 */
	static function newFromRow( $row ) {
		return new self( $row->op_entity );
	}

	/**
	 * Constructor, from entity ID
	 * @param $id integer
	 */
	function __construct( $id ) {
		parent::__construct( 'option', $id );
	}

	/**
	 * Get a list of localisable message names. This is used to provide the 
	 * translate subpage with a list of messages to localise.
	 */
	function getMessageNames() {
		return array( 'text' );
	}
}
