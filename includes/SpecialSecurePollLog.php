<?php

namespace MediaWiki\Extensions\SecurePoll;

use FormSpecialPage;
use HTMLForm;

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

		$this->getOutput()->addModules( 'ext.securepoll.htmlform' );
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
		$prefix = $this->getMessagePrefix();
		$fields = [];

		$fields['type'] = [
			'name' => 'type',
			'type' => 'select',
			'options-messages' => [
				$prefix . '-form-type-option-all' => 'all',
				$prefix . '-form-type-option-voter' => 'voter',
				$prefix . '-form-type-option-admin' => 'admin',
			],
			'default' => 'all',
		];

		$fields['electionName'] = [
			'name' => 'election_name',
			'type' => 'text',
			'label-message' => $prefix . '-form-electionname-label',
			'default' => '',
			'validation-callback' => [
				$this,
				'checkIfElectionExists',
			],
		];

		$fields['target'] = [
			'name' => 'target',
			'type' => 'user',
			'label-message' => $prefix . '-form-target-label',
			'exists' => true,
			'default' => '',
		];

		$this->setFormDefaults( $fields );

		return $fields;
	}

	/**
	 * Set default values for form fields if the form has already
	 * been submitted.
	 *
	 * @param array &$fields
	 */
	private function setFormDefaults( &$fields ) {
		$request = $this->getRequest();
		if ( !$request->wasPosted() ) {
			return;
		}

		foreach ( $fields as $fieldname => $value ) {
			$requestParam = $fields[$fieldname]['name'];
			if ( $request->getVal( $requestParam ) !== null ) {
				$fields[$fieldname]['default'] = $request->getVal( $requestParam );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setMethod( 'get' );
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$form = $this->getForm();
		$form->prepareForm();
		$form->displayForm( true );

		$pager = new SecurePollLogPager(
			$this->context,
			$data['type'],
			$data['type'] === 'voter' ? '' : $data['target'],
			$data['electionName']
		);

		$this->getOutput()->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
		return true;
	}

	/**
	 * Check that the election id exists
	 *
	 * Given a title, return the id of the election or false if it doesn't exist
	 *
	 * @internal For use by the HTMLFormField
	 * @param string $value
	 * @return bool|string true on success, string on error
	 */
	public function checkIfElectionExists( $value ) {
		$election = $this->context->getElectionByTitle( $value );
		if ( !$value || $election ) {
			return true;
		}
		return $this->msg(
			'securepolllog-election-does-not-exist',
			$value
		)->parse();
	}
}
