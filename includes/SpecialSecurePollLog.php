<?php

namespace MediaWiki\Extensions\SecurePoll;

use FormSpecialPage;

class SpecialSecurePollLog extends FormSpecialPage {
	/** @var Context */
	private $context;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'SecurePollLog', 'securepoll-create-poll' );
		$this->context = new Context();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		parent::execute( $par );

		$out = $this->getOutput();

		// Hide form until T271279
		$out->addModuleStyles( 'ext.securepoll.special' );

		$pager = new SecurePollLogPager( $this->context );
		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function requiresWrite() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		return false;
	}
}
