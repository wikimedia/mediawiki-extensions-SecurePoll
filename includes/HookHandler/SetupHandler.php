<?php

namespace MediaWiki\Extension\SecurePoll\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\SecurePoll\SpecialSecurePollLog;
use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Permissions\Hook\TitleQuickPermissionsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class SetupHandler implements
	CanonicalNamespacesHook,
	SpecialPage_initListHook,
	TitleQuickPermissionsHook
{
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function onCanonicalNamespaces( &$namespaces ) {
		if ( $this->config->get( 'SecurePollUseNamespace' ) ) {
			$namespaces[NS_SECUREPOLL] = 'SecurePoll';
			$namespaces[NS_SECUREPOLL_TALK] = 'SecurePoll_talk';
		}
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/**
	 * @inheritDoc
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->config->get( 'SecurePollUseLogging' ) ) {
			$list['SecurePollLog'] = [
				'class' => SpecialSecurePollLog::class,
				'services' => [
					'UserFactory',
				]
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onTitleQuickPermissions(
		$title, $user, $action, &$errors, $doExpensiveQueries, $short
	) {
		if ( $this->config->get( 'SecurePollUseNamespace' ) && $title->getNamespace() === NS_SECUREPOLL &&
			$action !== 'read'
		) {
			$errors[] = [ 'securepoll-ns-readonly' ];

			return false;
		}

		return true;
	}
}
