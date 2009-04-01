<?php

class SecurePoll_Option extends SecurePoll_Entity {
	static function newFromRow( $row ) {
		return new self( $row->op_entity );
	}

	function __construct( $id ) {
		parent::__construct( 'option', $id );
	}

	function getMessageNames() {
		return array( 'text' );
	}
}
