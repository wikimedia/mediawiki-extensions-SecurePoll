<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\UserFactory;

class SpecialSecurePollLog extends FormSpecialPage {
	/** @var Context */
	private $context;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct( UserFactory $userFactory ) {
		parent::__construct( 'SecurePollLog', 'securepoll-create-poll' );
		$this->context = new Context();
		$this->userFactory = $userFactory;
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
		$fields = [];

		$fields['type'] = [
			'name' => 'type',
			'type' => 'select',
			'options-messages' => [
				'securepolllog-form-type-option-all' => 'all',
				'securepolllog-form-type-option-voter' => 'voter',
				'securepolllog-form-type-option-admin' => 'admin',
			],
			'default' => 'all',
		];

		$fields['electionName'] = [
			'name' => 'election_name',
			'type' => 'text',
			'label-message' => 'securepolllog-form-electionname-label',
			'default' => '',
			'validation-callback' => [
				$this,
				'checkIfElectionExists',
			],
		];

		$fields['performer'] = [
			'name' => 'performer',
			'type' => 'user',
			'label-message' => 'securepolllog-form-performer-label',
			'exists' => true,
			'default' => '',
		];

		$fields['target'] = [
			'name' => 'target',
			'type' => 'user',
			'label-message' => 'securepolllog-form-target-label',
			'exists' => true,
			'default' => '',
		];

		$fields['date'] = [
			'name' => 'date',
			'type' => 'date',
			'label-message' => 'securepolllog-form-date-label',
			'default' => '',
			'max' => gmdate( 'M-d-Y' ),
		];

		$fields['actions'] = [
			'name' => 'actions',
			'type' => 'radio',
			'cssclass' => 'securepolllog-actions-radio',
			'label-message' => 'securepolllog-form-action-label',
			'options-messages' => [
				'securepolllog-form-action-option-addadmin' => ActionPage::LOG_TYPE_ADDADMIN,
				'securepolllog-form-action-option-removeadmin' => ActionPage::LOG_TYPE_REMOVEADMIN,
				'securepolllog-form-action-option-both' => -1,
			],
			'default' => -1,
			'flatlist' => true,
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
			$requestParam = $value['name'];
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

		// Get date components
		if ( $data['date'] ) {
			$date = explode( '-', $data['date'] );
			$year = (int)$date[0];
			$month = (int)$date[1];
			$day = (int)$date[2];
		} else {
			$year = 0;
			$month = 0;
			$day = 0;
		}

		// Transform action codes to integer(s) in an array
		// (to match LOG_TYPE_ADDADMIN and LOG_TYPE_REMOVEADMIN)
		if ( (int)$data['actions'] === -1 ) {
			$data['actions'] = [ ActionPage::LOG_TYPE_ADDADMIN, ActionPage::LOG_TYPE_REMOVEADMIN ];
		} else {
			$data['actions'] = [ (int)$data['actions'] ];
		}

		$pager = new SecurePollLogPager(
			$this->context,
			$this->userFactory,
			$data['type'],
			$data['performer'],
			$data['type'] === 'voter' ? '' : $data['target'],
			$data['electionName'],
			$year,
			$month,
			$day,
			$data['actions']
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
