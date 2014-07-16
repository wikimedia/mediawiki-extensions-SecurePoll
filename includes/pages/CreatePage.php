<?php

/**
 * Special:SecurePoll subpage for creating a poll
 */
class SecurePoll_CreatePage extends SecurePoll_Page {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		global $wgUser, $wgSecurePollCreateWikiGroupDir, $wgSecurePollCreateWikiGroups;

		if ( !$wgUser->isAllowed( 'securepoll-create-poll' ) ) {
			throw new PermissionsError( 'securepoll-create-poll' );
		}

		/** @todo These should be migrated to core, once the jquery.ui
		 * objectors write their own date picker. */
		if ( !isset( HTMLForm::$typeMappings['date'] ) || !isset( HTMLForm::$typeMappings['daterange'] ) ) {
			HTMLForm::$typeMappings['date'] = 'SecurePoll_HTMLDateField';
			HTMLForm::$typeMappings['daterange'] = 'SecurePoll_HTMLDateRangeField';
		}

		$this->parent->getOutput()->addModules( 'ext.securepoll.htmlform' );
		$this->parent->getOutput()->addModules( 'ext.securepoll' );

		$out = $this->parent->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-create-title' ) );

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

		$formItems['election_title'] = array(
			'label-message' => 'securepoll-create-label-election_title',
			'type' => 'text',
			'required' => true,
		);

		$wikiNames = $this->getWikiList();
		$options = array();
		$options['securepoll-create-option-wiki-this_wiki'] = wfWikiID();
		if ( count( $wikiNames ) > 1 ) {
			$options['securepoll-create-option-wiki-all_wikis'] = '*';
		}
		foreach ( $wgSecurePollCreateWikiGroups as $file => $msg ) {
			if ( is_readable( "$wgSecurePollCreateWikiGroups$file.dblist" ) ) {
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

		$form = new HTMLForm( $formItems, $this->parent->getContext(), 'securepoll-create' );
		$form->setDisplayFormat( 'div' );
		$form->setSubmitTextMsg( 'securepoll-create-action' );
		$form->setSubmitCallback( array( $this, 'processInput' ) );
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitle( $this->msg( 'securepoll-create-created' ) );
			$out->addWikiMsg( 'securepoll-create-created-text' );
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		}
	}

	public function processInput( $formData, $form ) {
		global $wgSecurePollCreateWikiGroupDir, $wgSecurePollCreateWikiGroups,
			$wgSecurePollCreateRemoteScriptPath;

		$wikis = isset( $formData['property_wiki'] ) ? $formData['property_wiki'] : wfWikiID();
		if ( $wikis === '*' ) {
			$wikis = array_values( $this->getWikiList() );
		} elseif ( substr( $wikis, 0, 1 ) === '@' ) {
			$wikis = false;

			// HTMLForm already checked this, but let's do it again anyway.
			if ( isset( $wgSecurePollCreateWikiGroups[$wikis] ) ) {
				$wikis = file_get_contents(
					$wgSecurePollCreateWikiGroupDir . substr( $wikis, 1 ) . '.dblist'
				);
			}

			if ( !$wikis ) {
				return Status::newFatal( 'securepoll-create-fail-bad-dblist' );
			}
			$wikis = array_map( 'trim', explode( "\n", trim( $wikis ) ) );
		} else {
			$wikis = (array)$wikis;
		}

		$remoteWikis = array_diff( $wikis, array( wfWikiID() ) );

		$dbws = array();
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbws[] = $dbw;
		try {
			$properties = array();
			$messages = array();
			$remoteProperties = array();

			$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
				'el_title' => $formData['election_title']
			) );
			if ( $id ) {
				foreach ( $dbws as $dbw ) {
					$dbw->rollback();
				}
				return Status::newFatal( 'securepoll-create-duplicate-title',
					$this->getWikiName( wfWikiId() ), wfWikiID()
				);
			}

			$lang = $formData['election_primaryLang'];

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

			$eId = self::insertEntity( $dbw, 'election' );
			$dbw->insert( 'securepoll_elections',
				array(
					'el_entity' => $eId,
					'el_title' => $formData['election_title'],
					'el_ballot' => $ballot,
					'el_tally' => $tally,
					'el_primary_lang' => $lang,
					'el_start_date' => $dbw->timestamp( $startDate ),
					'el_end_date' => $dbw->timestamp( $endDate ),
					'el_auth_type' => $remoteWikis ? 'remote-mw' : 'local',
				),
				__METHOD__
			);

