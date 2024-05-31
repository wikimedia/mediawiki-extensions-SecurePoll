<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Content\JsonContent;

/**
 * SecurePoll Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup SecurePoll
 *
 * @author Brad Jorsch <bjorsch@wikimedia.org>
 */
class SecurePollContent extends JsonContent {
	public function __construct( $text, $modelId = 'SecurePoll' ) {
		parent::__construct( $text, $modelId );
	}
}
