<?php

declare( strict_types=1 );

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

	/** @inheritDoc */
	public function getMessageNames() {
		return [ 'text' ];
	}
}
