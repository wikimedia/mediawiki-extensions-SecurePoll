<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use HTMLForm;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\Store\MemoryStore;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Request\WebRequestUpload;
use MediaWiki\Status\Status;
use OOUI\MessageWidget;
use OOUIHTMLForm;
use RuntimeException;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A subpage for tallying votes and producing results
 */
class TallyPage extends ActionPage {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var bool */
	private $tallyEnqueued = null;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param ILoadBalancer $loadBalancer
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		ILoadBalancer $loadBalancer,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $specialPage );
		$this->loadBalancer = $loadBalancer;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();

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

		$user = $this->specialPage->getUser();
		$this->initLanguage( $user, $this->election );
		$out->setPageTitleMsg( $this->msg( 'securepoll-tally-title', $this->election->getMessage( 'title' ) ) );

		if ( !$this->election->isAdmin( $user ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-tally-not-finished' );
			return;
		}

		$this->showTallyStatus();

		$form = $this->createForm();
		$form->show();

		$this->showTallyError();
		$this->showTallyResult();
	}

	/**
	 * Show any errors from the most recent tally attempt
	 */
	private function showTallyError(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$result = $dbr->selectField(
			'securepoll_properties',
			[
				'pr_value',
			],
			[
				'pr_entity' => $this->election->getId(),
				'pr_key' => [
					'tally-error',
				],
			]
		);

		if ( $result ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-result-error', $result )->text(),
				'type' => 'error',
			] );
			$out->prependHTML( $message->toString() );
		}
	}

	/**
	 * Check whether there is enqueued tally
	 *
	 * @return bool
	 */
	private function isTallyEnqueued(): bool {
		if ( $this->tallyEnqueued !== null ) {
			return $this->tallyEnqueued;
		}

		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$result = $dbr->selectField(
			'securepoll_properties',
			[
				'pr_value',
			],
			[
				'pr_entity' => $this->election->getId(),
				'pr_key' => [
					'tally-job-enqueued',
				],
			]
		);
		$this->tallyEnqueued = (bool)$result;
		return $this->tallyEnqueued;
	}

	/**
	 * Show messages indicating the status of tallying if relevant
	 */
	private function showTallyStatus(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$result = $dbr->selectField(
			'securepoll_properties',
			[
				'pr_value',
			],
			[
				'pr_entity' => $this->election->getId(),
				'pr_key' => [
					'tally-job-enqueued',
				],
			]
		);

		if ( $result ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-job-enqueued' )->text(),
				'type' => 'warning',
			] );
			$out->addHTML( $message->toString() );
		}
	}

	/**
	 * Show the tally result if one has previously been calculated
	 */
	private function showTallyResult(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$tallier = $this->election->getTallyFromDb( $dbr );

		if ( !$tallier ) {
			return;
		}

		$time = $dbr->selectField(
			'securepoll_properties',
			[
				'pr_value',
			],
			[
				'pr_entity' => $this->election->getId(),
				'pr_key' => [
					'tally-result-time',
				],
			]
		);

		$out->addHTML(
			$out->msg( 'securepoll-tally-result' )
				->rawParams( $tallier->getHtmlResult() )
				->dateTimeParams( wfTimestamp( TS_UNIX, $time ) )
		);
	}

	/**
	 * Create a form which, when submitted, shows a tally for the election.
	 *
	 * @return OOUIHTMLForm
	 */
	private function createForm() {
		$formFields = $this->getCryptDescriptors();

		if ( $this->isTallyEnqueued() ) {
			foreach ( $formFields as $fieldname => $field ) {
				$formFields[$fieldname]['disabled'] = true;
			}
			// This will replace the default submit button
			$formFields['disabledSubmit'] = [
				'type' => 'submit',
				'disabled' => true,
				'buttonlabel-message' => 'securepoll-tally-upload-submit',
			];
		}

		$form = HTMLForm::factory(
			'ooui',
			$formFields,
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitTextMsg( 'securepoll-tally-upload-submit' )
			->setSubmitCallback( [ $this, 'submitForm' ] );

		if ( $this->isTallyEnqueued() ) {
			$form->suppressDefaultSubmit();
		}

		if ( $this->election->getCrypt() ) {
			$form->setWrapperLegend( true );
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return $form;
	}

	/**
	 * Get any crypt-specific descriptors for the form.
	 *
	 * @return array
	 */
	private function getCryptDescriptors(): array {
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
			return $this->submitJob( $this->election, $data );
		}
		return $this->submitUpload( $data, $upload );
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
			throw new RuntimeException(
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
		'@phan-var ElectionTallier $tallier'; /** @var ElectionTallier $tallier */
		$out->addHTML( $tallier->getHtmlResult() );
		return true;
	}

	/**
	 * @param Election $election
	 * @param array $data
	 * @return bool
	 */
	private function submitJob( Election $election, array $data ): bool {
		$electionId = $election->getId();
		$dbw = $this->loadBalancer->getConnection( ILoadBalancer::DB_PRIMARY );

		$crypt = $election->getCrypt();
		if ( $crypt ) {
			// Save any request data that is needed for tallying
			$election->getCrypt()->updateDbForTallyJob( $electionId, $dbw, $data );
		}

		// Record that the election is being tallied. The job will
		// delete this on completion.
		$dbw->upsert(
			'securepoll_properties',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-job-enqueued',
				'pr_value' => 1,
			],
			[
				[
					'pr_entity',
					'pr_key'
				],
			],
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-job-enqueued',
			],
			__METHOD__
		);

		$this->jobQueueGroup->push(
			new JobSpecification(
				'securePollTallyElection',
				[ 'electionId' => $electionId ],
				[],
				$this->getTitle()
			)
		);

		// Delete error to prevent showing old errors while job is queueing
		$dbw->delete(
			'securepoll_properties',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-error',
			],
			__METHOD__
		);

		// Redirect (using HTTP 303 See Other) the UA to the current URL so that the user does not
		// inadvertently resubmit the form while trying to determine if the tallier has finished.
		$url = $this->getTitle()
			->getFullURL();

		$this->specialPage->getOutput()
			->redirect( $url, 303 );

		return true;
	}

	/**
	 * Update the context of the election to be tallied with any information
	 * not stored in the database that is needed for decryption.
	 *
	 * @param Election $election The election to be tallied
	 * @param array $data Form data
	 */
	private function updateContextForCrypt( Election $election, array $data ): void {
		$crypt = $election->getCrypt();
		if ( $crypt ) {
			$crypt->updateTallyContext( $election->context, $data );
		}
	}

	public function getTitle() {
		return $this->specialPage->getPageTitle( 'tally/' . $this->election->getId() );
	}
}
