<?php
/**
 *
 *
 * Created on Jul 7, 2015
 *
 * Copyright © 2015 Frances Hocutt "<Firstinitial><Lastname>@wikimedia.org"
 *
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
use MediaWiki\Extension\SecurePoll\ActionPageFactory;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to facilitate striking/unstriking SecurePoll votes.
 *
 * @ingroup API
 */
class ApiStrikeVote extends ApiBase {
	/** @var ActionPageFactory */
	private $actionPageFactory;

	/**
	 * @param ApiMain $apiMain
	 * @param string $moduleName
	 * @param ActionPageFactory $actionPageFactory
	 */
	public function __construct(
		ApiMain $apiMain,
		$moduleName,
		ActionPageFactory $actionPageFactory
	) {
		parent::__construct( $apiMain, $moduleName );
		$this->actionPageFactory = $actionPageFactory;
	}

	/**
	 * Strike or unstrike a vote.
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$option = $params['option'];
		$voteid = $params['voteid'];
		$reason = $params['reason'];

		// FIXME: thoughts on whether error checks should go here or in strike()?
		// if not logged in: fail
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError(
				'apierror-securepoll-mustbeloggedin-strikevote',
				'notloggedin'
			);
		}

		// see if vote exists
		// (using SpecialPageFactory gets us bad type hints for phan here)
		$page = new SpecialSecurePoll( $this->actionPageFactory );
		$context = $page->sp_context;
		$db = $context->getDB();
		$row = $db->newSelectQueryBuilder()
			->select( 'elections.*' )
			->from( 'securepoll_votes' )
			->join( 'securepoll_elections', 'elections', 'vote_election=el_entity' )
			->where( [ 'vote_id' => $voteid ] )
			->caller( __METHOD__ )
			->fetchRow();

		// if no vote: fail
		if ( !$row ) {
			$this->dieWithError(
				[
					'apierror-securepoll-badvoteid',
					$voteid
				],
				'novote'
			);
		}

		// strike the vote
		$subpage = $page->getSubpage( 'list' );
		$subpage->election = $context->newElectionFromRow( $row );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$status = $subpage->strike( $option, $voteid, $reason );

		$result = [];
		if ( $status->isGood() ) {
			$result['status'] = 'good';
		} else {
			$this->dieStatus( $status );
		}
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	protected function getAllowedParams() {
		return [
			'option' => [
				ParamValidator::PARAM_TYPE => [
					'strike',
					'unstrike'
				],
				ParamValidator::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'voteid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=strikevote&option=strike&reason=duplication&voteid=1&token=123ABC' =>
				'apihelp-strikevote-example-strike',
			'action=strikevote&option=unstrike&reason=mistake&voteid=1&token=123ABC' =>
				'apihelp-strikevote-example-unstrike',
		];
	}
}
