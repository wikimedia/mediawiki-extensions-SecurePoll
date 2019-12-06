<?php

use MediaWiki\MediaWikiServices;

/**
 * Special:SecurePoll subpage for creating or editing a poll
 */
class SecurePoll_CreatePage extends SecurePoll_ActionPage {
	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 * @throws MWException
	 * @throws PermissionsError
	 */
	public function execute( $params ) {
		global $wgSecurePollCreateWikiGroupDir, $wgSecurePollCreateWikiGroups;
		global $wgSecurePollUseNamespace;

		$out = $this->specialPage->getOutput();

		if ( $params ) {
			$out->setPageTitle( $this->msg( 'securepoll-edit-title' ) );
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

			$jumpUrl = $this->election->getProperty( 'jump-url' );
			if ( $jumpUrl ) {
				$jumpId = $this->election->getProperty( 'jump-id' );
				if ( !$jumpId ) {
					throw new MWException( 'Configuration error: no jump-id' );
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
			$out->setPageTitle( $this->msg( 'securepoll-create-title' ) );
			if ( !$this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
				throw new PermissionsError( 'securepoll-create-poll' );
			}
		}

		$out->addModules( 'ext.securepoll.htmlform' );
		$out->addModules( 'ext.securepoll' );
		$out->setPageTitle( $this->msg( 'securepoll-create-title' ) );

		# These are for injecting raw HTML into the HTMLForm for the
		# multi-column aspects of the designed layout.
		$layoutTableStart = [
			'type' => 'info',
			'rawrow' => true,
			'default' => '<table class="securepoll-layout-table"><tr><td>',
		];
		$layoutTableMid = [
			'type' => 'info',
			'rawrow' => true,
			'default' => '</td><td>',
		];
		$layoutTableEnd = [
			'type' => 'info',
			'rawrow' => true,
			'default' => '</td></tr></table>',
		];

		$formItems = [];

		$formItems['election_id'] = [
			'type' => 'hidden',
			'default' => -1,
			'output-as-default' => false,
		];

		$formItems['election_title'] = [
			'label-message' => 'securepoll-create-label-election_title',
			'type' => 'text',
			'required' => true,
		];

		$wikiNames = SecurePoll_FormStore::getWikiList();
		$options = [];
		$options['securepoll-create-option-wiki-this_wiki'] = wfWikiID();
		if ( count( $wikiNames ) > 1 ) {
			$options['securepoll-create-option-wiki-all_wikis'] = '*';
		}
		foreach ( $wgSecurePollCreateWikiGroups as $file => $msg ) {
			if ( is_readable( "$wgSecurePollCreateWikiGroupDir$file.dblist" ) ) {
				$options[$msg] = "@$file";
			}
		}

		if ( count( $wikiNames ) <= 1 && count( $options ) === 1 ) {
			// Only option is wfWikiID(), so don't bother making the user select it.
		} elseif ( count( $wikiNames ) < 10 ) {
			// So few, we may as well just list them explicitly
			$opts = [];
			foreach ( $options as $msg => $value ) {
				$opts[$this->msg( $msg )->plain()] = $value;
			}
			$key = array_search( wfWikiID(), $wikiNames, true );
			if ( $key !== false ) {
				unset( $wikiNames[$key] );
			}
			if ( $wikiNames ) {
				$opts[$this->msg( 'securepoll-create-option-wiki-other_wiki' )->plain(
				)] = $wikiNames;
			}
			$formItems['property_wiki'] = [
				'type' => 'select',
				'options' => $opts,
				'label-message' => 'securepoll-create-label-wiki',
			];
		} else {
			$options['securepoll-create-option-wiki-other_wiki'] = 'other';
			$formItems['property_wiki'] = [
				'type' => 'autocompleteselect',
				'autocomplete-data' => $wikiNames,
				'options-messages' => $options,
				'label-message' => 'securepoll-create-label-wiki',
				'require-match' => true,
				'default' => wfWikiID(),
			];
		}

		$languages = Language::fetchLanguageNames( null, 'mw' );
		ksort( $languages );
		$options = [];
		foreach ( $languages as $code => $name ) {
			$display = LanguageCode::bcp47( $code ) . ' - ' . $name;
			$options[$display] = $code;
		}
		$formItems['election_primaryLang'] = [
			'type' => 'select',
			'options' => $options,
			'label-message' => 'securepoll-create-label-election_primaryLang',
			'default' => 'en',
			'required' => true,
		];

		$formItems['election_startdate'] = [
			'label-message' => 'securepoll-create-label-election_startdate',
			'type' => 'date',
			'required' => true,
			'min' => gmdate( 'M-d-Y' ),
		];

		$days = [];
		for ( $i = 1; $i <= 28; $i++ ) {
			$days[$i] = $i;
		}
		$formItems['election_duration'] = [
			'type' => 'select',
			'label-message' => 'securepoll-create-label-election_duration',
			'required' => true,
			'options' => $days,
		];

		$formItems['return-url'] = [
			'label-message' => 'securepoll-create-label-election_return-url',
			'type' => 'url',
		];

		if ( isset( $formItems['property_wiki'] ) ) {
			$formItems['jump-text'] = [
				'label-message' => 'securepoll-create-label-election_jump-text',
				'type' => 'text',
			];
			if ( $formItems['property_wiki']['type'] === 'select' ) {
				$formItems['jump-text']['hide-if'] = [
					'===',
					'property_wiki',
					wfWikiId()
				];
			} else {
				$formItems['jump-text']['hide-if'] = [
					'===',
					'property_wiki-select',
					wfWikiId()
				];
			}
		}

		$formItems[] = $layoutTableStart;

		$formItems['election_type'] = [
			'label-message' => 'securepoll-create-label-election_type',
			'type' => 'radio',
			'options-messages' => [],
			'required' => true,
		];

		if ( count( SecurePoll_Crypt::$cryptTypes ) > 1 ) {
			$formItems[] = $layoutTableMid;
			$formItems['election_crypt'] = [
				'label-message' => 'securepoll-create-label-election_crypt',
				'type' => 'radio',
				'options-messages' => [],
				'required' => true,
			];
		} else {
			reset( SecurePoll_Crypt::$cryptTypes );
			$formItems['election_crypt'] = [
				'type' => 'hidden',
				'default' => key( SecurePoll_Crypt::$cryptTypes ),
				'options-messages' => [],
				// dummy, ignored
			];
		}

		$formItems[] = $layoutTableEnd;

		$formItems['disallow-change'] = [
			'label-message' => 'securepoll-create-label-election_disallow-change',
			'type' => 'check',
			'hidelabel' => true,
		];

		$formItems['voter-privacy'] = [
			'label-message' => 'securepoll-create-label-voter_privacy',
			'type' => 'check',
			'hidelabel' => true,
		];

		$formItems['property_admins'] = [
			'label-message' => 'securepoll-create-label-property_admins',
			'type' => 'cloner',
			'required' => true,
			'format' => 'raw',
			'fields' => [
				'username' => [
					'type' => 'text',
					'required' => true,
					'validation-callback' => [
						$this,
						'checkUsername'
					],
				],
			],
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
				'required' => true,
			],
			'delete' => [
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-questions-delete' )->text(),
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
				'required' => true,
			],
			'delete' => [
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-options-delete' )->text(),
			],
		];

		$tallyTypes = [];
		foreach ( SecurePoll_Ballot::$ballotTypes as $ballotType => $ballotClass ) {
			$types = [];
			foreach (
				call_user_func_array( [ $ballotClass, 'getTallyTypes' ], [] ) as $tallyType
			) {
				$type = "$ballotType+$tallyType";
				$types[] = $type;
				$tallyTypes[$tallyType][] = $type;
				$formItems['election_type']['options-messages']
				["securepoll-create-option-election_type-$type"] = $type;
			}
			self::processFormItems(
				$formItems,
				'election_type',
				$types,
				$ballotClass,
				'election'
			);
			self::processFormItems(
				$questionFields,
				'election_type',
				$types,
				$ballotClass,
				'question'
			);
			self::processFormItems(
				$optionFields,
				'election_type',
				$types,
				$ballotClass,
				'option'
			);
		}

		foreach ( SecurePoll_Tallier::$tallierTypes as $type => $class ) {
			if ( !isset( $tallyTypes[$type] ) ) {
				continue;
			}
			self::processFormItems(
				$formItems,
				'election_type',
				$tallyTypes[$type],
				$class,
				'election'
			);
			self::processFormItems(
				$questionFields,
				'election_type',
				$tallyTypes[$type],
				$class,
				'question'
			);
			self::processFormItems(
				$optionFields,
				'election_type',
				$tallyTypes[$type],
				$class,
				'option'
			);
		}

		foreach ( SecurePoll_Crypt::$cryptTypes as $type => $class ) {
			$formItems['election_crypt']['options-messages']
			["securepoll-create-option-election_crypt-$type"] = $type;
			if ( $class !== false ) {
				self::processFormItems(
					$formItems,
					'election_crypt',
					$type,
					$class,
					'election'
				);
				self::processFormItems(
					$questionFields,
					'election_crypt',
					$type,
					$class,
					'question'
				);
				self::processFormItems(
					$optionFields,
					'election_crypt',
					$type,
					$class,
					'option'
				);
			}
		}

		$questionFields['options'] = [
			'label-message' => 'securepoll-create-label-questions-option',
			'type' => 'cloner',
			'required' => true,
			'create-button-message' => 'securepoll-create-label-options-add',
			'fields' => $optionFields,
		];

		$formItems['questions'] = [
			'label-message' => 'securepoll-create-label-questions',
			'type' => 'cloner',
			'required' => true,
			'row-legend' => 'securepoll-create-questions-row-legend',
			'create-button-message' => 'securepoll-create-label-questions-add',
			'fields' => $questionFields,
		];

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-create-label-comment',
				'maxlength' => 250,
			];
		}

