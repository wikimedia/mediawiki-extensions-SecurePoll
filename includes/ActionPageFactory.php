<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\Pages\ArchivedPage;
use MediaWiki\Extension\SecurePoll\Pages\ArchivePage;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Extension\SecurePoll\Pages\DeleteTallyPage;
use MediaWiki\Extension\SecurePoll\Pages\DetailsPage;
use MediaWiki\Extension\SecurePoll\Pages\DumpPage;
use MediaWiki\Extension\SecurePoll\Pages\EntryPage;
use MediaWiki\Extension\SecurePoll\Pages\ListPage;
use MediaWiki\Extension\SecurePoll\Pages\LoginPage;
use MediaWiki\Extension\SecurePoll\Pages\MessageDumpPage;
use MediaWiki\Extension\SecurePoll\Pages\TallyListPage;
use MediaWiki\Extension\SecurePoll\Pages\TallyPage;
use MediaWiki\Extension\SecurePoll\Pages\TranslatePage;
use MediaWiki\Extension\SecurePoll\Pages\UnarchivePage;
use MediaWiki\Extension\SecurePoll\Pages\VotePage;
use MediaWiki\Extension\SecurePoll\Pages\VoterEligibilityPage;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\User\Options\UserOptionsLookup;
use Wikimedia\ObjectFactory\ObjectFactory;

class ActionPageFactory {
	/**
	 * List of page names to the subclass of ActionPage which handles them.
	 */
	private const PAGE_LIST = [
		'archive' => [
			'class' => ArchivePage::class,
			'services' => [
				'JobQueueGroup'
			],
		],
		'archived' => [
			'class' => ArchivedPage::class,
			'services' => [
				'LinkRenderer',
				'DBLoadBalancer',
			],
		],
		'create' => [
			'class' => CreatePage::class,
			'services' => [
				'DBLoadBalancerFactory',
				'LanguageNameUtils',
				'UserFactory',
				'PageUpdaterFactory',
			],
		],
		'edit' => [
			'class' => CreatePage::class,
			'services' => [
				'DBLoadBalancerFactory',
				'LanguageNameUtils',
				'UserFactory',
				'PageUpdaterFactory',
			],
		],
		'details' => [
			'class' => DetailsPage::class,
			'services' => [
				'JobQueueGroup',
			],
		],
		'dump' => [
			'class' => DumpPage::class,
		],
		'entry' => [
			'class' => EntryPage::class,
			'services' => [
				'LinkRenderer',
				'DBLoadBalancer',
			],
		],
		'list' => [
			'class' => ListPage::class,
			'services' => [
				'JobQueueGroup',
			],
		],
		'login' => [
			'class' => LoginPage::class,
		],
		'msgdump' => [
			'class' => MessageDumpPage::class,
		],
		'tallies' => [
			'class' => TallyListPage::class,
			'services' => [
				'LinkRenderer',
				'DBLoadBalancer',
				'JobQueueGroup',
			],
		],
		'tally' => [
			'class' => TallyPage::class,
			'pattern' => '/^tallies\/\d+\/result\/\d+$/',
			'services' => [
				'LinkRenderer',
				'DBLoadBalancer',
			],
		],
		'deletetally' => [
			'class' => DeleteTallyPage::class,
			'pattern' => '/^tallies\/\d+\/delete\/\d+$/',
			'services' => [
				'LinkRenderer',
				'JobQueueGroup',
			],
		],
		'translate' => [
			'class' => TranslatePage::class,
			'services' => [
				'LanguageNameUtils',
				'SecurePoll.TranslationRepo'
			],
		],
		'unarchive' => [
			'class' => UnarchivePage::class,
			'services' => [
				'JobQueueGroup'
			],
		],
		'vote' => [
			'class' => VotePage::class,
			'services' => [
				'DBLoadBalancer',
				'HookContainer',
			],
		],
		'votereligibility' => [
			'class' => VoterEligibilityPage::class,
			'services' => [
				'DBLoadBalancerFactory',
				'LinkRenderer',
				'UserGroupManager',
				'WikiPageFactory',
			]
		],
	];

	public function __construct(
		private readonly ObjectFactory $objectFactory,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly LanguageFallback $languageFallback,
	) {
	}

	/**
	 * Find the page with a given name or matching URL and return it (or NULL).
	 *
	 * The URL is only matched against a pattern if a pattern is defined for
	 * the page in "self::PAGE_LIST". If one is not defined then the parameter
	 * is compared against the keys of that array.
	 *
	 * @param string $url url to an action page
	 * @param SpecialSecurePoll $specialPage
	 * @return ActionPage|null ActionPage object or null if the page doesn't exist
	 */
	public function getPage( $url, $specialPage ) {
		$pageName = null;
		foreach ( self::PAGE_LIST as $actionPageName => $props ) {
			if ( isset( $props['pattern'] ) && preg_match( $props['pattern'], $url ) ) {
				$pageName = $actionPageName;
				break;
			}
		}

		if ( $pageName === null ) {
			$params = explode( '/', $url );
			$pageName = array_shift( $params );

			if (
				empty( self::PAGE_LIST[$pageName] ) ||
				isset( self::PAGE_LIST[$pageName]['pattern'] )
			) {
				return null;
			}
		}

		/** @var ActionPage $page */
		// ObjectFactory::createObject accepts an array, not just a callable (phan bug)
		// @phan-suppress-next-line PhanTypeInvalidCallableArraySize
		$page = $this->objectFactory->createObject(
			self::PAGE_LIST[$pageName],
			[
				'allowClassName' => true,
				'allowCallable' => true,
				'extraArgs' => [ $specialPage ],
			]
		);

		// These are needed for standard parts of ActionPage, so we don't
		// want every single subclass to need to declare them. (See: how
		// SpecialPageFactory handles this.)
		$page->setUserOptionsLookup( $this->userOptionsLookup );
		$page->setLanguageFallback( $this->languageFallback );
		return $page;
	}

	/**
	 * Returns an array of valid action pages.
	 *
	 * The structure of the returned page list is an associative array with the
	 * key being the name of the page as it appears in the URL, and the value
	 * being an array of attributes.
	 *
	 * Supported attributes include the page class, the services
	 * that need to be injected into it, and an optional regex URL pattern to
	 * replace the key being used as the action page URL.
	 *
	 * @return array[]
	 */
	public function getPageList() {
		return self::PAGE_LIST;
	}
}