			// Process election-level properties and messages for the selected modules
			if ( $remoteWikis ) {
				$properties[] = array(
					'pr_entity' => $eId,
					'pr_key' => 'remote-mw-script-path',
					'pr_value' => $wgSecurePollCreateRemoteScriptPath,
				);
				$remoteProperties['main-wiki'] = wfWikiID();
				$remoteProperties['jump-url'] = SpecialPage::getTitleFor( 'SecurePoll' );
				$remoteProperties['jump-id'] = $eId;
			}
			$properties[] = array(
				'pr_entity' => $eId,
				'pr_key' => 'encrypt-type',
				'pr_value' => $crypt,
			);
			$properties[] = array(
				'pr_entity' => $eId,
				'pr_key' => 'wikis',
				'pr_value' => join( "\n", $wikis ),
			);

			$admins = $this->getAdminsList( $formData['property_admins'] );
			$properties[] = array(
				'pr_entity' => $eId,
				'pr_key' => 'admins',
				'pr_value' => $admins,
			);
			$remoteProperties['admins'] = $admins;

			$messages[] = array(
				'msg_entity' => $eId,
				'msg_lang' => $lang,
				'msg_key' => 'title',
				'msg_text' => $formData['election_title'],
			);

			self::processFormData( $formData, $properties, $messages,
				SecurePoll_Ballot::$ballotTypes[$ballot], 'election', $eId, $lang
			);
			self::processFormData( $formData, $properties, $messages,
				SecurePoll_Tallier::$tallierTypes[$tally], 'election', $eId, $lang
			);
			self::processFormData( $formData, $properties, $messages,
				SecurePoll_Crypt::$cryptTypes[$crypt], 'election', $eId, $lang
			);

			// Process each question
			$qIndex = 0;
			foreach ( $formData['questions'] as $question ) {
				$qId = self::insertEntity( $dbw, 'question' );
				$dbw->insert( 'securepoll_questions',
					array(
						'qu_entity' => $qId,
						'qu_election' => $eId,
						'qu_index' => ++$qIndex,
					),
					__METHOD__
				);

				// Process the properties and messages for this question
				$messages[] = array(
					'msg_entity' => $qId,
					'msg_lang' => $lang,
					'msg_key' => 'text',
					'msg_text' => $question['text'],
				);
				self::processFormData( $question, $properties, $messages,
					SecurePoll_Ballot::$ballotTypes[$ballot], 'question', $qId, $lang
				);
				self::processFormData( $question, $properties, $messages,
					SecurePoll_Tallier::$tallierTypes[$tally], 'question', $qId, $lang
				);
				self::processFormData( $question, $properties, $messages,
					SecurePoll_Crypt::$cryptTypes[$crypt], 'question', $qId, $lang
				);

				// Process options for this question
				foreach ( $question['options'] as $option ) {
					$oId = self::insertEntity( $dbw, 'option' );
					$dbw->insert( 'securepoll_options',
						array(
							'op_entity' => $oId,
							'op_election' => $eId,
							'op_question' => $qId,
						),
						__METHOD__
					);

					// Process the properties and messages for this option
					$messages[] = array(
						'msg_entity' => $oId,
						'msg_lang' => $lang,
						'msg_key' => 'text',
						'msg_text' => $option['text'],
					);
					self::processFormData( $option, $properties, $messages,
						SecurePoll_Ballot::$ballotTypes[$ballot], 'option', $oId, $lang
					);
					self::processFormData( $option, $properties, $messages,
						SecurePoll_Tallier::$tallierTypes[$tally], 'option', $oId, $lang
					);
					self::processFormData( $option, $properties, $messages,
						SecurePoll_Crypt::$cryptTypes[$crypt], 'option', $oId, $lang
					);
				}
			}

			// Insert the properties and messages now
			$dbw->insert( 'securepoll_properties', $properties, __METHOD__ );
			$dbw->insert( 'securepoll_msgs', $messages, __METHOD__ );

