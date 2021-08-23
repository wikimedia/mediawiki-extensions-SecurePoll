<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Language;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extensions\SecurePoll\User\Auth;
use MediaWiki\Extensions\SecurePoll\User\Voter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserOptionsLookup;
use Message;
use User;

/**
 * Parent class for Special:SecurePoll subpages.
 */
abstract class ActionPage {
	public const LOG_TYPE_ADDADMIN = 0;
	public const LOG_TYPE_REMOVEADMIN = 1;
	public const LOG_TYPE_VIEWVOTES = 2;

	/** @var SpecialSecurePoll */
	public $specialPage;
	/** @var Election */
	public $election;
	/** @var Auth */
	public $auth;
	/** @var Context */
	public $context;
	/** @var UserOptionsLookup */
	protected $userOptionsLookup;
	/** @var LanguageFallback */
	protected $languageFallback;
	/** @var Language */
	public $userLang;

	/**
	 * Constructor.
	 * @param SpecialSecurePoll $specialPage
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
	 * @param Voter|User $user
	 * @param Election $election
	 */
	public function initLanguage( $user, $election ) {
		$uselang = $this->specialPage->getRequest()->getVal( 'uselang' );
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$languageFallback = $services->getLanguageFallback();
		$languageFactory = $services->getLanguageFactory();
		if ( $uselang !== null ) {
			$userLang = $uselang;
		} elseif ( $user instanceof Voter ) {
			$userLang = $user->getLanguage();
		} else {
			$userLang = $userOptionsLookup->getOption( $user, 'language' );
		}

		$languages = array_merge(
			[ $userLang ],
			$languageFallback->getAll( $userLang )
		);

		if ( !in_array( $election->getLanguage(), $languages ) ) {
			$languages[] = $election->getLanguage();
		}
		if ( !in_array( 'en', $languages ) ) {
			$languages[] = 'en';
		}
		$this->context->setLanguages( $languages );
		$this->userLang = $languageFactory->getLanguage( $userLang );
	}

	/**
	 * Get the current language. Call this after initLanguage() to get the
	 * voter language on the vote subpage.
	 *
	 * @return Language
	 */
	public function getUserLang() {
		if ( $this->userLang ) {
			return $this->userLang;
		} else {
			return $this->specialPage->getLanguage();
		}
	}

	public function setUserOptionsLookup( $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	public function setLanguageFallback( $languageFallback ) {
		$this->languageFallback = $languageFallback;
	}

	/**
	 * Relay for SpecialPage::msg
	 * @param string ...$args
	 * @return Message
	 */
	protected function msg( ...$args ) {
		return call_user_func_array(
			[
				$this->specialPage,
				'msg'
			],
			$args
		);
	}
}
