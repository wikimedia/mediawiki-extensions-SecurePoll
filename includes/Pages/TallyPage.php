<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use DeferredUpdates;
use Exception;
use HTMLForm;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\MemoryStore;
use MediaWiki\Extensions\SecurePoll\SpecialSecurePoll;
use OOUI\MessageWidget;
use OOUIHTMLForm;
use Status;
use WebRequestUpload;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A subpage for tallying votes and producing results
 */
class TallyPage extends ActionPage {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var bool */
	private $tallyOngoing = null;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		ILoadBalancer $loadBalancer
	) {
		parent::__construct( $specialPage );
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();
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

		$form = $this->createForm();
		$form->show();

		if ( !$form->wasSubmitted() ) {
			$this->showTallyStatus();
			$this->showTallyError();
			$this->showTallyResult();
		}
	}

	/**
	 * Show any errors from the most recent tally attempt
	 */
	private function showTallyError(): void {
		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
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
			$out->prependHtml( $message->toString() );
		}
	}

	/**
	 * Check whether there is an ongoing tally
	 *
	 * @return bool
	 */
	private function isTallyOngoing(): bool {
		if ( $this->tallyOngoing !== null ) {
			return $this->tallyOngoing;
		}

		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
		$result = $dbr->selectField(
			'securepoll_properties',
			[
				'pr_value',
			],
			[
				'pr_entity' => $this->election->getId(),
				'pr_key' => [
					'tally-ongoing',
				],
			]
		);
		$this->tallyOngoing = (bool)$result;
		return $this->tallyOngoing;
	}

	/**
	 * Show messages indicating the status of tallying if relevant
	 */
	private function showTallyStatus(): void {
		if ( $this->isTallyOngoing() ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-ongoing' )->text(),
				'type' => 'warning',
			] );
			$this->specialPage->getOutput()->prependHtml( $message->toString() );
		}
	}

	/**
	 * Show the tally result if one has previously been calculated
	 */
	private function showTallyResult(): void {
		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
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

		if ( $this->isTallyOngoing() ) {
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

		if ( $this->isTallyOngoing() ) {
			$form->suppressDefaultSubmit();
		}

		if ( $this->election->getCrypt() ) {
			$form->setWrapperLegend( true );
		}

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
		if ( $this->isTallyOngoing() ) {
			// Do nothing until the ongoing tally completes
			return;
		}

		$dbw = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_PRIMARY );
		$election = $this->election;
		$electionId = $election->getId();
		$method = __METHOD__;

		$this->updateContextForCrypt( $election, $data );

		// Record that the election is being tallied. This will be
		// deleted on completion.
		$dbw->insert(
			'securepoll_properties',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-ongoing',
				'pr_value' => 1,
			],
			$method,
			[ 'IGNORE' ]
		);

		// Remove any errors from previous tally attempts
		$dbw->delete(
			'securepoll_properties',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-error',
			],
			$method
		);

		// Tallying can take a long time, so defer
		DeferredUpdates::addCallableUpdate(
			static function () use ( $method, $election, $dbw ) {
				$electionId = $election->getId();

				$status = $election->tally();
				if ( !$status->isOK() ) {
					$dbw->upsert(
						'securepoll_properties',
						[
							'pr_entity' => $electionId,
							'pr_key' => 'tally-error',
							'pr_value' => $status->getMessage(),
						],
						[
							[
								'pr_entity',
								'pr_key',
							],
						],
						[
							'pr_value' => $status->getMessage(),
						],
						$method
					);
				} else {
					$tallier = $status->value;
					$result = json_encode( $tallier->getJSONResult() );

					$dbw->upsert(
						'securepoll_properties',
						[
							'pr_entity' => $electionId,
							'pr_key' => 'tally-result',
							'pr_value' => $result,
						],
						[
							[
								'pr_entity',
								'pr_key',
							],
						],
						[
							'pr_value' => $result,
						],
						$method
					);

					$time = time();
					$dbw->upsert(
						'securepoll_properties',
						[
							'pr_entity' => $electionId,
							'pr_key' => 'tally-result-time',
							'pr_value' => $time,
						],
						[
							[
								'pr_entity',
								'pr_key',
							],
						],
						[
							'pr_value' => $time,
						],
						$method
					);
				}

				$dbw->delete(
					'securepoll_properties',
					[
						'pr_entity' => $electionId,
						'pr_key' => 'tally-ongoing',
					],
					$method
				);
			}
		);

		$this->specialPage->getOutput()->addWikiMsg( 'securepoll-tally-ongoing' );
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