			// Create the "redirect" polls on all the local wikis
			foreach ( $remoteWikis as $dbname ) {
				$dbw = wfGetDB( DB_MASTER, array(), $dbname );
				$dbw->begin();
				$dbws[] = $dbw;

				$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
					'el_title' => $formData['election_title']
				) );
				if ( $id ) {
					foreach ( $dbws as $dbw ) {
						$dbw->rollback();
					}
					return Status::newFatal( 'securepoll-create-duplicate-title',
						$this->getWikiName( $dbname ), $dbname
					);
				}

				$rId = self::insertEntity( $dbw, 'election' );
				$dbw->insert( 'securepoll_elections',
					array(
						'el_entity' => $rId,
						'el_title' => $formData['election_title'],
						'el_ballot' => $ballot,
						'el_tally' => $tally,
						'el_primary_lang' => $lang,
						'el_start_date' => $dbw->timestamp( $startDate ),
						'el_end_date' => $dbw->timestamp( $endDate ),
						'el_auth_type' => 'local',
					),
					__METHOD__
				);

				foreach ( $remoteProperties as $key => $value ) {
					$dbw->insert( 'securepoll_properties',
						array(
							'pr_entity' => $rId,
							'pr_key' => $key,
							'pr_value' => $value,
						),
						__METHOD__
					);
				}
			}

			// Now commit all the transactions at once
			foreach ( $dbws as $dbw ) {
				$dbw->commit();
			}

			return Status::newGood( $eId );
		} catch ( Exception $ex ) {
			foreach ( $dbws as $dbw ) {
				$dbw->rollback();
			}
			throw $ex;
		}
	}

	/**
	 * Insert an entry into the securepoll_entities table, and return the ID
	 *
	 * @param string $type Entity type
	 * @return int
	 */
	private static function insertEntity( $dbw, $type ) {
		$id = $dbw->selectField( 'securepoll_entity', 'MAX(en_id)' ) + 1;
		$dbw->insert( 'securepoll_entity',
			array(
				'en_id' => $id,
				'en_type' => $type,
			),
			__METHOD__
		);
		return $id;
	}

	/**
	 * Combine form items for the class into the main array
	 *
	 * @param array &$outItems Array to insert the descriptors into
	 * @param string $field Owning field name, for hide-if
	 * @param string|array $types Type value(s) in the field, for hide-if
	 * @param string $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 */
	private static function processFormItems( &$outItems, $field, $types, $class, $category = null ) {
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
	 * Extract the values for the class's properties and messages
	 *
	 * @param array $formData Form data array
	 * @param array &$properties Array to store properties into
	 * @param array &$messages Array to store messages into
	 * @param string $class Class with the ::getCreateDescriptors static method
	 * @param string|null $category If given, ::getCreateDescriptors is
	 *    expected to return an array with subarrays for different categories
	 *    of descriptors, and this selects which subarray to process.
	 * @param int $id Entity ID the data belongs to
	 * @param string $lang Language for the messages
	 */
	private static function processFormData(
		$formData, &$properties, &$messages, $class, $category, $id, $lang
	) {
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
					$properties[] = array(
						'pr_entity' => $id,
						'pr_key' => $key,
						'pr_value' => $value,
					);
					break;
				case 'properties':
					foreach ( $value as $k => $v ) {
						$properties[] = array(
							'pr_entity' => $id,
							'pr_key' => $k,
							'pr_value' => $v,
						);
					}
					break;
				case 'message':
					$messages[] = array(
						'msg_entity' => $id,
						'msg_lang' => $lang,
						'msg_key' => $key,
						'msg_text' => $value,
					);
					break;
				case 'messages':
					foreach ( $value as $k => $v ) {
						$messages[] = array(
							'msg_entity' => $id,
							'msg_lang' => $lang,
							'msg_key' => $k,
							'msg_text' => $v,
						);
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

	/**
	 * Get the name of a wiki
	 *
	 * @param string $dbname
	 * @return string
	 */
	private function getWikiName( $dbname ) {
		$name = WikiMap::getWikiName( $dbname );
		return $name ?: $dbname;
	}

	/**
	 * Get the list of wiki names
	 *
	 * @return array
	 */
	private function getWikiList() {
		global $wgConf;

		$wikiNames = array();
		foreach ( $wgConf->getLocalDatabases() as $dbname ) {
			$host = $this->getWikiName( $dbname );
			if ( strpos( $host, '.' ) ) {
				// e.g. "en.wikipedia.org"
				$wikiNames[$host] = $dbname;
			}
		}

		// Make sure the local wiki is represented
		$dbname = wfWikiID();
		$wikiNames[$this->getWikiName( $dbname )] = $dbname;

		ksort( $wikiNames );

		return $wikiNames;
	}
}
