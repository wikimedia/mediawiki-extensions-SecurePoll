<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\Store\MemoryStore;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Request\WebRequestUpload;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use OOUI\MessageWidget;
use RuntimeException;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A subpage for listing all tallies for an election.
 */
class TallyListPage extends ActionPage {

	private const SUBPAGES = [
		'result' => [
			'text' => 'securepoll-subpage-view',
		],
		'delete' => [
			'text' => 'securepoll-subpage-delete',
			'token' => true,
		],
	];

	private ?bool $tallyEnqueued = null;

	public function __construct(
		SpecialSecurePoll $specialPage,
		private readonly LinkRenderer $linkRenderer,
		private readonly ILoadBalancer $loadBalancer,
		private readonly JobQueueGroup $jobQueueGroup
	) {
		parent::__construct( $specialPage );
	}

	/**
	 * Execute the subpage.
	 *
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
		$out->setPageTitleMsg(
			$this->msg( 'securepoll-tally-list-title', $this->election->getMessage( 'title' ) )
		);

		if ( !$this->election->isAdmin( $user ) ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-tally-not-finished' );
			return;
		}

		$this->showTallyStatus();
		$this->showTallyError();

		$form = $this->createForm();
		$form->show();

		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$tallies = $this->election->getTalliesFromDb( $dbr );
		if ( count( $tallies ) > 0 ) {
			$table = $this->createTallyTable( $tallies );
			$out->addHTML( $table );
			$out->addModuleStyles( [ 'mediawiki.pager.styles' ] );
		}

		$out->addModuleStyles( [ 'ext.securepoll' ] );
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
	 * Show any errors from the most recent tally attempt
	 */
	private function showTallyError(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $this->election->getId(),
				'pr_key' => 'tally-error',
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $result ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-result-error', $result )->text(),
				'type' => 'error',
			] );
			$out->prependHTML( $message->toString() );
		}
	}

	/**
	 * Show messages indicating the status of tallying if relevant
	 */
	private function showTallyStatus(): void {
		$dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA );
		$out = $this->specialPage->getOutput();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $this->election->getId(),
				'pr_key' => 'tally-job-enqueued',
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $result ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'securepoll-tally-job-enqueued' )->text(),
				'type' => 'warning',
			] );
			$out->addHTML( $message->toString() );
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
		$result = $dbr->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $this->election->getId(),
				'pr_key' => 'tally-job-enqueued',
			] )
			->caller( __METHOD__ )
			->fetchField();

		$this->tallyEnqueued = (bool)$result;

		return $this->tallyEnqueued;
	}

	/**
	 * Create a table of tallies for the election.
	 *
	 * @param array $tallies
	 * @return string
	 */
	private function createTallyTable( array $tallies ): string {
		$thead = Html::rawElement( 'thead', [], implode( "\n", [
			Html::rawElement( 'tr', [], implode( "\n", [
				Html::element( 'th', [], $this->msg( 'securepoll-header-timestamp' )->text() ),
				Html::element( 'th', [], $this->msg( 'securepoll-header-links' )->text() ),
			] ) ),
		] ) );

		$getTallyLinks = [ $this, 'getTallyLinks' ];
		$language = $this->specialPage->getLanguage();
		$user = $this->specialPage->getUser();

		// Map each tally to an HTML table row with a timestamp and links.
		$rows = array_map(
			static function ( $tally ) use ( $getTallyLinks, $language, $user ) {
				$timestamp = $language->userTimeAndDate( $tally['resultTime'], $user );
				$links = $getTallyLinks( $tally['tallyId'] );

				return Html::rawElement( 'tr', [], implode( "\n", [
					Html::element( 'td', [], $timestamp ),
					Html::rawElement( 'td', [], $links ),
				] ) );
			},
			$tallies
		);

		// Reverse the rows so that the latest tallies are at the top (desc).
		$rows = array_reverse( $rows );

		$tbody = Html::rawElement( 'tbody', [], implode( "\n", $rows ) );

		return Html::rawElement( 'table', [
			'class' => 'mw-datatable',
		], implode( "\n", [ $thead, $tbody ] ) );
	}

	/**
	 * Create a form which, when submitted, shows a tally for the election.
	 */
	private function createForm(): HTMLForm {
		$formFields = $this->getCryptDescriptors();
		$formFields += $this->getTallyModifiersForm();

		if ( $this->isTallyEnqueued() ) {
			foreach ( $formFields as $fieldname => $field ) {
				$formFields[$fieldname]['disabled'] = true;
			}
		}

		$form = HTMLForm::factory(
			'ooui',
			$formFields,
			$this->specialPage->getContext(),
			'securepoll-tally'
		);

		$form->setSubmitCallback( [ $this, 'submitForm' ] )
			->suppressDefaultSubmit();

		$buttonProps = [
			'type' => 'submit',
			'name' => 'Submit',
			'value' => $this->msg( 'securepoll-tally-upload-submit' )->text(),
			'flags' => [ 'primary', 'progressive' ],
			'attribs' => [],
		];
		if ( $this->isTallyEnqueued() ) {
			$buttonProps['attribs']['disabled'] = true;
		}
		$form->addButton( $buttonProps );

		if ( $this->election->getCrypt() ) {
			$form->setWrapperLegend( true );
		}

		return $form;
	}

	/**
	 * If the ballot type supports modifications to the tally calculation,
	 * generate the form fields to allow the user to pass those adjustment requests
	 * to the tallier.
	 *
	 * @return array
	 */
	private function getTallyModifiersForm(): array {
		$tallyModifiers = $this->election->getBallot()->getTallyModifiers();

		// Do nothing if no modifiers for this ballot type are found
		if ( !count( $tallyModifiers ) ) {
			return [];
		}

		// Set up a form that allows the submitter to declare modifcations based on what the
		// tallier will allow. See Ballot::getTallyModifiers for more information.
		$modifiers = [];
		foreach ( $tallyModifiers as $modifierName => $modifier ) {
			$modifierForm = [];
			foreach ( $this->election->getQuestions() as $question ) {
				$questionFields = [];
				$questionKey = "{$modifierName}-entityId_{$question->getId()}";

				// TODO: support different types on the question. No modifier requires this yet
				// so for now default to an 'info' type that shows the question name.
				$questionText = $this->msg(
					'securepoll-tallymodifiers-question-label',
					$question->getRawMessage( 'text', $this->election->getLanguage() )
				)->parse();
				$questionFields += [
					$questionKey => [
						'type' => 'info',
						'raw' => true,
						'default' => $questionText,
						'section' => $modifierName,
					]
				];

				$optionFields = [];
				foreach ( $question->getOptions() as $option ) {
					$optionKey = "{$modifierName}-entityId_{$option->getId()}";
					$optionFields += [
						$optionKey => [
							'type' => $modifier['field'] ? $modifier['type'] : 'info',
							'label' => $option->getRawMessage( 'text', $this->election->getLanguage() ),
							'section' => $modifierName
						]
					];
				}
				$questionFields += $optionFields;
				$modifierForm = array_merge( $modifierForm, $questionFields );
			}
			$modifiers = array_merge( $modifiers, $modifierForm );
		}

		return $modifiers;
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
	 * Pushes a tally job to the queue and reloads the page.
	 */
	private function submitJob( Election $election, array $data ): bool {
		$electionId = $election->getId();
		$dbw = $this->loadBalancer->getConnection( ILoadBalancer::DB_PRIMARY );

		$crypt = $election->getCrypt();
		if ( $crypt ) {
			// Save any request data that is needed for tallying
			$election->getCrypt()->updateDbForTallyJob( $electionId, $dbw, $data );
		}

		// The data array can contain information about requested modifiers to the tally.
		// Capture and transform modifier data passed through as an array to be saved to the job:
		// [ 'modifierName' => [ 'entityId' => 'value' ] ]
		// As modifiers can only affect questions or options and both will have a unique entity id,
		// we don't need to save any other information about the option to apply modifications later.
		$transformedData = [];
		foreach ( $data as $key => $datum ) {
			// Keys for modifiers come in the format $modifierName-entityId_$entityId, pull this data:
			$identifiers = explode( '-entityId_', $key );

			// Ignore the data if what's passed through isn't in the format expected of modifiers
			// eg. decryption keys
			if ( count( $identifiers ) !== 2 ) {
				continue;
			}
			if ( !isset( $transformedData[$identifiers[0]] ) ) {
				$transformedData[$identifiers[0]] = [];
			}
			$transformedData[$identifiers[0]][$identifiers[1]] = $datum;
		}

		// Record that the election is being tallied. The job will
		// delete this on completion.
		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_properties' )
			->row( [
				'pr_entity' => $electionId,
				'pr_key' => 'tally-job-enqueued',
				'pr_value' => serialize( $transformedData ),
			] )
			->ignore()
			->caller( __METHOD__ )
			->execute();

		$this->jobQueueGroup->push(
			new JobSpecification(
				'securePollTallyElection',
				[ 'electionId' => $electionId ],
				[],
				$this->getTitle()
			)
		);

		// Delete error to prevent showing old errors while job is queueing
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_properties' )
			->where( [
				'pr_entity' => $electionId,
				'pr_key' => 'tally-error',
			] )
			->caller( __METHOD__ )
			->execute();

		// Redirect (using HTTP 303 See Other) the UA to the current URL so
		// that the user does not inadvertently resubmit the form while trying
		// to determine if the tallier has finished.
		$url = $this->getTitle()->getFullURL();

		$this->specialPage->getOutput()->redirect( $url, 303 );

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

	/**
	 * Get any crypt-specific descriptors for the form.
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
	 * Get HTML of relevant links for a tally in the table.
	 */
	private function getTallyLinks( int $tallyId ): string {
		$electionId = $this->election->getId();
		$separator = $this->msg( 'pipe-separator' )->escaped();
		$html = '';

		foreach ( self::SUBPAGES as $subpage => $props ) {
			$linkHref = $this->specialPage->getPageTitle(
				"tallies/{$electionId}/{$subpage}/{$tallyId}"
			);
			$linkText = $this->msg( $props['text'] )->text();

			$queryParams = [];
			if ( $props['token'] ?? null ) {
				$queryParams[ 'token' ] = $this->specialPage
					->getContext()
					->getCsrfTokenSet()
					->getToken();
			}

			if ( $html !== '' ) {
				$html .= $separator;
			}

			$html .= $this->linkRenderer->makeKnownLink(
				$linkHref,
				$linkText,
				[],
				$queryParams
			);
		}

		return $html;
	}

	/**
	 * Returns the current page's title needed for the submission redirect.
	 */
	private function getTitle(): Title {
		return $this->specialPage->getPageTitle( 'tallies/' . $this->election->getId() );
	}
}
