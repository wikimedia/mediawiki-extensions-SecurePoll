<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Exception;
use HTMLForm;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\MemoryStore;

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

		$crypt = $this->election->getCrypt();
		if ( $crypt ) {
			if ( !$crypt->canDecrypt() ) {
				$out->addWikiMsg( 'securepoll-tally-no-key' );

				return;
			}

			if ( $request->wasPosted() ) {
				if ( $request->getVal( 'submit_upload' ) ) {
					$this->submitUpload();
				} else {
					$this->submitLocal();
				}
			} else {
				$out->addWikiMsg( 'securepoll-can-decrypt' );
				$this->showLocalForm();
				$this->showUploadForm();
			}
		} else {
			if ( $request->wasPosted() ) {
				$this->submitLocal();
			} else {
				$this->showLocalForm();
			}
		}
	}

	/**
	 * Show a form which, when submitted, shows a tally for the results in the DB
	 */
	public function showLocalForm() {
		$form = HTMLForm::factory(
			'ooui',
			[],
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitTextMsg( 'securepoll-tally-local-submit' )
			->setSubmitName( 'submit_local' )
			->setWrapperLegend(
				$this->msg( 'securepoll-tally-local-legend' )->text()
			)
			->show();
	}

	/**
	 * Shows a form for upload of a record produced by the dump subpage.
	 */
	public function showUploadForm() {
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
			->setWrapperLegend(
				$this->msg( 'securepoll-tally-upload-legend' )->text()
			)
			->show();
	}

	/**
	 * Show a tally of the local DB
	 */
	public function submitLocal() {
		$status = $this->election->tally();
		if ( !$status->isOK() ) {
			$this->specialPage->getOutput()->addWikiTextAsInterface( $status->getWikiText() );

			return;
		}
		$tallier = $status->value;
		$this->specialPage->getOutput()->addHTML( $tallier->getHtmlResult() );
	}

	/**
	 * Show a tally of the results in the uploaded file
	 */
	public function submitUpload() {
		$out = $this->specialPage->getOutput();

		if ( !isset( $_FILES['tally_file'] ) || !is_uploaded_file(
				$_FILES['tally_file']['tmp_name']
			) || !$_FILES['tally_file']['size']
		) {
			$out->addWikiMsg( 'securepoll-no-upload' );

			return;
		}
		$context = Context::newFromXmlFile( $_FILES['tally_file']['tmp_name'] );
		if ( !$context ) {
			$out->addWikiMsg( 'securepoll-dump-corrupt' );

			return;
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
			$out->addWikiTextAsInterface( $status->getWikiText( 'securepoll-tally-upload-error' ) );

			return;
		}
		$tallier = $status->value;
		$out->addHTML( $tallier->getHtmlResult() );
	}

	public function getTitle() {
		return $this->specialPage->getPageTitle( 'tally/' . $this->election->getId() );
	}
}
