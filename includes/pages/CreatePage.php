<?php

/**
 * Special:SecurePoll subpage for creating or editing a poll
 */
class SecurePoll_CreatePage extends SecurePoll_Page {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		global $wgUser, $wgSecurePollCreateWikiGroupDir, $wgSecurePollCreateWikiGroups;
		global $wgSecurePollUseNamespace;

		$out = $this->parent->getOutput();

		if ( $params ) {
			$out->setPageTitle( $this->msg( 'securepoll-edit-title' ) );
			$electionId = intval( $params[0] );
			$this->election = $this->context->getElection( $electionId );
			if ( !$this->election ) {
				$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
				return;
			}
			if ( !$this->election->isAdmin( $this->parent->getUser() ) ) {
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
					$jumpUrl .= '/' . join( '/', array_slice( $params, 1 ) );
				}

				$wiki = $this->election->getProperty( 'main-wiki' );
				if ( $wiki ) {
					$wiki = WikiMap::getWikiName( $wiki );
				} else {
					$wiki = $this->msg( 'securepoll-edit-redirect-otherwiki' )->text();
				}

				$out->addWikiMsg( 'securepoll-edit-redirect',
					Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
				);

				return;
			}
		} else {
			$out->setPageTitle( $this->msg( 'securepoll-create-title' ) );
			if ( !$wgUser->isAllowed( 'securepoll-create-poll' ) ) {
				throw new PermissionsError( 'securepoll-create-poll' );
			}
		}

		/** @todo These should be migrated to core, once the jquery.ui
		 * objectors write their own date picker. */
		if ( !isset( HTMLForm::$typeMappings['date'] ) || !isset( HTMLForm::$typeMappings['daterange'] ) ) {
			HTMLForm::$typeMappings['date'] = 'SecurePoll_HTMLDateField';
			HTMLForm::$typeMappings['daterange'] = 'SecurePoll_HTMLDateRangeField';
		}

		$this->parent->getOutput()->addModules( 'ext.securepoll.htmlform' );
		$this->parent->getOutput()->addModules( 'ext.securepoll' );

		# These are for injecting raw HTML into the HTMLForm for the
		# multi-column aspects of the designed layout.
		$layoutTableStart = array(
			'type' => 'info',
			'rawrow' => true,
			'default' => '<table class="securepoll-layout-table"><tr><td>',
		);
		$layoutTableMid = array(
			'type' => 'info',
			'rawrow' => true,
			'default' => '</td><td>',
		);
		$layoutTableEnd = array(
			'type' => 'info',
			'rawrow' => true,
			'default' => '</td></tr></table>',
		);

		$formItems = array();

		$formItems['election_id'] = array(
			'type' => 'hidden',
			'default' => -1,
			'output-as-default' => false,
		);

		$formItems['election_title'] = array(
			'label-message' => 'securepoll-create-label-election_title',
			'type' => 'text',
			'required' => true,
		);

		$wikiNames = SecurePoll_FormStore::getWikiList();
		$options = array();
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
			$opts = array();
			foreach ( $options as $msg => $value ) {
				$opts[$this->msg( $msg )->plain()] = $value;
			}
			$key = array_search( wfWikiID(), $wikiNames, true );
			if ( $key !== false ) {
				unset( $wikiNames[$key] );
			}
			if ( $wikiNames ) {
				$opts[$this->msg( 'securepoll-create-option-wiki-other_wiki' )->plain()] = $wikiNames;
			}
			$formItems['property_wiki'] = array(
				'type' => 'select',
				'options' => $opts,
				'label-message' => 'securepoll-create-label-wiki',
				'required' => true,
			);
		} else {
			$options['securepoll-create-option-wiki-other_wiki'] = 'other';
			$formItems['property_wiki'] = array(
				'type' => 'autocompleteselect',
				'autocomplete' => $wikiNames,
				'options-messages' => $options,
				'label-message' => 'securepoll-create-label-wiki',
				'required' => true,
				'require-match' => true,
				'default' => wfWikiID(),
			);
		}

		$languages = Language::fetchLanguageNames( null, 'mw' );
		ksort( $languages );
		$options = array();
		foreach ( $languages as $code => $name ) {
			$display = wfBCP47( $code ) . ' - ' . $name;
			$options[$display] = $code;
		}
		$formItems['election_primaryLang'] = array(
			'type' => 'select',
			'options' => $options,
			'label-message' => 'securepoll-create-label-election_primaryLang',
			'default' => 'en',
			'required' => true,
		);

		$days = array();
		for ( $i = 1; $i <= 28; $i++ ) {
			$days[$i] = $i;
		}
		$formItems['election_dates'] = array(
			'label-message' => 'securepoll-create-label-election_dates',
			'layout-message' => 'securepoll-create-layout-election_dates',
			'type' => 'daterange',
			'required' => true,
			'min' => gmdate( 'M-d-Y' ),
			'options' => $days,
		);

		$formItems['return-url'] = array(
			'label-message' => 'securepoll-create-label-election_return-url',
			'type' => 'url',
		);

		if ( isset( $formItems['property_wiki'] ) ) {
			$formItems['jump-text'] = array(
				'label-message' => 'securepoll-create-label-election_jump-text',
				'type' => 'text',
			);
			if ( $formItems['property_wiki']['type'] === 'select' ) {
				$formItems['jump-text']['hide-if'] = array( '===', 'property_wiki', wfWikiId() );
			} else {
				$formItems['jump-text']['hide-if'] = array( '===', 'property_wiki-select', wfWikiId() );
			}
		}

		$formItems[] = $layoutTableStart;

		$formItems['election_type'] = array(
			'label-message' => 'securepoll-create-label-election_type',
			'type' => 'radio',
			'options-messages' => array(),
			'required' => true,
		);

		if ( count( SecurePoll_Crypt::$cryptTypes ) > 1 ) {
			$formItems[] = $layoutTableMid;
			$formItems['election_crypt'] = array(
				'label-message' => 'securepoll-create-label-election_crypt',
				'type' => 'radio',
				'options-messages' => array(),
				'required' => true,
			);
		} else {
			reset( SecurePoll_Crypt::$cryptTypes );
			$formItems['election_crypt'] = array(
				'type' => 'hidden',
				'default' => key( SecurePoll_Crypt::$cryptTypes ),
				'options-messages' => array(), // dummy, ignored
			);
		}

		$formItems[] = $layoutTableEnd;

		$formItems['disallow-change'] = array(
			'label-message' => 'securepoll-create-label-election_disallow-change',
			'type' => 'check',
			'hidelabel' => true,
		);

		$formItems['property_admins'] = array(
			'label-message' => 'securepoll-create-label-property_admins',
			'type' => 'cloner',
			'required' => true,
			'format' => 'raw',
			'fields' => array(
				'username' => array(
					'type' => 'text',
					'required' => true,
					'validation-callback' => array( $this, 'checkUsername' ),
				),
			),
		);

		$questionFields = array(
			'id' => array(
				'type' => 'hidden',
				'default' => -1,
				'output-as-default' => false,
			),
			'text' => array(
				'label-message' => 'securepoll-create-label-questions-question',
				'type' => 'text',
				'required' => true,
			),
			'delete' => array(
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-questions-delete' )->text(),
			),
		);

		$optionFields = array(
			'id' => array(
				'type' => 'hidden',
				'default' => -1,
				'output-as-default' => false,
			),
			'text' => array(
				'label-message' => 'securepoll-create-label-options-option',
				'type' => 'text',
				'required' => true,
			),
			'delete' => array(
				'type' => 'submit',
				'default' => $this->msg( 'securepoll-create-label-options-delete' )->text(),
			),
		);

		$tallyTypes = array();
		foreach ( SecurePoll_Ballot::$ballotTypes as $ballotType => $ballotClass ) {
			$types = array();
			foreach ( call_user_func_array( array( $ballotClass, 'getTallyTypes' ), array() ) as $tallyType ) {
				$type = "$ballotType+$tallyType";
				$types[] = $type;
				$tallyTypes[$tallyType][] = $type;
				$formItems['election_type']['options-messages']["securepoll-create-option-election_type-$type"] = $type;
			}
			self::processFormItems( $formItems, 'election_type', $types, $ballotClass, 'election' );
			self::processFormItems( $questionFields, 'election_type', $types, $ballotClass, 'question' );
			self::processFormItems( $optionFields, 'election_type', $types, $ballotClass, 'option' );
		}

		foreach ( SecurePoll_Tallier::$tallierTypes as $type => $class ) {
			if ( !isset( $tallyTypes[$type] ) ) {
				continue;
			}
			self::processFormItems( $formItems, 'election_type', $tallyTypes[$type], $class, 'election' );
			self::processFormItems( $questionFields, 'election_type', $tallyTypes[$type], $class, 'question' );
			self::processFormItems( $optionFields, 'election_type', $tallyTypes[$type], $class, 'option' );
		}

		foreach ( SecurePoll_Crypt::$cryptTypes as $type => $class ) {
			$formItems['election_crypt']['options-messages']["securepoll-create-option-election_crypt-$type"] = $type;
			if ( $class !== false ) {
				self::processFormItems( $formItems, 'election_crypt', $type, $class, 'election' );
				self::processFormItems( $questionFields, 'election_crypt', $type, $class, 'question' );
				self::processFormItems( $optionFields, 'election_crypt', $type, $class, 'option' );
			}
		}

		$questionFields['options'] = array(
			'label-message' => 'securepoll-create-label-questions-option',
			'type' => 'cloner',
			'required' => true,
			'create-button-message' => 'securepoll-create-label-options-add',
			'fields' => $optionFields,
		);

		$formItems['questions'] = array(
			'label-message' => 'securepoll-create-label-questions',
			'type' => 'cloner',
			'required' => true,
			'row-legend' => 'securepoll-create-questions-row-legend',
			'create-button-message' => 'securepoll-create-label-questions-add',
			'fields' => $questionFields,
		);

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = array(
				'type' => 'text',
				'label-message' => 'securepoll-create-label-comment',
			);
		}

		$form = new HTMLForm( $formItems, $this->parent->getContext(),
			$this->election ? 'securepoll-edit' : 'securepoll-create'
		);
		$form->setDisplayFormat( 'div' );
		$form->setSubmitTextMsg( $this->election ? 'securepoll-edit-action' : 'securepoll-create-action' );
		$form->setSubmitCallback( array( $this, 'processInput' ) );
		$form->prepareForm();

		// If this isn't the result of a POST, load the data from the election
		$request = $this->parent->getRequest();
		if ( $this->election && !( $request->wasPosted() && $request->getCheck( 'wpEditToken' ) ) ) {
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

		try {
			$context = new SecurePoll_Context;
			$store = new SecurePoll_FormStore( $formData );
			$context->setStore( $store );
			$election = $context->getElection( $store->eId );

			if ( $this->election && $store->eId !== (int)$this->election->getId() ) {
				return Status::newFatal( 'securepoll-create-fail-bad-id' );
			}

			$dbw = $this->context->getDB();
			$properties = array();
			$messages = array();

			// Check for duplicate titles on the local wiki
			$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
				'el_title' => $election->title
			), __METHOD__, array( 'FOR UPDATE' ) );
			if ( $id && (int)$id !== $election->getId() ) {
				throw new SecurePoll_StatusException( 'securepoll-create-duplicate-title',
					SecurePoll_FormStore::getWikiName( wfWikiId() ), wfWikiID()
				);
			}

			// Check for duplicate titles on jump wikis too
			// (There's the possibility for a race here, but hopefully it won't
			// matter in practice)
			if ( $store->rId ) {
				foreach ( $store->remoteWikis as $dbname ) {
					$lb = wfGetLB( $dbname );
					$rdbw = $lb->getConnection( DB_MASTER, array(), $dbname );

					// Find an existing dummy election, if any
					$rId = $rdbw->selectField(
						array( 'p1' => 'securepoll_properties', 'p2' => 'securepoll_properties' ),
						'p1.pr_entity',
						array(
							'p1.pr_entity = p2.pr_entity',
							'p1.pr_key' => 'jump-id',
							'p1.pr_value' => $election->getId(),
							'p2.pr_key' => 'main-wiki',
							'p2.pr_value' => wfWikiID(),
						)
					);

					// Test for duplicate title
					$id = $rdbw->selectField( 'securepoll_elections', 'el_entity', array(
						'el_title' => $formData['election_title']
					) );
					if ( $id && $id !== $rId ) {
						throw new SecurePoll_StatusException( 'securepoll-create-duplicate-title',
							SecurePoll_FormStore::getWikiName( $dbname ), $dbname
						);
					}
					$lb->reuseConnection( $rdbw );
				}
			}

			// Ok, begin the actual work
			$dbw->begin();
			try {
				if ( $election->getId() > 0 ) {
					$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
						'el_entity' => $election->getId()
					), __METHOD__, array( 'FOR UPDATE' ) );
					if ( !$id ) {
						return Status::newFatal( 'securepoll-create-fail-id-missing' );
					}
				}

				// Insert or update the election entity
				$fields = array(
					'el_title' => $election->title,
					'el_ballot' => $election->ballotType,
					'el_tally' => $election->tallyType,
					'el_primary_lang' => $election->getLanguage(),
					'el_start_date' => $dbw->timestamp( $election->getStartDate() ),
					'el_end_date' => $dbw->timestamp( $election->getEndDate() ),
					'el_auth_type' => $election->authType,
				);
				if ( $election->getId() < 0 ) {
					$eId = self::insertEntity( $dbw, 'election' );
					$qIds = array();
					$oIds = array();
					$fields['el_entity'] = $eId;
					$dbw->insert( 'securepoll_elections', $fields, __METHOD__ );
				} else {
					$eId = $election->getId();
					$dbw->update( 'securepoll_elections', $fields, array( 'el_entity' => $eId ), __METHOD__ );

					// Delete any questions or options that weren't included in the
					// form submission.
					$qIds = array();
					$res = $dbw->select( 'securepoll_questions', 'qu_entity', array( 'qu_election' => $eId ) );
					foreach ( $res as $row ) {
						$qIds[] = $row->qu_entity;
					}
					$oIds = array();
					$res = $dbw->select( 'securepoll_options', 'op_entity', array( 'op_election' => $eId ) );
					foreach ( $res as $row ) {
						$oIds[] = $row->op_entity;
					}
					$deleteIds = array_merge(
						array_diff( $qIds, $store->qIds ),
						array_diff( $oIds, $store->oIds )
					);
					if ( $deleteIds ) {
						$dbw->delete( 'securepoll_msgs', array( 'msg_entity' => $deleteIds ), __METHOD__ );
						$dbw->delete( 'securepoll_properties', array( 'pr_entity' => $deleteIds ), __METHOD__ );
						$dbw->delete( 'securepoll_questions', array( 'qu_entity' => $deleteIds ), __METHOD__ );
						$dbw->delete( 'securepoll_options', array( 'op_entity' => $deleteIds ), __METHOD__ );
						$dbw->delete( 'securepoll_entity', array( 'en_id' => $deleteIds ), __METHOD__ );
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
					$dbw->replace( 'securepoll_questions',
						array( 'qu_entity' ),
						array(
							'qu_entity' => $qId,
							'qu_election' => $eId,
							'qu_index' => ++$qIndex,
						),
						__METHOD__
					);
					self::savePropertiesAndMessages( $dbw, $qId, $question );

					foreach ( $question->getOptions() as $option ) {
						$oId = $option->getId();
						if ( !in_array( $oId, $oIds ) ) {
							$oId = self::insertEntity( $dbw, 'option' );
						}
						$dbw->replace( 'securepoll_options',
							array( 'op_entity' ),
							array(
								'op_entity' => $oId,
								'op_election' => $eId,
								'op_question' => $qId,
							),
							__METHOD__
						);
						self::savePropertiesAndMessages( $dbw, $oId, $option );
					}
				}
				$dbw->commit();
			} catch ( Exception $ex ) {
				$dbw->rollback();
				throw $ex;
			}
		} catch ( SecurePoll_StatusException $ex ) {
			return $ex->status;
		}

		// Create the "redirect" polls on all the local wikis
		if ( $store->rId ) {
			$election = $context->getElection( $store->rId );
			foreach ( $store->remoteWikis as $dbname ) {
				$lb = wfGetLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, array(), $dbname );
				$dbw->begin();
				try {
					// Find an existing dummy election, if any
					$rId = $dbw->selectField(
						array( 'p1' => 'securepoll_properties', 'p2' => 'securepoll_properties' ),
						'p1.pr_entity',
						array(
							'p1.pr_entity = p2.pr_entity',
							'p1.pr_key' => 'jump-id',
							'p1.pr_value' => $eId,
							'p2.pr_key' => 'main-wiki',
							'p2.pr_value' => wfWikiID(),
						)
					);
					if ( !$rId ) {
						$rId = self::insertEntity( $dbw, 'election' );
					}

					// Insert it! We don't have to care about questions or options here.
					$dbw->replace( 'securepoll_elections',
						array( 'el_entity' ),
						array(
							'el_entity' => $rId,
							'el_title' => $election->title,
							'el_ballot' => $election->ballotType,
							'el_tally' => $election->tallyType,
							'el_primary_lang' => $election->getLanguage(),
							'el_start_date' => $dbw->timestamp( $election->getStartDate() ),
							'el_end_date' => $dbw->timestamp( $election->getEndDate() ),
							'el_auth_type' => $election->authType,
						),
						__METHOD__
					);
					self::savePropertiesAndMessages( $dbw, $rId, $election );

					// Fix jump-id
					$dbw->update( 'securepoll_properties',
						array( 'pr_value' => $eId ),
						array( 'pr_entity' => $rId, 'pr_key' => 'jump-id' ),
						__METHOD__
					);
					$dbw->commit();
				} catch ( Exception $ex ) {
					$dbw->rollback();
					MWExceptionHandler::logException( $ex );
				}
				$lb->reuseConnection( $dbw );
			}
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $eId );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection( $election );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $formData['comment'] );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election, 'msg/' . $election->getLanguage() );
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

		$ballot = $data['ballot'];
		$tally = $data['tally'];
		$crypt = isset( $p['encrypt-type'] ) ? $p['encrypt-type'] : 'none';

		$formData = array(
			'election_id' => $data['id'],
			'election_title' => $data['title'],
			'property_wiki' => isset( $p['wikis-val'] ) ? $p['wikis-val'] : null,
			'election_primaryLang' => $data['lang'],
			'election_dates' => array(
				$startDate->format( 'Y-m-d' ),
				$endDate->diff( $startDate )->format( '%a' ),
			),
			'return-url' => isset( $p['return-url'] ) ? $p['return-url'] : null,
			'jump-text' => isset( $m['jump-text'] ) ? $m['jump-text'] : null,
			'election_type' => "{$ballot}+{$tally}",
			'election_crypt' => $crypt,
			'disallow-change' => (bool)isset( $p['disallow-change'] ) ? $p['disallow-change'] : null,
			'property_admins' => array(),
			'questions' => array(),
			'comment' => '',
		);

		if ( isset( $data['properties']['admins'] ) ) {
			foreach ( explode( '|', $data['properties']['admins'] ) as $admin ) {
				$formData['property_admins'][] = array( 'username' => $admin );
			}
		}

		$classes = array();
		$tallyTypes = array();
		foreach ( SecurePoll_Ballot::$ballotTypes as $class ) {
			$classes[] = $class;
			foreach ( call_user_func_array( array( $class, 'getTallyTypes' ), array() ) as $type ) {
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
			$q = array(
				'text' => $question['messages']['text'],
			);
			if ( isset( $question['id'] ) ) {
				$q['id'] = $question['id'];
			}

			foreach ( $classes as $class ) {
				self::unprocessFormData( $q, $question, $class, 'question' );
			}

			// Process options for this question
			foreach ( $question['options'] as $option ) {
				$o = array(
					'text' => $option['messages']['text'],
				);
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
	 * @param string $type Entity type
	 * @return int
	 */
	private static function insertEntity( $dbw, $type ) {
		$id = $dbw->nextSequenceValue( 'securepoll_en_id_seq' );
		$dbw->insert( 'securepoll_entity',
			array(
				'en_id' => $id,
				'en_type' => $type,
			),
			__METHOD__
		);
		$id = $dbw->insertId();
		return $id;
	}

	/**
	 * Save properties and messages for an entity
	 *
	 * @param IDatabase $dbw
	 * @param int $id
	 * @param SecurePoll_Entity $entity
	 * @return array
	 */
	private static function savePropertiesAndMessages( $dbw, $id, $entity ) {
		$properties = array();
		foreach ( $entity->getAllProperties() as $key => $value ) {
			$properties[] = array(
				'pr_entity' => $id,
				'pr_key' => $key,
				'pr_value' => $value,
			);
		}
		$dbw->replace( 'securepoll_properties', array( 'pr_entity', 'pr_key' ), $properties, __METHOD__ );

		$messages = array();
		$langs = $entity->getLangList();
		foreach ( $entity->getMessageNames() as $name ) {
			foreach ( $langs as $lang ) {
				$value = $entity->getRawMessage( $name, $lang );
				if ( $value !== false ) {
					$messages[] = array(
						'msg_entity' => $id,
						'msg_lang' => $lang,
						'msg_key' => $name,
						'msg_text' => $value,
					);
				}
			}
		}
		$dbw->replace( 'securepoll_msgs', array( 'msg_entity', 'msg_lang', 'msg_key' ), $messages, __METHOD__ );
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

		$items = call_user_func_array( array( $class, 'getCreateDescriptors' ), array() );

		if ( !is_array( $types ) ) {
			$types = array( $types );
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
					$item['hide-if'] = array( 'OR', array( 'AND' ) );
				} else {
					$item['hide-if'] = array( 'OR', array( 'AND' ),
						$item['hide-if']
					);
				}
				$outItems[$key] = $item;
			} else {
				// @todo Detect if this is really the same descriptor?
			}
			foreach ( $types as $type ) {
				$outItems[$key]['hide-if'][1][] = array( '!==', $field, $type );
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

		$items = call_user_func_array( array( $class, 'getCreateDescriptors' ), array() );

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
					$formData[$key] = array();
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
					$formData[$key] = array();
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
	 * @param HTMLForm $parent Containing HTMLForm
	 * @return bool|string true on success, string on error
	 */
	public function checkUsername( $value, $alldata, HTMLForm $parent ) {
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

/**
 * SecurePoll_Store for loading the form data.
 */
class SecurePoll_FormStore extends SecurePoll_MemoryStore {
	public $eId, $rId = 0;
	public $qIds = array(), $oIds = array();
	public $remoteWikis;

	private $lang;

	public function __construct( $formData ) {
		global $wgSecurePollCreateWikiGroupDir, $wgSecurePollCreateWikiGroups,
			$wgSecurePollCreateRemoteScriptPath;

		$curId = 0;

		$wikis = isset( $formData['property_wiki'] ) ? $formData['property_wiki'] : wfWikiID();
		if ( $wikis === '*' ) {
			$wikis = array_values( self::getWikiList() );
		} elseif ( substr( $wikis, 0, 1 ) === '@' ) {
			$file = substr( $wikis, 1 );
			$wikis = false;

			// HTMLForm already checked this, but let's do it again anyway.
			if ( isset( $wgSecurePollCreateWikiGroups[$file] ) ) {
				$wikis = file_get_contents(
					$wgSecurePollCreateWikiGroupDir . $file . '.dblist'
				);
			}

			if ( !$wikis ) {
				throw new SecurePoll_StatusException( 'securepoll-create-fail-bad-dblist' );
			}
			$wikis = array_map( 'trim', explode( "\n", trim( $wikis ) ) );
		} else {
			$wikis = (array)$wikis;
		}

		$this->remoteWikis = array_diff( $wikis, array( wfWikiID() ) );

		// Create the entry for the election
		list( $ballot,$tally ) = explode( '+', $formData['election_type'] );
		$crypt = $formData['election_crypt'];

		$date = new DateTime(
			"{$formData['election_dates'][0]}T00:00:00Z",
			new DateTimeZone( 'GMT' )
		);
		$startDate = $date->format( 'YmdHis' );
		$date->add( new DateInterval( "P{$formData['election_dates'][1]}D" ) );
		$endDate = $date->format( 'YmdHis' );

		$this->lang = $formData['election_primaryLang'];

		$eId = (int)$formData['election_id'] <= 0 ? --$curId : (int)$formData['election_id'];
		$this->eId = $eId;
		$this->entityInfo[$eId] = array(
			'id' => $eId,
			'type' => 'election',
			'title' => $formData['election_title'],
			'ballot' => $ballot,
			'tally' => $tally,
			'primaryLang' => $this->lang,
			'startDate' => wfTimestamp( TS_MW, $startDate ),
			'endDate' => wfTimestamp( TS_MW, $endDate ),
			'auth' => $this->remoteWikis ? 'remote-mw' : 'local',
			'questions' => array(),
		);
		$this->properties[$eId] = array(
			'encrypt-type' => $crypt,
			'wikis' => join( "\n", $wikis ),
			'wikis-val' => isset( $formData['property_wiki'] ) ? $formData['property_wiki'] : wfWikiID(),
			'return-url' => $formData['return-url'],
			'disallow-change' => $formData['disallow-change'] ? 1 : 0,
		);
		$this->messages[$this->lang][$eId] = array(
			'title' => $formData['election_title'],
			'jump-text' => $formData['jump-text'],
		);

		$admins = $this->getAdminsList( $formData['property_admins'] );
		$this->properties[$eId]['admins'] = $admins;

		if ( $this->remoteWikis ) {
			$this->properties[$eId]['remote-mw-script-path'] = $wgSecurePollCreateRemoteScriptPath;

			$this->rId = $rId = --$curId;
			$this->entityInfo[$rId] = array(
				'id' => $rId,
				'type' => 'election',
				'title' => $formData['election_title'],
				'ballot' => $ballot,
				'tally' => $tally,
				'primaryLang' => $this->lang,
				'startDate' => wfTimestamp( TS_MW, $startDate ),
				'endDate' => wfTimestamp( TS_MW, $endDate ),
				'auth' => 'local',
				'questions' => array(),
			);
			$this->properties[$rId]['main-wiki'] = wfWikiID();
			$this->properties[$rId]['jump-url'] = SpecialPage::getTitleFor( 'SecurePoll' )->getFullUrl();
			$this->properties[$rId]['jump-id'] = $eId;
			$this->properties[$rId]['admins'] = $admins;
			$this->messages[$this->lang][$rId] = array(
				'title' => $formData['election_title'],
				'jump-text' => $formData['jump-text'],
			);
		}

		$this->processFormData( $eId, $formData, SecurePoll_Ballot::$ballotTypes[$ballot], 'election' );
		$this->processFormData( $eId, $formData, SecurePoll_Tallier::$tallierTypes[$tally], 'election' );
		$this->processFormData( $eId, $formData, SecurePoll_Crypt::$cryptTypes[$crypt], 'election' );

		// Process each question
		foreach ( $formData['questions'] as $question ) {
			if ( (int)$question['id'] <= 0 ) {
				$qId = --$curId;
			} else {
				$qId = (int)$question['id'];
				$this->qIds[] = $qId;
			}
			$this->entityInfo[$qId] = array(
				'id' => $qId,
				'type' => 'question',
				'election' => $eId,
				'options' => array(),
			);
			$this->properties[$qId] = array();
			$this->messages[$this->lang][$qId] = array(
				'text' => $question['text'],
			);

			$this->processFormData( $qId, $question, SecurePoll_Ballot::$ballotTypes[$ballot], 'question' );
			$this->processFormData( $qId, $question, SecurePoll_Tallier::$tallierTypes[$tally], 'question' );
			$this->processFormData( $qId, $question, SecurePoll_Crypt::$cryptTypes[$crypt], 'question' );

			// Process options for this question
			foreach ( $question['options'] as $option ) {
				if ( (int)$option['id'] <= 0 ) {
					$oId = --$curId;
				} else {
					$oId = (int)$option['id'];
					$this->oIds[] = $oId;
				}
				$this->entityInfo[$oId] = array(
					'id' => $oId,
					'type' => 'option',
					'election' => $eId,
					'question' => $qId,
				);
				$this->properties[$oId] = array();
				$this->messages[$this->lang][$oId] = array(
					'text' => $option['text'],
				);

				$this->processFormData( $oId, $option, SecurePoll_Ballot::$ballotTypes[$ballot], 'option' );
				$this->processFormData( $oId, $option, SecurePoll_Tallier::$tallierTypes[$tally], 'option' );
				$this->processFormData( $oId, $option, SecurePoll_Crypt::$cryptTypes[$crypt], 'option' );

				$this->entityInfo[$qId]['options'][] = &$this->entityInfo[$oId];
			}

			$this->entityInfo[$eId]['questions'][] = &$this->entityInfo[$qId];
		}
	}

	/**
	 * Extract the values for the class's properties and messages
	 *
	 * @param int $id
	 * @param array $formData Form data array
	 * @param string|false $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 */
	private function processFormData( $id, $formData, $class, $category ) {
		if ( $class === false ) {
			return;
		}

		$items = call_user_func_array( array( $class, 'getCreateDescriptors' ), array() );

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
			$value = $formData[$key];
			switch ( $item['SecurePoll_type'] ) {
				case 'property':
					$this->properties[$id][$key] = $value;
					break;
				case 'properties':
					foreach ( $value as $k => $v ) {
						$this->properties[$id][$k] = $v;
					}
					break;
				case 'message':
					$this->messages[$this->lang][$id][$key] = $value;
					break;
				case 'messages':
					foreach ( $value as $k => $v ) {
						$this->messages[$this->lang][$id][$k] = $v;
					}
					break;
			}
		}
	}

	/**
	 * Get the name of a wiki
	 *
	 * @param string $dbname
	 * @return string
	 */
	public static function getWikiName( $dbname ) {
		$name = WikiMap::getWikiName( $dbname );
		return $name ?: $dbname;
	}

	/**
	 * Get the list of wiki names
	 *
	 * @return array
	 */
	public static function getWikiList() {
		global $wgConf;

		$wikiNames = array();
		foreach ( $wgConf->getLocalDatabases() as $dbname ) {
			$host = self::getWikiName( $dbname );
			if ( strpos( $host, '.' ) ) {
				// e.g. "en.wikipedia.org"
				$wikiNames[$host] = $dbname;
			}
		}

		// Make sure the local wiki is represented
		$dbname = wfWikiID();
		$wikiNames[self::getWikiName( $dbname )] = $dbname;

		ksort( $wikiNames );

		return $wikiNames;
	}

	/**
	 * Convert the submitted array of admin usernames into a string for
	 * insertion into the database.
	 *
	 * @param array $data
	 * @return string
	 */
	private function getAdminsList( $data ) {
		$admins = array();
		foreach ( $data as $admin ) {
			$admins[] = User::getCanonicalName( $admin['username'] );
		}
		return join( '|', $admins );
	}
}

class SecurePoll_StatusException extends Exception {
	public $status;

	function __construct( $message /* ... */ ) {
		$args = func_get_args();
		$this->status = call_user_func_array( 'Status::newFatal', $args );
	}
}
