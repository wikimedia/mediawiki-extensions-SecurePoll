<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MessageLocalizer;
use Wikimedia\Message\MessageSpecifier;

/**
 * Parent class for Special:SecurePoll subpages.
 */
abstract class ActionPage implements MessageLocalizer {
	public const LOG_TYPE_ADDADMIN = 0;
	public const LOG_TYPE_REMOVEADMIN = 1;
	public const LOG_TYPE_VIEWVOTES = 2;
	public const LOG_TYPE_VIEWVOTEDETAILS = 3;

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

	public function setUserOptionsLookup( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	public function setLanguageFallback( LanguageFallback $languageFallback ) {
		$this->languageFallback = $languageFallback;
	}

	/**
	 * Relay for SpecialPage::msg
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys,
	 *   or a MessageSpecifier.
	 * @param mixed ...$params Normal message parameters
	 * @return Message
	 */
	public function msg( $key, ...$params ) {
		return $this->specialPage->msg( $key, ...$params );
	}

	protected function showRedirectMessage( string $action, array $params ): bool {
		$jumpUrl = $this->election->getProperty( 'jump-url' );
		if ( $jumpUrl ) {
			$jumpId = $this->election->getProperty( 'jump-id' );
			if ( !$jumpId ) {
				throw new InvalidDataException( 'Configuration error: no jump-id' );
			}
			$jumpUrl .= "/$action/$jumpId";
			if ( count( $params ) > 1 ) {
				$jumpUrl .= '/' . implode( '/', array_slice( $params, 1 ) );
			}

			$wiki = $this->election->getProperty( 'main-wiki' );
			if ( $wiki ) {
				$wiki = WikiMap::getWikiName( $wiki );
			} else {
				$wiki = $this->msg( 'securepoll-edit-redirect-otherwiki' )->text();
			}

			$this->specialPage->getOutput()->addWikiMsg(
				// Messages: securepoll-edit-redirect, securepoll-list-redirect
				"securepoll-$action-redirect",
				Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
			);
			return true;
		}
		return false;
	}
}
