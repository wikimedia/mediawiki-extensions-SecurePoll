<?php

namespace MediaWiki\Extension\SecurePoll\Hooks;

use MediaWiki\Extension\SecurePoll\Pages\VotePage;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "SecurePoll_JumpUrl" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface SecurePoll_JumpUrlHook {
	/**
	 * This hook is called when a user is told he needs to jump to another wiki to cast
	 * their vote.
	 *
	 * @since 1.38
	 *
	 * @param VotePage $page
	 * @param string &$url
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSecurePoll_JumpUrl(
		VotePage $page,
		string &$url
	);
}
