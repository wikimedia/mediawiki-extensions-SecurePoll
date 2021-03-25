<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Exception;
use HTMLForm;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\MemoryStore;
use OOUIHTMLForm;
use Status;
use WebRequestUpload;

/**
 * A subpage for tallying votes and producing results
 */
class TallyPage extends ActionPage {
	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$user = $this->specialPage->getUser();
		$request = $this->specialPage->getRequest();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );

			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

			return;
		}
		$this->initLanguage( $user, $this->election );
		$out->setPageTitle(
			$this->msg( 'securepoll-tally-title', $this->election->getMessage( 'title' ) )->text()
		);
		if ( !$this->election->isAdmin( $user ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );

			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-tally-not-finished' );

			return;
		}

		if (
			$this->election->getVotesCount() > 100 &&
			$this->election->getCrypt()
		) {
			$out->addWikiMsg( 'securepoll-tally-too-large' );

			return;
		}

		$form = $this->createForm();
		$form->show();
	}

	/**
	 * Create a form which, when submitted, shows a tally for the election.
	 *
	 * @return OOUIHTMLForm
	 */
	private function createForm() {
		$formFields = $this->getCryptDescriptors();

		$form = HTMLForm::factory(
			'ooui',
			$formFields,
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitTextMsg( 'securepoll-tally-upload-submit' )
			->setSubmitCallback( [ $this, 'submitForm' ] );

		return $form;
	}

	/**
	 * Get any crypt-specific descriptors for the form.
	 *
	 * @return array
	 */
	private function getCryptDescriptors() : array {
		$crypt = $this->election->getCrypt();

		if ( !$crypt ) {
			return [];
		}
		$formFields = [];
		if ( !$crypt->canDecrypt() ) {
			$formFields += $crypt->getTallyDescriptors();
		}

		$formFields += [
			'tally_file' => [
				'type' => 'file',
				'name' => 'tally_file',
				'help-message' => 'securepoll-tally-form-file-help',
				'label-message' => 'securepoll-tally-form-file-label',
			],
		];

		return $formFields;
	}

	/**
	 * Show a tally, either from the database or from an uploaded file.
	 *
	 * @internal Submit callback for the HTMLForm
	 * @param array $data Data from the form fields
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	public function submitForm( array $data ) {
		$upload = $this->specialPage->getRequest()->getUpload( 'tally_file' );
		if ( !$upload->exists()
			|| !is_uploaded_file( $upload->getTempName() )
			|| !$upload->getSize()
		) {
			return $this->submitLocal( $data );
		}
		return $this->submitUpload( $data, $upload );
	}

	/**
	 * Show a tally of the local DB
	 *
	 * @param array $data Data from the form fields
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	private function submitLocal( array $data ) {
		$this->updateContextForCrypt( $this->election, $data );
		$status = $this->election->tally();
		if ( !$status->isOK() ) {
			return $status->getMessage();
		}
		$tallier = $status->value;
		$this->specialPage->getOutput()->addHTML( $tallier->getHtmlResult() );
		return true;
	}

	/**
	 * Show a tally of the results in the uploaded file
	 *
	 * @param array $data Data from the form fields
	 * @param WebRequestUpload $upload
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	private function submitUpload( array $data, WebRequestUpload $upload ) {
		$out = $this->specialPage->getOutput();

		$context = Context::newFromXmlFile( $upload->getTempName() );
		if ( !$context ) {
			return [ 'securepoll-dump-corrupt' ];
		}
		$store = $context->getStore();
		if ( !$store instanceof MemoryStore ) {
			$class = get_class( $store );
			throw new Exception(
				"Expected instance of MemoryStore, got $class instead"
			);
		}
		$electionIds = $store->getAllElectionIds();
		$election = $context->getElection( reset( $electionIds ) );

		$this->updateContextForCrypt( $election, $data );
		$status = $election->tally();
		if ( !$status->isOK() ) {
			return [ [ 'securepoll-tally-upload-error', $status->getMessage() ] ];
		}
		$tallier = $status->value;
		$out->addHTML( $tallier->getHtmlResult() );
		return true;
	}

	/**
	 * Update the context of the election to be tallied with any information
	 * not stored in the database that is needed for decryption.
	 *
	 * @param Election $election The election to be tallied
	 * @param array $data Form data
	 */
	private function updateContextForCrypt( Election $election, array $data ) : void {
		$crypt = $election->getCrypt();
		if ( $crypt ) {
			$crypt->updateTallyContext( $election->context, $data );
		}
	}

	public function getTitle() {
		return $this->specialPage->getPageTitle( 'tally/' . $this->election->getId() );
	}
}
