<?php

/**
 * Parent class for Special:SecurePoll subpages.
 */
abstract class SecurePoll_Page {
	public $parent, $election, $auth, $user;
	public $context;

	/**
	 * Constructor.
	 * @param $parent SecurePollPage
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;
		$this->context = $parent->sp_context;
	}

	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	abstract public function execute( $params );

	/**
	 * Internal utility function for initializing the global entity language
	 * fallback sequence.
	 */
	public function initLanguage( $user, $election ) {
		$uselang = $this->parent->getRequest()->getVal( 'uselang' );
		if ( $uselang !== null ) {
			$userLang = $uselang;
		} elseif ( $user instanceof SecurePoll_Voter ) {
			$userLang = $user->getLanguage();
		} else {
			$userLang = $user->getOption( 'language' );
		}

		$languages = array_merge(
			array( $userLang ),
			Language::getFallbacksFor( $userLang ) );

		if ( !in_array( $election->getLanguage(), $languages ) ) {
			$languages[] = $election->getLanguage();
		}
		if ( !in_array( 'en', $languages ) ) {
			$languages[] = 'en';
		}
		$this->context->setLanguages( $languages );
	}

	/**
	 * Relay for SpecialPage::msg
	 */
	protected function msg( /* args */ ) {
		return call_user_func_array( array( $this->parent, 'msg' ), func_get_args() );
	}
}
