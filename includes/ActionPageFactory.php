<?php

namespace MediaWiki\Extensions\SecurePoll;

use MediaWiki\Extensions\SecurePoll\Pages\ActionPage;
use MediaWiki\Extensions\SecurePoll\Pages\CreatePage;
use MediaWiki\Extensions\SecurePoll\Pages\DetailsPage;
use MediaWiki\Extensions\SecurePoll\Pages\DumpPage;
use MediaWiki\Extensions\SecurePoll\Pages\EntryPage;
use MediaWiki\Extensions\SecurePoll\Pages\ListPage;
use MediaWiki\Extensions\SecurePoll\Pages\LoginPage;
use MediaWiki\Extensions\SecurePoll\Pages\MessageDumpPage;
use MediaWiki\Extensions\SecurePoll\Pages\TallyPage;
use MediaWiki\Extensions\SecurePoll\Pages\TranslatePage;
use MediaWiki\Extensions\SecurePoll\Pages\VotePage;
use MediaWiki\Extensions\SecurePoll\Pages\VoterEligibilityPage;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\ObjectFactory;

class ActionPageFactory {
	/**
	 * List of page names to the subclass of ActionPage which handles them.
	 */
	private const PAGE_LIST = [
		'create' => [
			'class' => CreatePage::class,
			'services' => [
				'DBLoadBalancer',
			],
		],
		'edit' => [
			'class' => CreatePage::class,
			'services' => [
				'DBLoadBalancer',
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
		],
		'list' => [
			'class' => ListPage::class,
		],
		'login' => [
			'class' => LoginPage::class,
		],
		'msgdump' => [
			'class' => MessageDumpPage::class,
		],
		'tally' => [
			'class' => TallyPage::class,
		],
		'translate' => [
			'class' => TranslatePage::class,
		],
		'vote' => [
			'class' => VotePage::class,
			'services' => [
				'DBLoadBalancer',
				'HookContainer'
			],
		],
		'votereligibility' => [
			'class' => VoterEligibilityPage::class,
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
}
