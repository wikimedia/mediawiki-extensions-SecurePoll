<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in SecurePollServiceWiringTest.php
// @codeCoverageIgnoreStart

return [
	'SecurePoll.ActionPageFactory' => static function ( MediaWikiServices $services ): ActionPageFactory {
		return new ActionPageFactory(
			$services->getObjectFactory(),
			$services->getUserOptionsLookup(),
			$services->getLanguageFallback()
		);
	},
	'SecurePoll.TranslationRepo' => static function ( MediaWikiServices $services ): TranslationRepo {
		return new TranslationRepo(
			$services->getDBLoadBalancerFactory(),
			$services->getWikiPageFactory(),
			$services->getMainConfig()
		);
	}
];

// @codeCoverageIgnoreEnd
