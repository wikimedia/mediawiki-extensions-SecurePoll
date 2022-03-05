<?php

namespace MediaWiki\Extension\SecurePoll\Entities;

use MediaWiki\Extension\SecurePoll\Context;

/**
 * Class representing the options which the voter can choose from when they are
 * answering a question.
 */
class Option extends Entity {
	/**
	 * Constructor
	 * @param Context $context
	 * @param array $info Associative array of entity info
	 */
	public function __construct( $context, $info ) {
		parent::__construct( $context, 'option', $info );
	}

	/**
	 * Get a list of localisable message names. This is used to provide the
	 * translate subpage with a list of messages to localise.
	 * @return array
	 */
	public function getMessageNames() {
		return [ 'text' ];
	}
}
