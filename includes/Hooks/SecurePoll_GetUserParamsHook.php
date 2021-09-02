<?php

namespace MediaWiki\Extensions\SecurePoll\Hooks;

use MediaWiki\Extensions\SecurePoll\User\LocalAuth;
use User;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "SecurePoll_GetUserParams" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface SecurePoll_GetUserParamsHook {
	/**
	 * This hook is called when voter parameters for a local User object are collected.
	 *
	 * @since 1.37
	 *
	 * @param LocalAuth $localAuth
	 * @param User $user
	 * @param array &$params
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSecurePoll_GetUserParams(
		LocalAuth $localAuth,
		User $user,
		array &$params
	);
}
