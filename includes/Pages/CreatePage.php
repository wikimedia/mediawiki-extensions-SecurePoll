<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use DateTime;
use DateTimeZone;
use HTMLForm;
use LanguageCode;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Crypt\Crypt;
use MediaWiki\Extension\SecurePoll\Entities\Entity;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\SecurePollContentHandler;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\Store\FormStore;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Linker\Linker;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use Message;
use PermissionsError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * Special:SecurePoll subpage for creating or editing a poll
 */
class CreatePage extends ActionPage {
	/** @var LBFactory */
	private $lbFactory;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LBFactory $lbFactory
	 * @param UserGroupManager $userGroupManager
	 * @param LanguageNameUtils $languageNameUtils
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LBFactory $lbFactory,
		UserGroupManager $userGroupManager,
		LanguageNameUtils $languageNameUtils,
		WikiPageFactory $wikiPageFactory,
		UserFactory $userFactory
	) {
		parent::__construct( $specialPage );
		$this->lbFactory = $lbFactory;
		$this->userGroupManager = $userGroupManager;
		$this->languageNameUtils = $languageNameUtils;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userFactory = $userFactory;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 * @throws InvalidDataException
	 * @throws PermissionsError
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

		if ( $params ) {
			$out->setPageTitleMsg( $this->msg( 'securepoll-edit-title' ) );
			$electionId = intval( $params[0] );
			$this->election = $this->context->getElection( $electionId );
			if ( !$this->election ) {
				$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

				return;
			}
			if ( !$this->election->isAdmin( $this->specialPage->getUser() ) ) {
				$out->addWikiMsg( 'securepoll-need-admin' );

				return;
			}
			if ( $this->election->isFinished() ) {
				$out->addWikiMsg( 'securepoll-finished-no-edit' );
				return;
			}

			$jumpUrl = $this->election->getProperty( 'jump-url' );
			if ( $jumpUrl ) {
				$jumpId = $this->election->getProperty( 'jump-id' );
				if ( !$jumpId ) {
					throw new InvalidDataException( 'Configuration error: no jump-id' );
				}
				$jumpUrl .= "/edit/$jumpId";
				if ( count( $params ) > 1 ) {
					$jumpUrl .= '/' . implode( '/', array_slice( $params, 1 ) );
				}

				$wiki = $this->election->getProperty( 'main-wiki' );
				if ( $wiki ) {
					$wiki = WikiMap::getWikiName( $wiki );
				} else {
					$wiki = $this->msg( 'securepoll-edit-redirect-otherwiki' )->text();
				}

				$out->addWikiMsg(
					'securepoll-edit-redirect',
					Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
				);

				return;
			}
		} else {
			$out->setPageTitleMsg( $this->msg( 'securepoll-create-title' ) );
			if ( !$this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
				throw new PermissionsError( 'securepoll-create-poll' );
			}
		}

		$out->addJsConfigVars( 'SecurePollSubPage', 'create' );
		$out->addModules( 'ext.securepoll.htmlform' );
		$out->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.securepoll',
		] );

		$election = $this->election;
		$isRunning = $election && $election->isStarted() && !$election->isFinished();
		$formItems = [];

		$formItems['election_id'] = [
			'type' => 'hidden',
			'default' => -1,
			'output-as-default' => false,
		];

		// Submit intended to be hidden w/CSS
		// Placed at the beginning of the form so that when the form
		// is submitted by pressing enter while focused on an input,
		// it will trigger this generic submit and not generate an event
		// on a cloner add/delete item
		$formItems['default_submit'] = [
			'type' => 'submit',
			'buttonlabel' => 'submit',
			'cssclass' => 'securepoll-default-submit',
		];

		$formItems['election_title'] = [
			'label-message' => 'securepoll-create-label-election_title',
			'type' => 'text',
			'required' => true,
			'disabled' => $isRunning,
		];

		$wikiNames = FormStore::getWikiList();
		$options = [];
		$options['securepoll-create-option-wiki-this_wiki'] = WikiMap::getCurrentWikiId();
		if ( count( $wikiNames ) > 1 ) {
			$options['securepoll-create-option-wiki-all_wikis'] = '*';
		}
		$securePollCreateWikiGroupDir = $this->specialPage->getConfig()->get( 'SecurePollCreateWikiGroupDir' );
		foreach ( $this->specialPage->getConfig()->get( 'SecurePollCreateWikiGroups' ) as $file => $msg ) {
			if ( is_readable( "$securePollCreateWikiGroupDir$file.dblist" ) ) {
				$options[$msg] = "@$file";
			}
		}

		// If the only option is WikiMap::getCurrentWikiId() don't show it; otherwise...
		if ( count( $wikiNames ) > 1 || count( $options ) > 1 ) {
			$opts = [];
			foreach ( $options as $msg => $value ) {
				$opts[$this->msg( $msg )->plain()] = $value;
			}
			$key = array_search( WikiMap::getCurrentWikiId(), $wikiNames, true );
			if ( $key !== false ) {
				unset( $wikiNames[$key] );
			}
			if ( $wikiNames ) {
				$opts[$this->msg( 'securepoll-create-option-wiki-other_wiki' )->plain()] = $wikiNames;
			}
			$formItems['property_wiki'] = [
				'type' => 'select',
				'options' => $opts,
				'label-message' => 'securepoll-create-label-wiki',
				'disabled' => $isRunning,
			];
		}

		$languages = $this->languageNameUtils->getLanguageNames();
		ksort( $languages );
		$options = [];
		foreach ( $languages as $code => $name ) {
			$display = LanguageCode::bcp47( $code ) . ' - ' . $name;
			$options[$display] = $code;
		}
		$formItems['election_primaryLang'] = [
			'type' => 'select',
			'options' => $options,
			'label-message' => 'securepoll-create-label-election_primarylang',
			'default' => 'en',
			'required' => true,
			'disabled' => $isRunning,
		];

		$formItems['election_startdate'] = [
			'label-message' => 'securepoll-create-label-election_startdate',
			'type' => 'datetime',
			'required' => true,
			'min' => $isRunning ? '' : gmdate( 'Y-m-d H:i:s' ),
			'disabled' => $isRunning,
		];

		$formItems['election_enddate'] = [
			'label-message' => 'securepoll-create-label-election_enddate',
			'type' => 'datetime',
			'required' => true,
			'min' => $isRunning ? '' : gmdate( 'Y-m-d H:i:s' ),
			'validation-callback' => [
				$this,
				'checkElectionEndDate'
			],
			'disabled' => $isRunning,
		];

		$formItems['return-url'] = [
			'label-message' => 'securepoll-create-label-election_return-url',
			'type' => 'url',
		];

		if ( isset( $formItems['property_wiki'] ) ) {
			$formItems['jump-text'] = [
				'label-message' => 'securepoll-create-label-election_jump-text',
				'type' => 'text',
				'disabled' => $isRunning,
			];
			$formItems['jump-text']['hide-if'] = [
				'===',
				'property_wiki',
				WikiMap::getCurrentWikiId()
			];
		}

		$formItems['election_type'] = [
			'label-message' => 'securepoll-create-label-election_type',
			'type' => 'radio',
			'options-messages' => [],
			'required' => true,
			'disabled' => $isRunning,
		];

		if ( count( Crypt::$cryptTypes ) > 1 ) {
			$formItems['election_crypt'] = [
				'label-message' => 'securepoll-create-label-election_crypt',
				'type' => 'radio',
				'options-messages' => [],
				'required' => true,
				'disabled' => $isRunning,
			];
		} else {
			reset( Crypt::$cryptTypes );
			$formItems['election_crypt'] = [
				'type' => 'hidden',
				'default' => key( Crypt::$cryptTypes ),
				'options-messages' => [],
				// dummy, ignored
			];
		}

		$formItems['disallow-change'] = [
			'label-message' => 'securepoll-create-label-election_disallow-change',
			'type' => 'check',
			'hidelabel' => true,
			'disabled' => $isRunning,
		];

		$formItems['voter-privacy'] = [
			'label-message' => 'securepoll-create-label-voter_privacy',
			'type' => 'check',
			'hidelabel' => true,
			'disabled' => $isRunning,
		];

		$formItems['property_admins'] = [
			'label-message' => 'securepoll-create-label-property_admins',
			'type' => 'usersmultiselect',
			'exists' => true,
			'required' => true,
			'validation-callback' => [
				$this,
				'checkIfInElectionAdminUserGroup'
			],
		];

		$formItems['request-comment'] = [
			'label-message' => 'securepoll-create-label-request-comment',
			'type' => 'check',
			'disabled' => $isRunning
		];

		$formItems['comment-prompt'] = [
			'label-message' => 'securepoll-create-label-comment-prompt',
			'type' => 'textarea',
			'rows' => 2,
			'disabled' => $isRunning,
			'hide-if' => [
				'!==',
				'request-comment',
				'1'
			]
		];

		$questionFields = [
			'id' => [
				'type' => 'hidden',
				'default' => -1,
				'output-as-default' => false,
			],
			'text' => [
				'label-message' => 'securepoll-create-label-questions-question',
				'type' => 'text',
				'validation-callback' => [
					$this,
					'checkRequired',
				],
				'disabled' => $isRunning,
			],
			'delete' => [
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-questions-delete' )->text(),
				'disabled' => $isRunning,
				'flags' => [
					'destructive'
				],
			],
		];

		$optionFields = [
			'id' => [
				'type' => 'hidden',
				'default' => -1,
				'output-as-default' => false,
			],
			'text' => [
				'label-message' => 'securepoll-create-label-options-option',
				'type' => 'text',
				'validation-callback' => [
					$this,
					'checkRequired',
				],
				'disabled' => $isRunning,
			],
			'delete' => [
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-options-delete' )->text(),
				'disabled' => $isRunning,
				'flags' => [
					'destructive'
				],
			],
		];

		$tallyTypes = [];
		foreach ( $this->context->getBallotTypesForVote() as $ballotType => $ballotClass ) {
			$types = [];
			foreach (
				call_user_func_array( [ $ballotClass, 'getTallyTypes' ], [] ) as $tallyType
			) {
				$type = "$ballotType+$tallyType";
				$types[] = $type;
				$tallyTypes[$tallyType][] = $type;
				$formItems['election_type']['options-messages']["securepoll-create-option-election_type-$type"]
					= $type;
			}

			self::processFormItems(
				$formItems,
				'election_type',
				$types,
				$ballotClass,
				'election',
				$isRunning
			);
			self::processFormItems(
				$questionFields,
				'election_type',
				$types,
				$ballotClass,
				'question',
				$isRunning
			);
			self::processFormItems(
				$optionFields,
				'election_type',
				$types,
				$ballotClass,
				'option',
				$isRunning
			);
		}

		foreach ( Tallier::$tallierTypes as $type => $class ) {
			if ( !isset( $tallyTypes[$type] ) ) {
				continue;
			}
			self::processFormItems(
				$formItems,
				'election_type',
				$tallyTypes[$type],
				$class,
				'election',
				$isRunning
			);
			self::processFormItems(
				$questionFields,
				'election_type',
				$tallyTypes[$type],
				$class,
				'question',
				$isRunning
			);
			self::processFormItems(
				$optionFields,
				'election_type',
				$tallyTypes[$type],
				$class,
				'option',
				$isRunning
			);
		}

		foreach ( Crypt::$cryptTypes as $type => $class ) {
			$formItems['election_crypt']['options-messages']["securepoll-create-option-election_crypt-$type"]
				= $type;
			if ( $class !== false ) {
				self::processFormItems(
					$formItems,
					'election_crypt',
					$type,
					$class,
					'election',
					$isRunning
				);
				self::processFormItems(
					$questionFields,
					'election_crypt',
					$type,
					$class,
					'question',
					$isRunning
				);
				self::processFormItems(
					$optionFields,
					'election_crypt',
					$type,
					$class,
					'option',
					$isRunning
				);
			}
		}

		$questionFields['options'] = [
			'label-message' => 'securepoll-create-label-questions-option',
			'type' => 'cloner',
			'required' => true,
			'create-button-message' => 'securepoll-create-label-options-add',
			'fields' => $optionFields,
			'disabled' => $isRunning,
		];

		$formItems['questions'] = [
			'label-message' => 'securepoll-create-label-questions',
			'type' => 'cloner',
			'row-legend' => 'securepoll-create-questions-row-legend',
			'create-button-message' => 'securepoll-create-label-questions-add',
			'fields' => $questionFields,
			'disabled' => $isRunning,
		];

		if ( $this->specialPage->getConfig()->get( 'SecurePollUseNamespace' ) ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-create-label-comment',
				'maxlength' => 250,
			];
		}

		// Set form field defaults from any existing election
		if ( $this->election ) {
			$existingFieldData = $this->getFormDataFromElection();
			foreach ( $existingFieldData as $fieldName => $fieldValue ) {
				if ( isset( $formItems[ $fieldName ] ) ) {
					$formItems[ $fieldName ]['default'] = $fieldValue;
				}
			}
		}

		$form = HTMLForm::factory(
			'ooui',
			$formItems,
			$this->specialPage->getContext(),
			$this->election ? 'securepoll-edit' : 'securepoll-create'
		);

		$form->setSubmitTextMsg(
			$this->election ? 'securepoll-edit-action' : 'securepoll-create-action'
		);
		$form->setSubmitCallback(
			[
				$this,
				$isRunning ? 'processInputDuringElection' : 'processInput'
			]
		);
		$form->prepareForm();

		// If this isn't the result of a POST, load the data from the election
		$request = $this->specialPage->getRequest();
		if ( $this->election && !( $request->wasPosted() && $request->getCheck(
					'wpEditToken'
				) )
		) {
			$form->mFieldData = $this->getFormDataFromElection();
		}

		$result = $form->tryAuthorizedSubmit();
		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			if ( $this->election ) {
				$out->setPageTitleMsg( $this->msg( 'securepoll-edit-edited' ) );
				$out->addWikiMsg( 'securepoll-edit-edited-text' );
			} else {
				$out->setPageTitleMsg( $this->msg( 'securepoll-create-created' ) );
				$out->addWikiMsg( 'securepoll-create-created-text' );
			}
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		} else {
			$form->displayForm( $result );
		}
	}

	public function processInputDuringElection( $formData ) {
		// If editing a poll while it's running, only allow certain fields to be updated
		// For now only property_admins and return-url can be edited
		$fields = [
			'admins' => implode( '|', explode( "\n", $formData['property_admins'] ) ),
			'return-url' => $formData['return-url']
		];

		$originalFormData = [];
		$securePollUseLogging = $this->specialPage->getConfig()->get( 'SecurePollUseLogging' );
		if ( $securePollUseLogging ) {
			// Store original form data for logging
			$originalFormData = $this->getFormDataFromElection();
		}

		$dbw = $this->lbFactory->getMainLB()->getConnection( ILoadBalancer::DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		foreach ( $fields as $pr_key => $pr_value ) {
			$dbw->update(
				'securepoll_properties',
				[ 'pr_value' => $pr_value ],
				[
					'pr_entity' => $this->election->getId(),
					'pr_key' => $pr_key
				],
				__METHOD__
			);
		}
		$dbw->endAtomic( __METHOD__ );

		// Log any changes to admins
		if ( $securePollUseLogging ) {
			$this->logAdminChanges( $originalFormData, $formData, $this->election->getId() );
		}

		$this->recordElectionToNamespace( $this->election->getId(), $formData );

		return Status::newGood( $this->election->getId() );
	}

	public function processInput( $formData, $form ) {
		try {
			$context = new Context;
			$userId = $this->specialPage->getUser()->getId();
			$store = new FormStore;
			$context->setStore( $store );
			$store->setFormData( $context, $formData, $userId );
			$election = $context->getElection( $store->eId );

			if ( $this->election && $store->eId !== (int)$this->election->getId() ) {
				return Status::newFatal( 'securepoll-create-fail-bad-id' );
			}

			// Get a connection in autocommit mode so that it is possible to do
			// explicit transactions on it (T287859)
			$dbw = $this->lbFactory->getMainLB()->getConnection( ILoadBalancer::DB_PRIMARY,
				[], false, ILoadBalancer::CONN_TRX_AUTOCOMMIT );

			// Check for duplicate titles on the local wiki
			$id = $dbw->selectField(
				'securepoll_elections',
				'el_entity',
				[ 'el_title' => $election->title ],
				__METHOD__
			);
			if ( $id && (int)$id !== $election->getId() ) {
				throw new StatusException(
					'securepoll-create-duplicate-title',
					FormStore::getWikiName( WikiMap::getCurrentWikiId() ),
					WikiMap::getCurrentWikiId()
				);
			}

			// Check for duplicate titles on jump wikis too
			// (There's the possibility for a race here, but hopefully it won't
			// matter in practice)
			if ( $store->rId ) {
				foreach ( $store->remoteWikis as $dbname ) {
					$lb = $this->lbFactory->getMainLB( $dbname );
					// Use autocommit mode so that we can share connections with
					// the write code below
					$rdbw = $lb->getConnection( DB_PRIMARY, [], $dbname,
						ILoadBalancer::CONN_TRX_AUTOCOMMIT );

					// Find an existing dummy election, if any
					$rId = $rdbw->selectField(
						[
							'p1' => 'securepoll_properties',
							'p2' => 'securepoll_properties'
						],
						'p1.pr_entity',
						[
							'p1.pr_entity = p2.pr_entity',
							'p1.pr_key' => 'jump-id',
							'p1.pr_value' => $election->getId(),
							'p2.pr_key' => 'main-wiki',
							'p2.pr_value' => WikiMap::getCurrentWikiId(),
						],
						__METHOD__
					);
					// Test for duplicate title
					$id = $rdbw->selectField(
						'securepoll_elections',
						'el_entity',
						[
							'el_title' => $formData['election_title']
						],
						__METHOD__
					);

					$lb->reuseConnection( $rdbw );

					if ( $id && $id !== $rId ) {
						throw new StatusException(
							'securepoll-create-duplicate-title',
							FormStore::getWikiName( $dbname ),
							$dbname
						);
					}
				}
			}
		} catch ( StatusException $ex ) {
			return $ex->status;
		}

		$originalFormData = [];
		$securePollUseLogging = $this->specialPage->getConfig()->get( 'SecurePollUseLogging' );
		if ( $securePollUseLogging && $this->election ) {
			// Store original form data for logging
			$originalFormData = $this->getFormDataFromElection();
		}

		// Ok, begin the actual work
		$dbw->startAtomic( __METHOD__ );
		if ( $election->getId() > 0 ) {
			$id = $dbw->selectField(
				'securepoll_elections',
				'el_entity',
				[
					'el_entity' => $election->getId()
				],
				__METHOD__,
				[ 'FOR UPDATE' ]
			);
			if ( !$id ) {
				$dbw->endAtomic( __METHOD__ );

				return Status::newFatal( 'securepoll-create-fail-id-missing' );
			}
		}

		// Insert or update the election entity
		$fields = [
			'el_title' => $election->title,
			'el_ballot' => $election->ballotType,
			'el_tally' => $election->tallyType,
			'el_primary_lang' => $election->getLanguage(),
			'el_start_date' => $dbw->timestamp( $election->getStartDate() ),
			'el_end_date' => $dbw->timestamp( $election->getEndDate() ),
			'el_auth_type' => $election->authType,
			'el_owner' => $election->owner,
		];
		if ( $election->getId() < 0 ) {
			$eId = self::insertEntity( $dbw, 'election' );
			$qIds = [];
			$oIds = [];
			$fields['el_entity'] = $eId;
			$dbw->insert( 'securepoll_elections', $fields, __METHOD__ );

			// Enable sitewide block by default on new elections
			$dbw->insert(
				'securepoll_properties',
				[
					'pr_entity' => $eId,
					'pr_key' => 'not-sitewide-blocked',
					'pr_value' => 1,
				],
				__METHOD__
			);
		} else {
			$eId = $election->getId();
			$dbw->update( 'securepoll_elections', $fields, [ 'el_entity' => $eId ], __METHOD__ );

			// Delete any questions or options that weren't included in the
			// form submission.
			$qIds = [];
			$res = $dbw->select( 'securepoll_questions', 'qu_entity', [ 'qu_election' => $eId ], __METHOD__ );
			foreach ( $res as $row ) {
				$qIds[] = $row->qu_entity;
			}
			$oIds = [];
			$res = $dbw->select( 'securepoll_options', 'op_entity', [ 'op_election' => $eId ], __METHOD__ );
			foreach ( $res as $row ) {
				$oIds[] = $row->op_entity;
			}
			$deleteIds = array_merge(
				array_diff( $qIds, $store->qIds ),
				array_diff( $oIds, $store->oIds )
			);
			if ( $deleteIds ) {
				$dbw->delete( 'securepoll_msgs', [ 'msg_entity' => $deleteIds ], __METHOD__ );
				$dbw->delete( 'securepoll_properties', [ 'pr_entity' => $deleteIds ], __METHOD__ );
				$dbw->delete( 'securepoll_questions', [ 'qu_entity' => $deleteIds ], __METHOD__ );
				$dbw->delete( 'securepoll_options', [ 'op_entity' => $deleteIds ], __METHOD__ );
				$dbw->delete( 'securepoll_entity', [ 'en_id' => $deleteIds ], __METHOD__ );
			}
		}
		self::savePropertiesAndMessages( $dbw, $eId, $election );

		// Now do questions and options
		$qIndex = 0;
		foreach ( $election->getQuestions() as $question ) {
			$qId = $question->getId();
			if ( !in_array( $qId, $qIds ) ) {
				$qId = self::insertEntity( $dbw, 'question' );
			}
			$dbw->replace(
				'securepoll_questions',
				'qu_entity',
				[
					'qu_entity' => $qId,
					'qu_election' => $eId,
					'qu_index' => ++$qIndex,
				],
				__METHOD__
			);
			self::savePropertiesAndMessages( $dbw, $qId, $question );

			foreach ( $question->getOptions() as $option ) {
				$oId = $option->getId();
				if ( !in_array( $oId, $oIds ) ) {
					$oId = self::insertEntity( $dbw, 'option' );
				}
				$dbw->replace(
					'securepoll_options',
					'op_entity',
					[
						'op_entity' => $oId,
						'op_election' => $eId,
						'op_question' => $qId,
					],
					__METHOD__
				);
				self::savePropertiesAndMessages( $dbw, $oId, $option );
			}
		}
		$dbw->endAtomic( __METHOD__ );

		if ( $securePollUseLogging ) {
			$this->logAdminChanges( $originalFormData, $formData, $eId );
		}

		// Create the "redirect" polls on foreign wikis
		if ( $store->rId ) {
			$election = $context->getElection( $store->rId );
			foreach ( $store->remoteWikis as $dbname ) {
				$lb = $this->lbFactory->getMainLB( $dbname );
				// As for the local wiki, request autocommit mode to get outer transaction scope
				$dbw = $lb->getConnection( ILoadBalancer::DB_PRIMARY, [], $dbname,
					ILoadBalancer::CONN_TRX_AUTOCOMMIT );
				$dbw->startAtomic( __METHOD__ );
				// Find an existing dummy election, if any
				$rId = $dbw->selectField(
					[
						'p1' => 'securepoll_properties',
						'p2' => 'securepoll_properties'
					],
					'p1.pr_entity',
					[
						'p1.pr_entity = p2.pr_entity',
						'p1.pr_key' => 'jump-id',
						'p1.pr_value' => $eId,
						'p2.pr_key' => 'main-wiki',
						'p2.pr_value' => WikiMap::getCurrentWikiId(),
					],
					__METHOD__
				);
				if ( !$rId ) {
					$rId = self::insertEntity( $dbw, 'election' );
				}

				// Insert it! We don't have to care about questions or options here.
				$dbw->replace(
					'securepoll_elections',
					'el_entity',
					[
						'el_entity' => $rId,
						'el_title' => $election->title,
						'el_ballot' => $election->ballotType,
						'el_tally' => $election->tallyType,
						'el_primary_lang' => $election->getLanguage(),
						'el_start_date' => $dbw->timestamp( $election->getStartDate() ),
						'el_end_date' => $dbw->timestamp( $election->getEndDate() ),
						'el_auth_type' => $election->authType,
						'el_owner' => $election->owner,
					],
					__METHOD__
				);
				self::savePropertiesAndMessages( $dbw, $rId, $election );

				// Fix jump-id
				$dbw->update(
					'securepoll_properties',
					[ 'pr_value' => $eId ],
					[
						'pr_entity' => $rId,
						'pr_key' => 'jump-id'
					],
					__METHOD__
				);
				$dbw->endAtomic( __METHOD__ );
				$lb->reuseConnection( $dbw );
			}
		}

		$this->recordElectionToNamespace( $eId, $formData );

		return Status::newGood( $eId );
	}

	/**
	 * Record this election to the SecurePoll namespace, if so configured.
	 *
	 * @param int $eId election id
	 * @param array $formData
	 */
	private function recordElectionToNamespace( $eId, $formData ) {
		if ( $this->specialPage->getConfig()->get( 'SecurePollUseNamespace' ) ) {
			// Create a new context to bypass caching.
			$context = new Context;
			// We may be inside a transaction, so force a primary DB connection (T209804)
			$context->getStore()->setForcePrimary( true );

			$election = $context->getElection( $eId );

			[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				$content,
				$this->specialPage->getUser(),
				$formData['comment']
			);

			[ $title, $content ] = SecurePollContentHandler::makeContentFromElection(
				$election,
				'msg/' . $election->getLanguage()
			);
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				$content,
				$this->specialPage->getUser(),
				$formData['comment']
			);
		}
	}

	/**
	 * Log changes made to the admins of the election.
	 *
	 * @param array $originalFormData Empty array if no election exists
	 * @param array $formData
	 * @param int $electionId
	 */
	private function logAdminChanges(
		array $originalFormData,
		array $formData,
		int $electionId
	): void {
		if ( isset( $originalFormData['property_admins'] ) ) {
			$oldAdmins = explode( "\n", $originalFormData['property_admins'] );
		} else {
			$oldAdmins = [];
		}
		$newAdmins = explode( "\n", $formData['property_admins'] );

		if ( $oldAdmins === $newAdmins ) {
			return;
		}

		$actions = [
			self::LOG_TYPE_ADDADMIN => array_diff( $newAdmins, $oldAdmins ),
			self::LOG_TYPE_REMOVEADMIN => array_diff( $oldAdmins, $newAdmins ),
		];

		$dbw = $this->lbFactory->getMainLB()->getConnection( ILoadBalancer::DB_PRIMARY );
		$fields = [
			'spl_timestamp' => $dbw->timestamp( time() ),
			'spl_election_id' => $electionId,
			'spl_user' => $this->specialPage->getUser()->getId(),
		];

		foreach ( array_keys( $actions ) as $action ) {
			foreach ( $actions[$action] as $admin ) {
				$dbw->insert(
					'securepoll_log',
					$fields + [
						'spl_type' => $action,
						'spl_target' => $this->userFactory->newFromName( $admin )->getId(),
					],
					__METHOD__
				);
			}
		}
	}

	/**
	 * Recreate the form data from an election
	 *
	 * @return array
	 */
	private function getFormDataFromElection() {
		$lang = $this->election->getLanguage();
		$data = array_replace_recursive(
			SecurePollContentHandler::getDataFromElection( $this->election, "msg/$lang" ),
			SecurePollContentHandler::getDataFromElection( $this->election )
		);
		$p = &$data['properties'];
		$m = &$data['messages'];

		$startDate = new MWTimestamp( $data['startDate'] );
		$endDate = new MWTimestamp( $data['endDate'] );

		$ballot = $data['ballot'];
		$tally = $data['tally'];
		$crypt = $p['encrypt-type'] ?? 'none';

		$formData = [
			'election_id' => $data['id'],
			'election_title' => $data['title'],
			'property_wiki' => $p['wikis-val'] ?? null,
			'election_primaryLang' => $data['lang'],
			'election_startdate' => $startDate->format( 'Y-m-d\TH:i:s.0\Z' ),
			'election_enddate' => $endDate->format( 'Y-m-d\TH:i:s.0\Z' ),
			'return-url' => $p['return-url'] ?? null,
			'jump-text' => $m['jump-text'] ?? null,
			'election_type' => "{$ballot}+{$tally}",
			'election_crypt' => $crypt,
			'disallow-change' => isset( $p['disallow-change'] ) ? (bool)$p['disallow-change'] : null,
			'voter-privacy' => isset( $p['voter-privacy'] ) ? (bool)$p['voter-privacy'] : null,
			'property_admins' => '',
			'request-comment' => isset( $p['request-comment'] ) ? (bool)$p['request-comment'] : null,
			'comment-prompt' => $m['comment-prompt'] ?? null,
			'questions' => [],
			'comment' => '',
		];

		if ( isset( $data['properties']['admins'] ) ) {
			// HTMLUsersMultiselectField takes a line-separated string
			$formData['property_admins'] = implode( "\n", explode( '|', $data['properties']['admins'] ) );
		}

		$classes = [];
		$tallyTypes = [];
		foreach ( $this->context->getBallotTypesForVote() as $class ) {
			$classes[] = $class;
			foreach ( call_user_func_array( [ $class, 'getTallyTypes' ], [] ) as $type ) {
				$tallyTypes[$type] = true;
			}
		}
		foreach ( Tallier::$tallierTypes as $type => $class ) {
			if ( isset( $tallyTypes[$type] ) ) {
				$classes[] = $class;
			}
		}
		foreach ( Crypt::$cryptTypes as $class ) {
			if ( $class !== false ) {
				$classes[] = $class;
			}
		}

		foreach ( $classes as $class ) {
			self::unprocessFormData( $formData, $data, $class, 'election' );
		}

		foreach ( $data['questions'] as $question ) {
			$q = [
				'text' => $question['messages']['text'],
			];
			if ( isset( $question['id'] ) ) {
				$q['id'] = $question['id'];
			}

			foreach ( $classes as $class ) {
				self::unprocessFormData( $q, $question, $class, 'question' );
			}

			// Process options for this question
			foreach ( $question['options'] as $option ) {
				$o = [
					'text' => $option['messages']['text'],
				];
				if ( isset( $option['id'] ) ) {
					$o['id'] = $option['id'];
				}

				foreach ( $classes as $class ) {
					self::unprocessFormData( $o, $option, $class, 'option' );
				}

				$q['options'][] = $o;
			}

			$formData['questions'][] = $q;
		}

		return $formData;
	}

	/**
	 * Insert an entry into the securepoll_entities table, and return the ID
	 *
	 * @param IDatabase $dbw
	 * @param string $type Entity type
	 * @return int
	 */
	private static function insertEntity( $dbw, $type ) {
		$dbw->insert(
			'securepoll_entity',
			[
				'en_type' => $type,
			],
			__METHOD__
		);

		return $dbw->insertId();
	}

	/**
	 * Save properties and messages for an entity
	 *
	 * @param IDatabase $dbw
	 * @param int $id
	 * @param Entity $entity
	 */
	private static function savePropertiesAndMessages( $dbw, $id, $entity ) {
		$properties = [];
		foreach ( $entity->getAllProperties() as $key => $value ) {
			$properties[] = [
				'pr_entity' => $id,
				'pr_key' => $key,
				'pr_value' => $value,
			];
		}
		$dbw->replace(
			'securepoll_properties',
			[
				[
					'pr_entity',
					'pr_key'
				]
			],
			$properties,
			__METHOD__
		);

		$messages = [];
		$langs = $entity->getLangList();
		foreach ( $entity->getMessageNames() as $name ) {
			foreach ( $langs as $lang ) {
				$value = $entity->getRawMessage( $name, $lang );
				if ( $value !== false ) {
					$messages[] = [
						'msg_entity' => $id,
						'msg_lang' => $lang,
						'msg_key' => $name,
						'msg_text' => $value,
					];
				}
			}
		}
		$dbw->replace(
			'securepoll_msgs',
			[
				[
					'msg_entity',
					'msg_lang',
					'msg_key'
				]
			],
			$messages,
			__METHOD__
		);
	}

	/**
	 * Combine form items for the class into the main array
	 *
	 * @param array &$outItems Array to insert the descriptors into
	 * @param string $field Owning field name, for hide-if
	 * @param string|array $types Type value(s) in the field, for hide-if
	 * @param class-string|false $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 * @param bool|null $disabled Should the field be disabled
	 */
	private static function processFormItems(
		&$outItems, $field, $types, $class,
		$category = null,
		$disabled = false
	) {
		if ( $class === false ) {
			return;
		}

		$items = call_user_func_array(
			[
				$class,
				'getCreateDescriptors'
			],
			[]
		);

		if ( !is_array( $types ) ) {
			$types = [ $types ];
		}

		if ( $category ) {
			if ( !isset( $items[$category] ) ) {
				return;
			}
			$items = $items[$category];
		}

		foreach ( $items as $key => $item ) {
			if ( $disabled ) {
				$item['disabled'] = true;
			}
			if ( !isset( $outItems[$key] ) ) {
				if ( !isset( $item['hide-if'] ) ) {
					$item['hide-if'] = [
						'OR',
						[ 'AND' ]
					];
				} else {
					$item['hide-if'] = [
						'OR',
						[ 'AND' ],
						$item['hide-if']
					];
				}
				$outItems[$key] = $item;
			} else {
				// @todo Detect if this is really the same descriptor?
			}
			foreach ( $types as $type ) {
				$outItems[$key]['hide-if'][1][] = [
					'!==',
					$field,
					$type
				];
			}
		}
	}

	/**
	 * Inject form field values for the class's properties and messages
	 *
	 * @param array &$formData Form data array
	 * @param array $data Input data array
	 * @param class-string|false $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 */
	private static function unprocessFormData( &$formData, $data, $class, $category ) {
		if ( $class === false ) {
			return;
		}

		$items = call_user_func_array(
			[
				$class,
				'getCreateDescriptors'
			],
			[]
		);

		if ( $category ) {
			if ( !isset( $items[$category] ) ) {
				return;
			}
			$items = $items[$category];
		}

		foreach ( $items as $key => $item ) {
			if ( !isset( $item['SecurePoll_type'] ) ) {
				continue;
			}
			switch ( $item['SecurePoll_type'] ) {
				case 'property':
					if ( isset( $data['properties'][$key] ) ) {
						$formData[$key] = $data['properties'][$key];
					} else {
						$formData[$key] = null;
					}
					break;
				case 'properties':
					$formData[$key] = [];
					foreach ( $data['properties'] as $k => $v ) {
						$formData[$key][$k] = $v;
					}
					break;
				case 'message':
					if ( isset( $data['messages'][$key] ) ) {
						$formData[$key] = $data['messages'][$key];
					} else {
						$formData[$key] = null;
					}
					break;
				case 'messages':
					$formData[$key] = [];
					foreach ( $data['messages'] as $k => $v ) {
						$formData[$key][$k] = $v;
					}
					break;
			}
		}
	}

	/**
	 * Check that the user is part of the electionadmin group
	 *
	 * @param string $value Username
	 * @param array $alldata All form data
	 * @param HTMLForm $containingForm Containing HTMLForm
	 * @return bool|string true on success, string on error
	 */
	public function checkIfInElectionAdminUserGroup( $value, $alldata, HTMLForm $containingForm ) {
		$user = $this->userFactory->newFromName( $value );
		if ( !$user || !in_array( 'electionadmin', $this->userGroupManager->getUserEffectiveGroups( $user ) ) ) {
			return $this->msg(
				'securepoll-create-user-not-in-electionadmin-group',
				$value
			)->parse();
		}

		return true;
	}

	public function checkElectionEndDate( $value, $formData ) {
		$startDate = new DateTime( $formData['election_startdate'], new DateTimeZone( 'GMT' ) );
		$endDate = new DateTime( $value, new DateTimeZone( 'GMT' ) );

		if ( $startDate >= $endDate ) {
			return $this->msg( 'securepoll-htmlform-daterange-end-before-start' )->parseAsBlock();
		}

		return true;
	}

	/**
	 * Check that a required field has been filled.
	 *
	 * This is a hack for using with cloner fields. Just setting required=true
	 * breaks cloner fields when used with OOUI, in no-JS environments, because
	 * the browser will prevent submission on clicking the remove button of an
	 * empty field.
	 *
	 * @internal For use by the HTMLFormField
	 * @param string $value
	 * @return true|Message true on success, Message on error
	 */
	public static function checkRequired( $value ) {
		if ( $value === '' ) {
			return Status::newFatal( 'htmlform-required' )->getMessage();
		}
		return true;
	}
}
