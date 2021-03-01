<?php

namespace MediaWiki\Extensions\SecurePoll;

use MediaWiki\MediaWikiServices;

return [
	'SecurePoll.ActionPageFactory' => static function ( MediaWikiServices $services ) {
		return new ActionPageFactory(
			$services->getObjectFactory(),
			$services->getUserOptionsLookup(),
			$services->getLanguageFallback()
		);
	}
];
