<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Rest\SetTranslationHandler;
use MediaWiki\Rest\Handler;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @group SecurePoll
 *
 * @covers \MediaWiki\Extension\SecurePoll\Rest\SetTranslationHandler
 */
class SetTranslationHandlerTest extends MediaWikiUnitTestCase {
	use HandlerTestTrait;
	use MockServiceDependenciesTrait;

	public function testParamSettings() {
		$handler = $this->newServiceInstance( SetTranslationHandler::class, [] );
		$paramSettings = $handler->getParamSettings();

		// test entityid
		$this->assertArrayHasKey( 'entityid', $paramSettings );
		$entityidParam = $paramSettings['entityid'];
		$this->assertArrayHasKey( Handler::PARAM_SOURCE, $entityidParam );
		$this->assertSame( 'path', $entityidParam[Handler::PARAM_SOURCE] );

		// test language
		$this->assertArrayHasKey( 'language', $paramSettings );
		$languageParam = $paramSettings['language'];
		$this->assertArrayHasKey( Handler::PARAM_SOURCE, $languageParam );
		$this->assertSame( 'path', $languageParam[Handler::PARAM_SOURCE] );
	}
}
