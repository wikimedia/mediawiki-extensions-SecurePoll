<?php

namespace MediaWiki\Extension\SecurePoll\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\ActionPageFactory;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\TranslationRepo;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class SetTranslationHandler extends SimpleHandler {

	/** @var TranslationRepo */
	private $translationRepo;

	/** @var ActionPageFactory */
	private $actionPageFactory;

	public function __construct(
		TranslationRepo $translationRepo,
		ActionPageFactory $actionPageFactory
	) {
		$this->translationRepo = $translationRepo;
		$this->actionPageFactory = $actionPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function run( $params ): Response {
		$request = $this->getRequest();
		$electionId = (int)$request->getPathParam( 'entityid' );
		$language = $request->getPathParam( 'language' );

		// Stub out a SecurePoll page to gain access to the election context
		$page = new SpecialSecurePoll( $this->actionPageFactory );
		$securepollContext = $page->sp_context;
		$election = $securepollContext->getElection( $electionId );

		// Only allow admins of the election to translate
		$isAdmin = $election->isAdmin( $this->getAuthority() );
		if ( !$isAdmin ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securepoll-need-admin' ),
				403
			);
		}

		$body = $this->getValidatedBody();
		if ( !$body ) {
			throw new HttpException( 'No valid body' );
		}

		$context = RequestContext::getMain();
		$user = $context->getUser();
		$sp_context = new Context;
		$election = $sp_context->getElection( $electionId );
		if ( !$election ) {
			throw new HttpException( 'No valid election' );
		}

		$this->translationRepo->setTranslation(
			$election,
			$body['data'],
			$language,
			$user,
			''
		);

		return $this->getResponseFactory()->createJson( [
			'success' => true
		] );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'entityid' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'language' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'data' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'array'
			]
		];
	}
}
