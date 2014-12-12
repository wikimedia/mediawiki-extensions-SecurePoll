<?php

/**
 * Special:SecurePoll subpage for managing the voter list for a poll
 */
class SecurePoll_VoterEligibilityPage extends SecurePoll_Page {
	private static $lists = array(
		'voter' => 'need-list',
		'include' => 'include-list',
		'exclude' => 'exclude-list',
	);

	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		$out = $this->parent->getOutput();

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
			$jumpUrl .= "/votereligibility/$jumpId";
			if ( count( $params ) > 1 ) {
				$jumpUrl .= '/' . join( '/', array_slice( $params, 1 ) );
			}

			$wiki = $this->election->getProperty( 'main-wiki' );
			if ( $wiki ) {
				$wiki = WikiMap::getWikiName( $wiki );
			} else {
				$wiki = $this->msg( 'securepoll-votereligibility-redirect-otherwiki' )->text();
			}

			$out->addWikiMsg( 'securepoll-votereligibility-redirect',
				Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
			);

			return;
		}

		if ( count( $params ) >= 3 ) {
			$operation = $params[1];
		} else {
			$operation = 'config';
		}

		switch ( $operation ) {
			case 'edit':
				$this->executeEdit( $params[2] );
				break;
			case 'clear':
				$this->executeClear( $params[2] );
				break;
			default:
				$this->executeConfig();
				break;
		}
	}

	private function saveProperties( $properties, $delete, $comment ) {
		global $wgSecurePollUseNamespace;

		$localWiki = wfWikiID();
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = array( $localWiki );
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				$lb = wfGetLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, array(), $dbname );
			}
			try {
				$dbw->begin();
				$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
					'el_title' => $this->election->title
				) );
				if ( $id ) {
					$ins = array();
					foreach ( $properties as $key => $value ) {
						$ins[] = array(
							'pr_entity' => $id,
							'pr_key' => $key,
							'pr_value' => $value,
						);
					}

					$dbw->delete( 'securepoll_properties',
						array(
							'pr_entity' => $id,
							'pr_key' => array_merge( $delete, array_keys( $properties ) ),
						)
					);

					if ( $ins ) {
						$dbw->insert( 'securepoll_properties', $ins );
					}
				}
				$dbw->commit();
			} catch ( Exception $ex ) {
				$dbw->rollback();
				// If it's for the local wiki, rethrow. Otherwise, just log but
				// still update the jump wikis.
				if ( $dbname === $localWiki ) {
					throw $ex;
				}
				MWExceptionHandler::logException( $ex );
			}
			if ( $dbname !== $localWiki ) {
				$lb->reuseConnection( $dbw );
			}
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection( $election );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $comment );
		}
	}

	private function fetchList( $property, $db = DB_SLAVE ) {
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = array( wfWikiID() );
		}

		$names = array();
		foreach ( $wikis as $dbname ) {
			$dbr = wfGetDB( $db, array(), $dbname );

			$id = $dbr->selectField( 'securepoll_elections', 'el_entity', array(
				'el_title' => $this->election->title
			) );
			if ( !$id ) {
				// WTF?
				continue;
			}
			$list = $dbr->selectField( 'securepoll_properties', 'pr_value', array(
				'pr_entity' => $id,
				'pr_key' => $property,
			) );
			if ( !$list ) {
				continue;
			}

			$res = $dbr->select(
				array( 'securepoll_lists', 'user' ),
				array( 'user_name' ),
				array(
					'li_name' => $list,
					'user_id=li_member',
				)
			);
			foreach ( $res as $row ) {
				$names[] = str_replace( '_', ' ', $row->user_name ) . "@$dbname";
			}
		}
		sort( $names );

		return $names;
	}

	/**
	 * @todo Make this really private when we don't support PHP 5.3 anymore
	 * @private
	 */
	public function saveList( $property, $names, $comment ) {
		global $wgSecurePollUseNamespace;

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = array( wfWikiID() );
		}

		$wikiNames = array( '*' => array() );
		foreach ( explode( "\n", $names ) as $name ) {
			$name = trim( $name );
			$i = strrpos( $name, '@' );
			if ( $i === false ) {
				$wiki = '*';
			} else {
				$wiki = trim( substr( $name, $i+1 ) );
				$name = trim( substr( $name, 0, $i ) );
			}
			if ( $wiki !== '' && $name !== '' ) {
				$wikiNames[$wiki][] = str_replace( ' ', '_', $name );
			}
		}

		$list = "{$this->election->getId()}/list/$property";

		$localWiki = wfWikiID();
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = array( $localWiki );
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				$lb = wfGetLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, array(), $dbname );
			}
			try {
				$dbw->begin();

				$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
					'el_title' => $this->election->title
				) );
				if ( $id ) {
					$dbw->replace( 'securepoll_properties',
						array( 'pr_entity', 'pr_key' ),
						array(
							'pr_entity' => $id,
							'pr_key' => $property,
							'pr_value' => $list,
						)
					);

					if ( isset( $wikiNames[$dbname] ) ) {
						$queryNames = array_merge( $wikiNames['*'], $wikiNames[$dbname] );
					} else {
						$queryNames = $wikiNames['*'];
					}

					$dbw->delete( 'securepoll_lists', array( 'li_name' => $list ) );
					if ( $queryNames ) {
						$dbw->insertSelect(
							'securepoll_lists',
							'user',
							array(
								'li_name' => $dbw->addQuotes( $list ),
								'li_member' => 'user_id'
							),
							array(
								'user_name' => $queryNames,
							)
						);
					}
				}

				$dbw->commit();
			} catch ( Exception $ex ) {
				$dbw->rollback();
				// If it's for the local wiki, rethrow. Otherwise, just log but
				// still update the jump wikis.
				if ( $dbname === $localWiki ) {
					throw $ex;
				}
				MWExceptionHandler::logException( $ex );
			}
			if ( $dbname !== $localWiki ) {
				$lb->reuseConnection( $dbw );
			}
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection( $election );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $comment );

			$json = FormatJson::encode( $this->fetchList( $property, DB_MASTER ),
				false, FormatJson::ALL_OK );
			$title = Title::makeTitle( NS_SECUREPOLL, $list );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent(
				$x=ContentHandler::makeContent( $json, $title, 'SecurePoll' ), $comment
			);
		}
	}

	private function executeConfig() {
		global $wgSecurePollUseNamespace;

		/** @todo These should be migrated to core, once the jquery.ui
		 * objectors write their own date picker. */
		if ( !isset( HTMLForm::$typeMappings['date'] ) || !isset( HTMLForm::$typeMappings['daterange'] ) ) {
			HTMLForm::$typeMappings['date'] = 'SecurePoll_HTMLDateField';
			HTMLForm::$typeMappings['daterange'] = 'SecurePoll_HTMLDateRangeField';
		}

		$this->parent->getOutput()->addModules( 'ext.securepoll.htmlform' );
		$this->parent->getOutput()->addModules( 'ext.securepoll' );

		$out = $this->parent->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-title' ) );

		$formItems = array();

		$formItems['min-edits'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-min_edits',
			'type' => 'int',
			'min' => 0,
			'default' => $this->election->getProperty( 'min-edits', '' ),
		);

		$date = $this->election->getProperty( 'max-registration', '' );
		if ( $date !== '' ) {
			$date = gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $date ) );
		}
		$formItems['max-registration'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-max_registration',
			'type' => 'date',
			'default' => $date,
		);

		$formItems['not-blocked'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_blocked',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-blocked', false ),
		);

		$formItems['not-centrally-blocked'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_centrally_blocked',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-centrally-blocked', false ),
		);

		$formItems['central-block-threshold'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-central_block_threshold',
			'type' => 'int',
			'min' => 1,
			'required' => true,
			'hide-if' => array( '===', 'not-centrally-blocked', '' ),
			'default' => $this->election->getProperty( 'central-block-threshold', '' ),
		);

		$formItems['not-bot'] = array(
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_bot',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-bot', false ),
		);

		$formItems[] = array(
			'section' => 'lists',
			'type' => 'info',
			'rawrow' => true,
			'default' => Html::openElement( 'dl' ),
		);
		foreach ( self::$lists as $list => $property ) {
			$name = $this->msg( "securepoll-votereligibility-list-$list" )->text();
			$formItems[] = array(
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => Html::rawElement( 'dt', array(), $name ) .
					Html::openElement( 'dd' ) . Html::openElement( 'ul' ),
			);

			$use = null;
			if ( $list === 'voter' ) {
				$complete = $this->election->getProperty( 'list_complete-count', 0 );
				$total = $this->election->getProperty( 'list_total-count', 0 );
				if ( $complete !== $total ) {
					$use = $this->msg( 'securepoll-votereligibility-label-processing' )
						->numParams( round( $complete * 100.0 / $total, 1 ) )
						->numParams( $complete, $total );
					$links = array( 'clear' );
				}
			}
			if ( $use === null && $this->election->getProperty( $property ) ) {
				$use = $this->msg( 'securepoll-votereligibility-label-inuse' );
				$links = array( 'edit', 'clear' );
			}
			if ( $use === null ) {
				$use = $this->msg( 'securepoll-votereligibility-label-notinuse' );
				$links = array( 'edit' );
			}

			$formItems[] = array(
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => Html::rawElement( 'li', array(), $use->parse() ),
			);

			$prefix = 'votereligibility/' . $this->election->getId();
			foreach ( $links as $action ) {
				$title = SpecialPage::getTitleFor( 'SecurePoll', "$prefix/$action/$list" );
				$link = Linker::link(
					$title, $this->msg( "securepoll-votereligibility-label-$action" )->parse()
				);
				$formItems[] = array(
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::rawElement( 'li', array(), $link ),
				);
			}

			if ( $list === 'voter' ) {
				$formItems[] = array(
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::openElement( 'li' ),
				);

				$formItems['list_populate'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-populate',
					'type' => 'check',
					'hidelabel' => true,
					'default' => $this->election->getProperty( 'list_populate', false ),
				);

				$formItems['list_edits-before'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before',
					'type' => 'check',
					'default' => $this->election->getProperty( 'list_edits-before', false ),
					'hide-if' => array( '===', 'list_populate', '' ),
				);

				$formItems['list_edits-before-count'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before_count',
					'type' => 'int',
					'min' => 1,
					'required' => true,
					'hide-if' => array( 'OR',
						array( '===', 'list_populate', '' ),
						array( '===', 'list_edits-before', '' ),
					),
					'default' => $this->election->getProperty( 'list_edits-before-count', '' ),
				);

				$date = $this->election->getProperty( 'list_edits-before-date', '' );
				if ( $date !== '' ) {
					$date = gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $date ) );
				}
				$formItems['list_edits-before-date'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before_date',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => array( 'OR',
						array( '===', 'list_populate', '' ),
						array( '===', 'list_edits-before', '' ),
					),
					'default' => $date,
				);

				$formItems['list_edits-between'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_between',
					'type' => 'check',
					'hide-if' => array( '===', 'list_populate', '' ),
					'default' => $this->election->getProperty( 'list_edits-between', false ),
				);

				$formItems['list_edits-between-count'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_between_count',
					'type' => 'int',
					'min' => 1,
					'required' => true,
					'hide-if' => array( 'OR',
						array( '===', 'list_populate', '' ),
						array( '===', 'list_edits-between', '' ),
					),
					'default' => $this->election->getProperty( 'list_edits-between-count', '' ),
				);

				$dates = $this->election->getProperty( 'list_edits-between-dates', '' );
				if ( $dates === '' ) {
					$dates = array();
				} else {
					$dates = explode( '|', $dates );
					$dates = array(
						gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $dates[0] ) ),
						gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $dates[1] ) ),
					);
				}
				$formItems['list_edits-between-dates'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_between_dates',
					'layout-message' => 'securepoll-votereligibility-layout-edits_between_dates',
					'type' => 'daterange',
					'absolute' => true,
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => array( 'OR',
						array( '===', 'list_populate', '' ),
						array( '===', 'list_edits-between', '' ),
					),
					'default' => $dates,
				);

				$groups = $this->election->getProperty( 'list_exclude-groups', array() );
				if ( $groups ) {
					$groups = array_map( function ( $group ) {
						return array( 'group' => $group );
					}, explode( '|', $groups ) );
				}
				$formItems['list_exclude-groups'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-exclude_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => array(
						'group' => array(
							'type' => 'text',
							'required' => true,
						),
					),
					'hide-if' => array( '===', 'list_populate', '' ),
				);

				$groups = $this->election->getProperty( 'list_include-groups', array() );
				if ( $groups ) {
					$groups = array_map( function ( $group ) {
						return array( 'group' => $group );
					}, explode( '|', $groups ) );
				}
				$formItems['list_include-groups'] = array(
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-include_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => array(
						'group' => array(
							'type' => 'text',
							'required' => true,
						),
					),
					'hide-if' => array( '===', 'list_populate', '' ),
				);

				$formItems[] = array(
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::closeElement( 'li' ),
				);
			}

			$formItems[] = array(
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => '</ul></dd>',
			);
		}

		$formItems[] = array(
			'section' => 'lists',
			'type' => 'info',
			'rawrow' => true,
			'default' => Html::closeElement( 'dl' ),
		);

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = array(
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
			);
		}

		$form = new HTMLForm( $formItems, $this->parent->getContext(), 'securepoll-votereligibility' );
		$form->addHeaderText(
			$this->msg( 'securepoll-votereligibility-basic-info' )->parseAsBlock(), 'basic'
		);
		$form->addHeaderText(
			$this->msg( 'securepoll-votereligibility-lists-info' )->parseAsBlock(), 'lists'
		);
		$form->setDisplayFormat( 'div' );
		$form->setSubmitTextMsg( 'securepoll-votereligibility-action' );
		$form->setSubmitCallback( array( $this, 'processConfig' ) );
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitle( $this->msg( 'securepoll-votereligibility-saved' ) );
			$out->addWikiMsg( 'securepoll-votereligibility-saved-text' );
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		}
	}

	public function processConfig( $formData, $form ) {
		static $props = array(
			'min-edits', 'not-blocked', 'not-centrally-blocked',
			'central-block-threshold', 'not-bot', 'list_populate',
			'list_edits-before', 'list_edits-before-count',
			'list_edits-between', 'list_edits-between-count',
		);
		static $dateProps = array(
			'max-registration', 'list_edits-before-date', 'list_edits-between-dates',
		);
		static $listProps = array(
			'list_exclude-groups', 'list_include-groups',
		);

		if ( $formData['list_populate'] &&
			!$formData['list_edits-before'] &&
			!$formData['list_edits-between'] &&
			!$formData['list_exclude-groups'] &&
			!$formData['list_exclude-groups']
		) {
			return Status::newFatal( 'securepoll-votereligibility-fail-nothing-to-process' );
		}

		$properties = array();
		$deleteProperties = array();

		foreach ( $props as $prop ) {
			if ( $formData[$prop] !== '' && $formData[$prop] !== false ) {
				$properties[$prop] = $formData[$prop];
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $dateProps as $prop ) {
			if ( $formData[$prop] !== '' && $formData[$prop] !== array() ) {
				$dates = array_map( function ( $date ) {
					$date = new DateTime( $date, new DateTimeZone( 'GMT' ) );
					return wfTimestamp( TS_MW, $date->format( 'YmdHis' ) );
				}, (array)$formData[$prop] );
				$properties[$prop] = join( '|', $dates );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $listProps as $prop ) {
			if ( $formData[$prop] ) {
				$names = array_map( function ( $entry ) {
					return $entry['group'];
				}, $formData[$prop] );
				sort( $names );
				$properties[$prop] = join( '|', $names );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		$populate = !empty( $properties['list_populate'] );
		if ( $populate ) {
			$properties['need-list'] = 'need-list-' . $this->election->getId();
		}

		$this->saveProperties( $properties, $deleteProperties, $formData['comment'] );

		if ( $populate ) {
			SecurePoll_PopulateVoterListJob::pushJobsForElection( $this->election );
		}

		return Status::newGood();
	}

	private function executeEdit( $which ) {
		global $wgSecurePollUseNamespace;

		$out = $this->parent->getOutput();

		if ( !isset( self::$lists[$which] ) ) {
			$out->addWikiMsg( 'securepoll-votereligibility-invalid-list' );
			return;
		}
		$property = self::$lists[$which];
		$name = $this->msg( "securepoll-votereligibility-list-$which" )->text();

		if ( $which === 'voter' ) {
			$complete = $this->election->getProperty( 'list_complete-count', 0 );
			$total = $this->election->getProperty( 'list_total-count', 0 );
			if ( $complete !== $total ) {
				$out->addWikiMsg( 'securepoll-votereligibility-list-is-processing' );
				return;
			}
		}


		$this->parent->getOutput()->addModules( 'ext.securepoll' );

		$out = $this->parent->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-edit-title', $name ) );

		$formItems = array();

		$formItems['names'] = array(
			'label-message' => 'securepoll-votereligibility-label-names',
			'type' => 'textarea',
			'rows' => 20,
			'default' => join( "\n", $this->fetchList( $property ) ),
		);

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = array(
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
			);
		}

		$form = new HTMLForm( $formItems, $this->parent->getContext(), 'securepoll-votereligibility' );
		$form->addHeaderText(
			$this->msg( 'securepoll-votereligibility-edit-header' )->parseAsBlock()
		);
		$form->setDisplayFormat( 'div' );
		$form->setSubmitTextMsg( 'securepoll-votereligibility-edit-action' );
		$that = $this;
		$form->setSubmitCallback( function ( $formData, $form ) use ( $property, $that ) {
			$that->saveList( $property, $formData['names'], $formData['comment'] );
			return Status::newGood();
		} );
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitle( $this->msg( 'securepoll-votereligibility-saved' ) );
			$out->addWikiMsg( 'securepoll-votereligibility-saved-text' );
			$out->returnToMain( false,
				SpecialPage::getTitleFor( 'SecurePoll', 'votereligibility/' . $this->election->getId() )
			);
		}
	}

	private function executeClear( $which ) {
		global $wgSecurePollUseNamespace;

		$out = $this->parent->getOutput();

		if ( !isset( self::$lists[$which] ) ) {
			$out->addWikiMsg( 'securepoll-votereligibility-invalid-list' );
			return;
		}
		$property = self::$lists[$which];
		$name = $this->msg( "securepoll-votereligibility-list-$which" )->text();

		$out = $this->parent->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-clear-title', $name ) );

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = array( wfWikiID() );
		}

		$localWiki = wfWikiID();
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			$i = array_search( $localWiki, $wikis );
			if ( $i !== false ) {
				unset( $wikis[$i] );
			}
			array_unshift( $wikis, $localWiki );
		} else {
			$wikis = array( $localWiki );
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				$lb = wfGetLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, array(), $dbname );
			}
			try {
				$dbw->begin();

				$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
					'el_title' => $this->election->title
				) );
				if ( $id ) {
					$list = $dbw->selectField( 'securepoll_properties', 'pr_value', array(
						'pr_entity' => $id,
						'pr_key' => $property,
					) );
					if ( $list ) {
						$dbw->delete( 'securepoll_lists', array( 'li_name' => $list ) );
						$dbw->delete( 'securepoll_properties',
							array( 'pr_entity' => $id, 'pr_key' => $property ) );
					}

					if ( $which === 'voter' ) {
						$dbw->delete( 'securepoll_properties', array(
							'pr_entity' => $id,
							'pr_key' => array(
								'list_populate', 'list_job-key',
								'list_total-count', 'list_complete-count',
								'list_job-key',
							),
						) );
					}
				}

				$dbw->commit();
			} catch ( Exception $ex ) {
				$dbw->rollback();
				// If it's for the local wiki, rethrow. Otherwise, just log but
				// still update the jump wikis.
				if ( $dbname === $localWiki ) {
					throw $ex;
				}
				MWExceptionHandler::logException( $ex );
			}
			if ( $dbname !== $localWiki ) {
				$lb->reuseConnection( $dbw );
			}
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection( $election );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content,
				$this->msg( 'securepoll-votereligibility-cleared-comment', $name ) );

			$title = Title::makeTitle( NS_SECUREPOLL, "{$election->getId()}/list/$property" );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent(
				ContentHandler::makeContent( '[]', $title, 'SecurePoll' ),
				$this->msg( 'securepoll-votereligibility-cleared-comment', $name )
			);
		}

		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-cleared' ) );
		$out->addWikiMsg( 'securepoll-votereligibility-cleared-text', $name );
		$out->returnToMain( false,
			SpecialPage::getTitleFor( 'SecurePoll', 'votereligibility/' . $this->election->getId() )
		);
	}
}
