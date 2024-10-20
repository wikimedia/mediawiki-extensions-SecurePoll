<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\SecurePoll\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use MediaWiki\Extension\SecurePoll\User\RemoteMWAuth;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to authenticate jump-wiki user.
 *
 * @ingroup API
 */
class ApiSecurePollAuth extends ApiBase {
	/** @var UserFactory */
	private $userFactory;

	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		UserFactory $userFactory
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->userFactory = $userFactory;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$user = $this->userFactory->newFromId( $params['id'] );
		if ( !$user->isRegistered() ) {
			$this->dieWithError(
				'securepoll-api-no-user'
			);
		}
		$token = RemoteMWAuth::encodeToken( $user->getToken() );
		if ( !hash_equals( $params['token'], $token ) ) {
			$this->dieWithError(
				'securepoll-api-token-mismatch'
			);
		}

		$context = new Context();
		/** @var LocalAuth $auth */
		$auth = $context->newAuth( 'local' );
		$result = $auth->getUserParams( $user );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return [
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=securepollauth&token=123ABC&id=1&format=json' =>
				'apihelp-securepollauth-example-auth',
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isInternal() {
		return true;
	}
}
