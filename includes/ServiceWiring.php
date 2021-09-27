<?php

namespace MediaWiki\Extensions\SecurePoll;

use MediaWiki\Extensions\SecurePoll\Hooks\HookRunner;
use MediaWiki\MediaWikiServices;

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
