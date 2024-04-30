<?php

namespace MediaWiki\Extension\SecurePoll\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\TranslationRepo;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class SetTranslationHandler extends SimpleHandler {

	/** @var TranslationRepo */
	private $translationRepo;

	/**
	 * @param TranslationRepo $translationRepo
	 */
	public function __construct( $translationRepo ) {
		$this->translationRepo = $translationRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function run( $params ): Response {
		$request = $this->getRequest();

		$electionId = (int)$request->getPathParam( 'entityid' );
		$language = $request->getPathParam( 'language' );
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
}
