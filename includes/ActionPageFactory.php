<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\Pages\ArchivedPage;
use MediaWiki\Extension\SecurePoll\Pages\ArchivePage;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Extension\SecurePoll\Pages\DetailsPage;
use MediaWiki\Extension\SecurePoll\Pages\DumpPage;
use MediaWiki\Extension\SecurePoll\Pages\EntryPage;
use MediaWiki\Extension\SecurePoll\Pages\ListPage;
use MediaWiki\Extension\SecurePoll\Pages\LoginPage;
use MediaWiki\Extension\SecurePoll\Pages\MessageDumpPage;
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
				'UserGroupManager',
				'LanguageNameUtils',
				'WikiPageFactory',
				'UserFactory',
			],
		],
		'edit' => [
			'class' => CreatePage::class,
			'services' => [
				'DBLoadBalancerFactory',
				'UserGroupManager',
				'LanguageNameUtils',
				'WikiPageFactory',
				'UserFactory',
			],
		],
		'details' => [
			'class' => DetailsPage::class,
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
		'tally' => [
			'class' => TallyPage::class,
			'services' => [
				'DBLoadBalancer',
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
				'TitleFactory',
				'UserGroupManager',
				'WikiPageFactory',
			]
		],
	];

	/** @var ObjectFactory */
	private $objectFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LanguageFallback */
	private $languageFallback;

	/**
	 * @param ObjectFactory $objectFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		ObjectFactory $objectFactory,
		UserOptionsLookup $userOptionsLookup,
		LanguageFallback $languageFallback
	) {
		$this->objectFactory = $objectFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->languageFallback = $languageFallback;
	}

	/**
	 * Find the object with a given name and return it (or NULL)
	 *
	 * @param string $name Action page name
	 * @param SpecialSecurePoll $specialPage
	 * @return ActionPage|null ActionPage object or null if the page doesn't exist
	 */
	public function getPage( $name, $specialPage ) {
		if ( empty( self::PAGE_LIST[$name] ) ) {
			return null;
		}
		/** @var ActionPage $page */
		// ObjectFactory::createObject accepts an array, not just a callable (phan bug)
		// @phan-suppress-next-line PhanTypeInvalidCallableArrayKey
		$page = $this->objectFactory->createObject(
			self::PAGE_LIST[$name],
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
	 * Returns a list of action page names.
	 *
	 * @return string[]
	 */
	public function getNames() {
		return array_keys( self::PAGE_LIST );
	}
}
