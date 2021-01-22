<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Exception;
use HTMLForm;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\MemoryStore;
use OOUIHTMLForm;
use Status;

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

		$localForm = $this->createLocalForm();
		$uploadForm = $this->createUploadForm();

		$crypt = $this->election->getCrypt();
		if ( $crypt ) {
			if ( !$crypt->canDecrypt() ) {
				$out->addWikiMsg( 'securepoll-tally-no-key' );

				return;
			}

			if ( $request->wasPosted() ) {
				if ( $request->getVal( 'submit_upload' ) ) {
					$result = $this->trySubmitForm( $uploadForm );
					if ( $result !== true ) {
						$this->displayFormNoErrors( $localForm );
						$uploadForm->displayForm( $result );
					}
				} else {
					$result = $this->trySubmitForm( $localForm );
					if ( $result !== true ) {
						$localForm->displayForm( $result );
						$this->displayFormNoErrors( $uploadForm );
					}
				}
			} else {
				$out->addWikiMsg( 'securepoll-can-decrypt' );
				$localForm->show();
				$uploadForm->show();
			}
		} else {
			$localForm->show();
		}
	}

	/**
	 * Try to submit a form
	 *
	 * @param OOUIHTMLForm $form
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	private function trySubmitForm( $form ) {
		$form->prepareForm();
		return $form->tryAuthorizedSubmit();
	}

	/**
	 * Display a form without errors
	 *
	 * @param OOUIHTMLForm $form
	 */
	private function displayFormNoErrors( $form ) {
		$form->prepareForm();
		$form->displayForm( true );
	}

	/**
	 * Create a form which, when submitted, shows a tally for the results in the DB
	 *
	 * @return OOUIHTMLForm
	 */
	private function createLocalForm() {
		$form = HTMLForm::factory(
			'ooui',
			[],
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitTextMsg( 'securepoll-tally-local-submit' )
			->setSubmitName( 'submit_local' )
			->setSubmitCallback( [ $this, 'submitLocal' ] )
			->setWrapperLegend(
				$this->msg( 'securepoll-tally-local-legend' )->text()
			);

		return $form;
	}

	/**
	 * Create a form for upload of a record produced by the dump subpage
	 *
	 * @return OOUIHTMLForm
	 */
	private function createUploadForm() {
		$form = HTMLForm::factory(
			'ooui',
			[
				'tally_file' => [
					'type' => 'file',
					'name' => 'tally_file',
				],
			],
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitTextMsg( 'securepoll-tally-upload-submit' )
			->setSubmitName( 'submit_upload' )
			->setSubmitCallback( [ $this, 'submitUpload' ] )
			->setWrapperLegend(
				$this->msg( 'securepoll-tally-upload-legend' )->text()
			);

		return $form;
	}

	/**
	 * Show a tally of the local DB
	 *
	 * @internal Submit callback for the HTMLForm
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	public function submitLocal() {
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
	 * @internal Submit callback for the HTMLForm
	 * @return bool|string|array|Status As documented for HTMLForm::trySubmit
	 */
	public function submitUpload() {
		$out = $this->specialPage->getOutput();

		if ( !isset( $_FILES['tally_file'] ) || !is_uploaded_file(
				$_FILES['tally_file']['tmp_name']
			) || !$_FILES['tally_file']['size']
		) {
			return [ 'securepoll-no-upload' ];
		}
		$context = Context::newFromXmlFile( $_FILES['tally_file']['tmp_name'] );
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

		$status = $election->tally();
		if ( !$status->isOK() ) {
			return [ [ 'securepoll-tally-upload-error', $status->getMessage() ] ];
		}
		$tallier = $status->value;
		$out->addHTML( $tallier->getHtmlResult() );
		return true;
	}

	public function getTitle() {
		return $this->specialPage->getPageTitle( 'tally/' . $this->election->getId() );
	}
}
