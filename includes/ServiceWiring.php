<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Hooks\HookRunner;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in SecurePollServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'SecurePoll.ActionPageFactory' => static function ( MediaWikiServices $services ) {
		return new ActionPageFactory(
			$services->getObjectFactory(),
			$services->getUserOptionsLookup(),
			$services->getLanguageFallback()
		);
	},
	'SecurePoll.HookRunner' => static function ( MediaWikiServices $services ) {
		return new HookRunner(
			$services->getHookContainer()
		);
	}
];

// @codeCoverageIgnoreEnd
