<?php

namespace MediaWiki\Extensions\SecurePoll\HookHandler;

use Config;
use MediaWiki\Extensions\SecurePoll\SpecialSecurePollLog;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class SpecialPageHandler implements SpecialPage_initListHook {
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
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
}