		$form = HTMLForm::factory(
			'div',
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
				'processInput'
			]
		);
		$form->prepareForm();

		// If this isn't the result of a POST, load the data from the election
		$request = $this->specialPage->getRequest();
		if ( $this->election && !( $request->wasPosted() && $request->getCheck(
					'wpEditToken'
				) )
		) {
			$form->mFieldData = $this->getFormDataFromElection( $this->election );
		}

		$result = $form->tryAuthorizedSubmit();
		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			if ( $this->election ) {
				$out->setPageTitle( $this->msg( 'securepoll-edit-edited' ) );
				$out->addWikiMsg( 'securepoll-edit-edited-text' );
			} else {
				$out->setPageTitle( $this->msg( 'securepoll-create-created' ) );
				$out->addWikiMsg( 'securepoll-create-created-text' );
			}
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		} else {
			$form->displayForm( $result );
		}
	}

	public function processInput( $formData, $form ) {
		global $wgSecurePollUseNamespace;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		try {
			$context = new SecurePoll_Context;
			$userId = $this->specialPage->getUser()->getId();
			$store = new SecurePoll_FormStore( $formData, $userId );
			$context->setStore( $store );
			$election = $context->getElection( $store->eId );

			if ( $this->election && $store->eId !== (int)$this->election->getId() ) {
				return Status::newFatal( 'securepoll-create-fail-bad-id' );
			}

			$dbw = $this->context->getDB();

			// Check for duplicate titles on the local wiki
			$id = $dbw->selectField(
				'securepoll_elections',
				'el_entity',
				[ 'el_title' => $election->title ],
				__METHOD__,
				[ 'FOR UPDATE' ]
			);
			if ( $id && (int)$id !== $election->getId() ) {
				throw new SecurePoll_StatusException(
					'securepoll-create-duplicate-title',
					SecurePoll_FormStore::getWikiName( wfWikiId() ),
					wfWikiID()
				);
			}

			// Check for duplicate titles on jump wikis too
			// (There's the possibility for a race here, but hopefully it won't
			// matter in practice)
			if ( $store->rId ) {
				foreach ( $store->remoteWikis as $dbname ) {
					$lb = $lbFactory->getMainLB( $dbname );
					$rdbw = $lb->getConnection( DB_MASTER, [], $dbname );

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
							'p2.pr_value' => wfWikiID(),
						]
					);
					// Test for duplicate title
					$id = $rdbw->selectField(
						'securepoll_elections',
						'el_entity',
						[
							'el_title' => $formData['election_title']
						]
					);

					$lb->reuseConnection( $rdbw );

					if ( $id && $id !== $rId ) {
						throw new SecurePoll_StatusException(
							'securepoll-create-duplicate-title',
							SecurePoll_FormStore::getWikiName( $dbname ),
							$dbname
						);
					}
				}
			}
		} catch ( SecurePoll_StatusException $ex ) {
			return $ex->status;
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
		} else {
			$eId = $election->getId();
			$dbw->update( 'securepoll_elections', $fields, [ 'el_entity' => $eId ], __METHOD__ );

			// Delete any questions or options that weren't included in the
			// form submission.
			$qIds = [];
			$res = $dbw->select( 'securepoll_questions', 'qu_entity', [ 'qu_election' => $eId ] );
			foreach ( $res as $row ) {
				$qIds[] = $row->qu_entity;
			}
			$oIds = [];
			$res = $dbw->select( 'securepoll_options', 'op_entity', [ 'op_election' => $eId ] );
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
				[ 'qu_entity' ],
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
					[ 'op_entity' ],
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

		// Create the "redirect" polls on all the local wikis
		if ( $store->rId ) {
			$election = $context->getElection( $store->rId );
			foreach ( $store->remoteWikis as $dbname ) {
				$lb = $lbFactory->getMainLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, [], $dbname );
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
						'p2.pr_value' => wfWikiID(),
					]
				);
				if ( !$rId ) {
					$rId = self::insertEntity( $dbw, 'election' );
				}

				// Insert it! We don't have to care about questions or options here.
				$dbw->replace(
					'securepoll_elections',
					[ 'el_entity' ],
					[
						'el_entity' => $rId,
						'el_title' => $election->title,
						'el_ballot' => $election->ballotType,
						'el_tally' => $election->tallyType,
						'el_primary_lang' => $election->getLanguage(),
						'el_start_date' => $dbw->timestamp( $election->getStartDate() ),
						'el_end_date' => $dbw->timestamp( $election->getEndDate() ),
						'el_auth_type' => $election->authType,
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

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching.
			$context = new SecurePoll_Context;
			// We may be inside a transaction, so force a master connection (T209804)
			$context->setStore( new SecurePoll_DBStore( true ) );

			$election = $context->getElection( $eId );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $formData['comment'] );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election,
				'msg/' . $election->getLanguage()
			);
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $formData['comment'] );
		}

		return Status::newGood( $eId );
	}

	/**
	 * Recreate the form data from an election
	 *
	 * @param SecurePoll_Election $election
	 * @return array
	 */
	private function getFormDataFromElection( $election ) {
		$lang = $this->election->getLanguage();
		$data = array_replace_recursive(
			SecurePollContentHandler::getDataFromElection( $this->election, "msg/$lang" ),
			SecurePollContentHandler::getDataFromElection( $this->election )
		);
		$p = &$data['properties'];
		$m = &$data['messages'];

		$startDate = new MWTimestamp( $data['startDate'] );
		$endDate = new MWTimestamp( $data['endDate'] );
		$duration = $endDate->diff( $startDate )->format( '%a' );

		$ballot = $data['ballot'];
		$tally = $data['tally'];
		$crypt = $p['encrypt-type'] ?? 'none';

		$formData = [
			'election_id' => $data['id'],
			'election_title' => $data['title'],
			'property_wiki' => $p['wikis-val'] ?? null,
			'election_primaryLang' => $data['lang'],
			'election_startdate' => $startDate->format( 'Y-m-d' ),
			'election_duration' => $duration,
			'return-url' => $p['return-url'] ?? null,
			'jump-text' => $m['jump-text'] ?? null,
			'election_type' => "{$ballot}+{$tally}",
			'election_crypt' => $crypt,
			'disallow-change' => isset( $p['disallow-change'] ) ? (bool)$p['disallow-change'] : null,
			'voter-privacy' => isset( $p['voter-privacy'] ) ? (bool)$p['voter-privacy'] : null,
			'property_admins' => [],
			'questions' => [],
			'comment' => '',
		];

		if ( isset( $data['properties']['admins'] ) ) {
			foreach ( explode( '|', $data['properties']['admins'] ) as $admin ) {
				$formData['property_admins'][] = [ 'username' => $admin ];
			}
		}

		$classes = [];
		$tallyTypes = [];
		foreach ( SecurePoll_Ballot::$ballotTypes as $class ) {
			$classes[] = $class;
			foreach ( call_user_func_array( [ $class, 'getTallyTypes' ], [] ) as $type ) {
				$tallyTypes[$type] = true;
			}
		}
		foreach ( SecurePoll_Tallier::$tallierTypes as $type => $class ) {
			if ( isset( $tallyTypes[$type] ) ) {
				$classes[] = $class;
			}
		}
		foreach ( SecurePoll_Crypt::$cryptTypes as $class ) {
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
	 * @param \Wikimedia\Rdbms\IDatabase $dbw
	 * @param string $type Entity type
	 * @return int
	 */
	private static function insertEntity( $dbw, $type ) {
		$id = $dbw->nextSequenceValue( 'securepoll_en_id_seq' );
		$dbw->insert(
			'securepoll_entity',
			[
				'en_id' => $id,
				'en_type' => $type,
			],
			__METHOD__
		);
		$id = $dbw->insertId();

		return $id;
	}

	/**
	 * Save properties and messages for an entity
	 *
	 * @param \Wikimedia\Rdbms\IDatabase $dbw
	 * @param int $id
	 * @param SecurePoll_Entity $entity
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
	 * @param string|false $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 */
	private static function processFormItems( &$outItems, $field, $types, $class, $category = null ) {
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
	 * @param string|false $class Class with the ::getCreateDescriptors static method
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
	 * Check a username for validity.
	 *
	 * @param string $value Username
	 * @param array $alldata All form data
	 * @param HTMLForm $containingForm Containing HTMLForm
	 * @return bool|string true on success, string on error
	 */
	public function checkUsername( $value, $alldata, HTMLForm $containingForm ) {
		$user = User::newFromName( $value );
		if ( !$user ) {
			return $this->msg( 'securepoll-create-invalid-username' )->parse();
		}
		if ( !$user->isLoggedIn() ) {
			return $this->msg( 'securepoll-create-user-does-not-exist' )->parse();
		}

		return true;
	}
}
