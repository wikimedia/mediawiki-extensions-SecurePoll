<?php

namespace MediaWiki\Extension\SecurePoll\User;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Hooks\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * Authorization class for locally created accounts.
 * Certain functions in this class are also used for sending local voter
 * parameters to a remote SecurePoll installation.
 */
class LocalAuth extends Auth {
	/** @var HookRunner */
	private $hookRunner;

	/** @var MediaWikiServices */
	private $services;

	/**
	 * @inheritDoc
	 */
	public function __construct( $context ) {
		parent::__construct( $context );
		$this->hookRunner = new HookRunner(
			MediaWikiServices::getInstance()->getHookContainer()
		);
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 * Create a voter transparently, without user interaction.
	 * Sessions authorized against local accounts are created this way.
	 * @param Election $election
	 * @return Status
	 */
	public function autoLogin( $election ) {
		$user = RequestContext::getMain()->getUser();
		if ( !$user->isNamed() ) {
			return Status::newFatal( 'securepoll-not-logged-in' );
		}
		$params = $this->getUserParams( $user );
		$params['electionId'] = $election->getId();
		$qualStatus = $election->getQualifiedStatus( $params );
		if ( !$qualStatus->isOK() ) {
			return $qualStatus;
		}
		$voter = $this->getVoter( $params );

		return Status::newGood( $voter );
	}

	/**
	 * Get voter parameters for a local User object.
	 * @param User $user
	 * @return array
	 */
	public function getUserParams( $user ) {
		$block = $user->getBlock();
		$blockCounts = $this->getCentralBlockCount( $user );
		$server = $this->services->getMainConfig()->get( MainConfigNames::Server );

		$params = [
			'name' => $user->getName(),
			'type' => 'local',
			'domain' => preg_replace( '!.*/(.*)$!', '$1', $server ),
			'url' => $user->getUserPage()->getCanonicalURL(),
			'properties' => [
				'wiki' => WikiMap::getCurrentWikiId(),
				'blocked' => (bool)$block,
				'isSitewideBlocked' => $block ? $block->isSitewide() : null,
				'central-block-count' => $blockCounts['blockCount'],
				'central-sitewide-block-count' => $blockCounts['sitewideBlockCount'],
				'edit-count' => $user->getEditCount(),
				'bot' => $user->isAllowed( 'bot' ),
				'language' => $this->services
					->getUserOptionsLookup()->getOption( $user, 'language' ),
				'groups' => array_merge(
					$this->services->getUserGroupManager()->getUserGroups( $user ),
					$this->services->getUserGroupManager()->getUserImplicitGroups( $user ) ),
				'lists' => $this->getLists( $user ),
				'central-lists' => $this->getCentralLists( $user ),
				'registration' => $user->getRegistration(),
			]
		];

		$this->hookRunner->onSecurePoll_GetUserParams( $this, $user, $params );

		return $params;
	}

	/**
	 * Get voter parameters for a local User object, except without central block count.
	 *
	 * @param User $user
	 * @return array
	 */
	public function getUserParamsFast( $user ) {
		$server = $this->services->getMainConfig()->get( 'Server' );
		$block = $user->getBlock();
		$params = [
			'name' => $user->getName(),
			'type' => 'local',
			'domain' => preg_replace( '!.*/(.*)$!', '$1', $server ),
			'url' => $user->getUserPage()->getCanonicalURL(),
			'properties' => [
				'wiki' => WikiMap::getCurrentWikiId(),
				'blocked' => (bool)$block,
				'isSitewideBlocked' => $block ? $block->isSitewide() : null,
				'central-block-count' => 0,
				'edit-count' => $user->getEditCount(),
				'bot' => $user->isAllowed( 'bot' ),
				'language' => $this->services
					->getUserOptionsLookup()->getOption( $user, 'language' ),
				'groups' => $this->services
					->getUserGroupManager()->getUserGroups( $user ),
				'lists' => $this->getLists( $user ),
				'central-lists' => $this->getCentralLists( $user ),
				'registration' => $user->getRegistration(),
			]
		];

		$this->hookRunner->onSecurePoll_GetUserParams( $this, $user, $params );

		return $params;
	}

	/**
	 * Get the lists a given local user belongs to
	 * @param User $user
	 * @return array
	 */
	public function getLists( $user ) {
		$dbr = $this->context->getDB();
		return $dbr->newSelectQueryBuilder()
			->select( 'li_name' )
			->from( 'securepoll_lists' )
			->where( [ 'li_member' => $user->getId() ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Get the CentralAuth lists the user belongs to
	 * @param User $user
	 * @return array
	 */
	public function getCentralLists( $user ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			return [];
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->isAttached() ) {
			return [];
		}
		$caDbManager = MediaWikiServices::getInstance()->getService(
			'CentralAuth.CentralAuthDatabaseManager'
		);
		$dbc = $caDbManager->getCentralReplicaDB();
		return $dbc->newSelectQueryBuilder()
			->select( 'li_name' )
			->from( 'securepoll_lists' )
			->where( [ 'li_member' => $centralUser->getId() ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Checks how many central wikis the user is blocked on
	 * @param User $user
	 * @return array the number of wikis the user is blocked on, both partial and sitewide
	 */
	public function getCentralBlockCount( $user ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			return [
				'blockCount' => 0,
				'sitewideBlockCount' => 0,
			];
		}

		$centralUser = CentralAuthUser::getInstanceByName( $user->getName() );

		$attached = $centralUser->queryAttached();
		$blockCount = 0;
		$sitewideBlockCount = 0;

		foreach ( $attached as $data ) {
			if ( $data['blocked'] ) {
				$blockCount++;
				if ( $data['block-sitewide'] ) {
					$sitewideBlockCount++;
				}
			}
		}

		return [
			'blockCount' => $blockCount,
			'sitewideBlockCount' => $sitewideBlockCount,
		];
	}
}
