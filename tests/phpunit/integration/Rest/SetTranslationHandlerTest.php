<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Rest;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Rest\SetTranslationHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use SpecialPageTestBase;
use Wikimedia\Message\MessageValue;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Rest\SetTranslationHandler
 */
class SetTranslationHandlerTest extends SpecialPageTestBase {
	use HandlerTestTrait;

	private Election $election;

	protected function setUp(): void {
		parent::setUp();

		// Create an election to translate for
		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );

		$now = wfTimestamp();
		$tomorrow = wfTimestamp( TS_ISO_8601, $now + 86400 );
		$twoDaysLater = wfTimestamp( TS_ISO_8601, $now + 2 * 86400 );
		$params = [
			'wpelection_title' => 'Test Election',
			'wpquestions' => [
				[ 'text' => [ 'Question 1' ], 'options' => [ [ 'text' => 'Option 1' ] ] ]
			],
			'wpelection_startdate' => $tomorrow,
			'wpelection_enddate' => $twoDaysLater,
			'wpelection_type' => 'approval+plurality',
			'wpelection_crypt' => 'none',
			'wpreturn-url' => '',
		];

		$request = new FauxRequest( $params, true, );
		$request->setVal( 'wpproperty_admins', $this->getTestSysop()->getUser()->getName() );
		[ $html, $page ] = $this->executeSpecialPage(
			'create', $request, null, $this->getTestSysop()->getAuthority()
		);

		$this->election = ( new Context() )->getElectionByTitle( $params['wpelection_title'] );
		$questions = $this->election->getQuestions();

		$this->assertStringContainsString( '(securepoll-create-created-text)', $html );
		$this->assertSame( $params['wpelection_title'], $this->election->title );
		$this->assertCount( 1, $questions );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	protected function getHandler(): Handler {
		$services = $this->getServiceContainer();
		return SetTranslationHandler::factory(
			$services->getService( 'SecurePoll.TranslationRepo' ),
			$services->getService( 'SecurePoll.ActionPageFactory' )
		);
	}

	/**
	 * Convenience function to create a test request.
	 * @param string $entityid Id of the entity to translate
	 * @param string $language language of the translation
	 * @param array $body translation strings
	 * @return RequestData
	 */
	private static function getRequestData(
		string $entityid,
		string $language = 'qqx',
		array $body = []
	): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [
				'entityid' => $entityid,
				'language' => $language
			],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( [ 'data' => $body ] )
		] );
	}

	/**
	 * Convenience function to execute a request against the REST handler returned by
	 * {@link HandlerTestCase::getHandler}.
	 *
	 * @param RequestData $requestData Request options
	 * @param Authority $user The user to execute the request as
	 * @return ResponseInterface
	 */
	protected function executeWithUser( RequestData $requestData, Authority $user ): ResponseInterface {
		return $this->executeHandler(
			$this->getHandler(),
			$requestData,
			[],
			[],
			[],
			[],
			$user
		);
	}

	public function testRun(): void {
		$performer = $this->getTestSysop()->getAuthority();
		$request = self::getRequestData( $this->election->getId(), 'en', [] );
		$response = $this->executeWithUser( $request, $performer );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertTrue( $body[ 'success' ] );
	}

	public function testOnlyElectionAdminOfElectionCanRun(): void {
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'securepoll-need-admin' ),
				403
			)
		);

		$performer = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		$request = self::getRequestData( $this->election->getId(), 'en', [] );
		$response = $this->executeWithUser( $request, $performer );
	}
}
