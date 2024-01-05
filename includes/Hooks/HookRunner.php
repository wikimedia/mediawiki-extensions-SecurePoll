<?php

namespace MediaWiki\Extension\SecurePoll\Hooks;

use MediaWiki\Extension\SecurePoll\Pages\VotePage;
use MediaWiki\Extension\SecurePoll\User\LocalAuth;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\User;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
/**
 * Run hooks provided by SecurePoll.
 *
 * @author Zabe
 * @since 1.38
 */
class HookRunner implements
	SecurePoll_GetUserParamsHook,
	SecurePoll_JumpUrlHook
{
	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onSecurePoll_GetUserParams(
		LocalAuth $localAuth,
		User $user,
		array &$params
	) {
		$this->hookContainer->run(
			'SecurePoll_GetUserParams',
			[
				$localAuth,
				$user,
				&$params
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onSecurePoll_JumpUrl(
		VotePage $page,
		string &$url
	) {
		$this->hookContainer->run(
			'SecurePoll_JumpUrl',
			[
				$page,
				&$url
			]
		);
	}
}
