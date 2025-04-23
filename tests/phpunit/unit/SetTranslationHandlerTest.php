<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Rest\SetTranslationHandler;
use MediaWiki\Rest\Handler;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\ParamValidator\ParamValidator;

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

	public function testBodyParamSettings() {
		$handler = $this->newServiceInstance( SetTranslationHandler::class, [] );
		$bodyParamSettings = $handler->getBodyParamSettings();

		// test data
		$this->assertArrayHasKey( 'data', $bodyParamSettings );
		$dataParam = $bodyParamSettings['data'];

		$this->assertArrayHasKey( Handler::PARAM_SOURCE, $dataParam );
		$this->assertSame( 'body', $dataParam[Handler::PARAM_SOURCE] );

		$this->assertArrayHasKey( ParamValidator::PARAM_REQUIRED, $dataParam );
		$this->assertTrue( $dataParam[ParamValidator::PARAM_REQUIRED], 'data param should be required' );

		$this->assertArrayHasKey( ParamValidator::PARAM_TYPE, $dataParam );
		$this->assertSame( 'array', $dataParam[ParamValidator::PARAM_TYPE] );
	}
}
