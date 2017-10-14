<?php

/**
 * Parent class for Special:SecurePoll subpages.
 */
abstract class SecurePoll_ActionPage {
	/** @var SecurePoll_SpecialSecurePoll  */
	public $specialPage;
	/** @var SecurePoll_Election */
	public $election;
	/** @var SecurePoll_Auth */
	public $auth;
	/** @var User */
	public $user;
	/** @var SecurePoll_Context */
	public $context;

	/**
	 * Constructor.
	 * @param SecurePoll_SpecialSecurePoll $specialPage
	 */
	public function __construct( $specialPage ) {
		$this->specialPage = $specialPage;
		$this->context = $specialPage->sp_context;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	abstract public function execute( $params );

	/**
	 * Internal utility function for initializing the global entity language
	 * fallback sequence.
	 * @param User $user
	 * @param SecurePoll_Election $election
	 */
	public function initLanguage( $user, $election ) {
		$uselang = $this->specialPage->getRequest()->getVal( 'uselang' );
		if ( $uselang !== null ) {
			$userLang = $uselang;
		} elseif ( $user instanceof SecurePoll_Voter ) {
			$userLang = $user->getLanguage();
		} else {
			$userLang = $user->getOption( 'language' );
		}

		$languages = array_merge(
			[ $userLang ],
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
	 * @return string
	 */
	protected function msg( /* args */ ) {
		return call_user_func_array( [ $this->specialPage, 'msg' ], func_get_args() );
	}
}
