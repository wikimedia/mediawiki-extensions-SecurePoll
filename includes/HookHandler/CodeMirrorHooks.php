<?php

namespace MediaWiki\Extension\SecurePoll\HookHandler;

use MediaWiki\Extension\CodeMirror\Hooks;
use MediaWiki\Extension\CodeMirror\Hooks\CodeMirrorSpecialPageHook;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * All hooks from the CodeMirror extension which is optional to use with this extension.
 */
class CodeMirrorHooks implements CodeMirrorSpecialPageHook {
	/**
	 * @param SpecialPage $special
	 * @param array &$textareas
	 * @return bool
	 */
	public function onCodeMirrorSpecialPage( SpecialPage $special, array &$textareas ): bool {
		if ( $special->getName() === 'SecurePoll' ) {
			// Only match subpages of Special:SecurePoll/translate
			$title = $special->getContext()->getTitle();
			if ( $title && $title->isSubpageOf( $special->getPageTitle( 'translate' ) ) ) {
				$textareas = [
					Hooks::NO_PRIMARY_TEXTAREA,
					'.securepoll-translate-box > textarea'
				];
				return false;
			}
		}
		return true;
	}
}
