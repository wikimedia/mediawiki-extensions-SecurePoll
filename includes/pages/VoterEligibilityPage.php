<?php

use Wikimedia\Rdbms\DBConnectionError;

/**
 * Special:SecurePoll subpage for managing the voter list for a poll
 */
class SecurePoll_VoterEligibilityPage extends SecurePoll_ActionPage {
	private static $lists = [
		'voter' => 'need-list',
		'include' => 'include-list',
		'exclude' => 'exclude-list',
	];

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

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
			$jumpUrl .= "/votereligibility/$jumpId";
			if ( count( $params ) > 1 ) {
				$jumpUrl .= '/' . implode( '/', array_slice( $params, 1 ) );
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
			$wikis = [ $localWiki ];
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				$lb = wfGetLB( $dbname );
				unset( $dbw ); // trigger DBConnRef destruction and connection reuse
				$dbw = $lb->getConnectionRef( DB_MASTER, [], $dbname );
				try {
					// Connect to the DB and check if the LB is in read-only mode
					if ( $dbw->isReadOnly() ) {
						continue;
					}
				} catch ( DBConnectionError $e ) {
					MWExceptionHandler::logException( $e );
					continue;
				}
			}

			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->selectField(
				'securepoll_elections',
				'el_entity',
				[ 'el_title' => $this->election->title ],
				__METHOD__
			);
			if ( $id ) {
				$ins = [];
				foreach ( $properties as $key => $value ) {
					$ins[] = [
						'pr_entity' => $id,
						'pr_key' => $key,
						'pr_value' => $value,
					];
				}

				$dbw->delete(
					'securepoll_properties',
					[
						'pr_entity' => $id,
						'pr_key' => array_merge( $delete, array_keys( $properties ) ),
					],
					__METHOD__
				);

				if ( $ins ) {
					$dbw->insert( 'securepoll_properties', $ins );
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $comment );
		}
	}

	private function fetchList( $property, $db = DB_REPLICA ) {
		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = [ wfWikiID() ];
		}

		$names = [];
		foreach ( $wikis as $dbname ) {
			$dbr = wfGetDB( $db, [], $dbname );

			$id = $dbr->selectField( 'securepoll_elections', 'el_entity', [
				'el_title' => $this->election->title
			] );
			if ( !$id ) {
				// WTF?
				continue;
			}
			$list = $dbr->selectField( 'securepoll_properties', 'pr_value', [
				'pr_entity' => $id,
				'pr_key' => $property,
			] );
			if ( !$list ) {
				continue;
			}

			$res = $dbr->select(
				[ 'securepoll_lists', 'user' ],
				[ 'user_name' ],
				[
					'li_name' => $list,
					'user_id=li_member',
				]
			);
			foreach ( $res as $row ) {
				$names[] = str_replace( '_', ' ', $row->user_name ) . "@$dbname";
			}
		}
		sort( $names );

		return $names;
	}

	private function saveList( $property, $names, $comment ) {
		global $wgSecurePollUseNamespace;

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = [ wfWikiID() ];
		}

		$wikiNames = [ '*' => [] ];
		foreach ( explode( "\n", $names ) as $name ) {
			$name = trim( $name );
			$i = strrpos( $name, '@' );
			if ( $i === false ) {
				$wiki = '*';
			} else {
				$wiki = trim( substr( $name, $i + 1 ) );
				$name = trim( substr( $name, 0, $i ) );
			}
			if ( $wiki !== '' && $name !== '' ) {
				$wikiNames[$wiki][] = str_replace( '_', ' ', $name );
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
			$wikis = [ $localWiki ];
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				unset( $dbw ); // trigger DBConnRef destruction and connection reuse
				$dbw = wfGetLB( $dbname )->getConnectionRef( DB_MASTER, [], $dbname );
				try {
					// Connect to the DB and check if the LB is in read-only mode
					if ( $dbw->isReadOnly() ) {
						continue;
					}
				} catch ( DBConnectionError $e ) {
					MWExceptionHandler::logException( $e );
					continue;
				}
			}

			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->selectField(
				'securepoll_elections',
				'el_entity',
				[ 'el_title' => $this->election->title ],
				__METHOD__
			);
			if ( $id ) {
				$dbw->replace(
					'securepoll_properties',
					[ 'pr_entity', 'pr_key' ],
					[
						'pr_entity' => $id,
						'pr_key' => $property,
						'pr_value' => $list,
					],
					__METHOD__
				);

				if ( isset( $wikiNames[$dbname] ) ) {
					$queryNames = array_merge( $wikiNames['*'], $wikiNames[$dbname] );
				} else {
					$queryNames = $wikiNames['*'];
				}

				$dbw->delete( 'securepoll_lists', [ 'li_name' => $list ], __METHOD__ );
				if ( $queryNames ) {
					$dbw->insertSelect(
						'securepoll_lists',
						'user',
						[
							'li_name' => $dbw->addQuotes( $list ),
							'li_member' => 'user_id'
						],
						[ 'user_name' => $queryNames ],
						__METHOD__
					);
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election
			);
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( $content, $comment );

			$json = FormatJson::encode( $this->fetchList( $property, DB_MASTER ),
				false, FormatJson::ALL_OK );
			$title = Title::makeTitle( NS_SECUREPOLL, $list );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent(
				$x = ContentHandler::makeContent( $json, $title, 'SecurePoll' ), $comment
			);
		}
	}

	private function executeConfig() {
		global $wgSecurePollUseNamespace;

		$this->specialPage->getOutput()->addModules( 'ext.securepoll' );

		$out = $this->specialPage->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-title' ) );

		$formItems = [];

		$formItems['min-edits'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-min_edits',
			'type' => 'int',
			'min' => 0,
			'default' => $this->election->getProperty( 'min-edits', '' ),
		];

		$date = $this->election->getProperty( 'max-registration', '' );
		if ( $date !== '' ) {
			$date = gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $date ) );
		} else {
			$date = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		}
		$formItems['max-registration'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-max_registration',
			'type' => 'date',
			'default' => $date,
		];

		$formItems['not-blocked'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_blocked',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-blocked', false ),
		];

		$formItems['not-centrally-blocked'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_centrally_blocked',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-centrally-blocked', false ),
		];

		$formItems['central-block-threshold'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-central_block_threshold',
			'type' => 'int',
			'min' => 1,
			'required' => true,
			'hide-if' => [ '===', 'not-centrally-blocked', '' ],
			'default' => $this->election->getProperty( 'central-block-threshold', '' ),
		];

		$formItems['not-bot'] = [
			'section' => 'basic',
			'label-message' => 'securepoll-votereligibility-label-not_bot',
			'type' => 'check',
			'hidelabel' => true,
			'default' => $this->election->getProperty( 'not-bot', false ),
		];

		$formItems[] = [
			'section' => 'lists',
			'type' => 'info',
			'rawrow' => true,
			'default' => Html::openElement( 'dl' ),
		];
		foreach ( self::$lists as $list => $property ) {
			$name = $this->msg( "securepoll-votereligibility-list-$list" )->text();
			$formItems[] = [
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => Html::rawElement( 'dt', [], $name ) .
					Html::openElement( 'dd' ) . Html::openElement( 'ul' ),
			];

			$use = null;
			if ( $list === 'voter' ) {
				$complete = $this->election->getProperty( 'list_complete-count', 0 );
				$total = $this->election->getProperty( 'list_total-count', 0 );
				if ( $complete !== $total ) {
					$use = $this->msg( 'securepoll-votereligibility-label-processing' )
						->numParams( round( $complete * 100.0 / $total, 1 ) )
						->numParams( $complete, $total );
					$links = [ 'clear' ];
				}
			}
			if ( $use === null && $this->election->getProperty( $property ) ) {
				$use = $this->msg( 'securepoll-votereligibility-label-inuse' );
				$links = [ 'edit', 'clear' ];
			}
			if ( $use === null ) {
				$use = $this->msg( 'securepoll-votereligibility-label-notinuse' );
				$links = [ 'edit' ];
			}

			$formItems[] = [
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => Html::rawElement( 'li', [], $use->parse() ),
			];

			$prefix = 'votereligibility/' . $this->election->getId();
			foreach ( $links as $action ) {
				$title = SpecialPage::getTitleFor( 'SecurePoll', "$prefix/$action/$list" );
				$link = Linker::link(
					$title, $this->msg( "securepoll-votereligibility-label-$action" )->parse()
				);
				$formItems[] = [
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::rawElement( 'li', [], $link ),
				];
			}

			if ( $list === 'voter' ) {
				$formItems[] = [
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::openElement( 'li' ),
				];

				$formItems['list_populate'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-populate',
					'type' => 'check',
					'hidelabel' => true,
					'default' => $this->election->getProperty( 'list_populate', false ),
				];

				$formItems['list_edits-before'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before',
					'type' => 'check',
					'default' => $this->election->getProperty( 'list_edits-before', false ),
					'hide-if' => [ '===', 'list_populate', '' ],
				];

				$formItems['list_edits-before-count'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before_count',
					'type' => 'int',
					'min' => 1,
					'required' => true,
					'hide-if' => [ 'OR',
						[ '===', 'list_populate', '' ],
						[ '===', 'list_edits-before', '' ],
					],
					'default' => $this->election->getProperty( 'list_edits-before-count', '' ),
				];

				$date = $this->election->getProperty( 'list_edits-before-date', '' );
				if ( $date !== '' ) {
					$date = gmdate( 'Y-m-d', wfTimestamp( TS_UNIX, $date ) );
				} else {
					$date = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
				}
				$formItems['list_edits-before-date'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_before_date',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => [ 'OR',
						[ '===', 'list_populate', '' ],
						[ '===', 'list_edits-before', '' ],
					],
					'default' => $date,
				];

				$formItems['list_edits-between'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_between',
					'type' => 'check',
					'hide-if' => [ '===', 'list_populate', '' ],
					'default' => $this->election->getProperty( 'list_edits-between', false ),
				];

				$formItems['list_edits-between-count'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_between_count',
					'type' => 'int',
					'min' => 1,
					'required' => true,
					'hide-if' => [ 'OR',
						[ '===', 'list_populate', '' ],
						[ '===', 'list_edits-between', '' ],
					],
					'default' => $this->election->getProperty( 'list_edits-between-count', '' ),
				];

				$editCountStartDate = $this->election->getProperty( 'list_edits-startdate', '' );
				if ( $editCountStartDate !== '' ) {
					$editCountStartDate = gmdate( 'Y-m-d',
						wfTimestamp( TS_UNIX, $editCountStartDate ) );
				}

				$formItems['list_edits-startdate'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_startdate',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => [ 'OR',
						[ '===', 'list_populate', '' ],
						[ '===', 'list_edits-between', '' ],
					],
					'default' => $editCountStartDate,
				];

				$editCountEndDate = $this->election->getProperty( 'list_edits-enddate', '' );
				if ( $editCountEndDate === '' ) {
					$editCountEndDate = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
				} else {
					$editCountEndDate = gmdate( 'Y-m-d',
						wfTimestamp( TS_UNIX, $editCountEndDate ) );
				}

				$formItems['list_edits-enddate'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-edits_enddate',
					'type' => 'date',
					'max' => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
					'required' => true,
					'hide-if' => [ 'OR',
						[ '===', 'list_populate', '' ],
						[ '===', 'list_edits-between', '' ],
					],
					'default' => $editCountEndDate,
				];

				$groups = $this->election->getProperty( 'list_exclude-groups', [] );
				if ( $groups ) {
					$groups = array_map( function ( $group ) {
						return [ 'group' => $group ];
					}, explode( '|', $groups ) );
				}
				$formItems['list_exclude-groups'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-exclude_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => [
						'group' => [
							'type' => 'text',
							'required' => true,
						],
					],
					'hide-if' => [ '===', 'list_populate', '' ],
				];

				$groups = $this->election->getProperty( 'list_include-groups', [] );
				if ( $groups ) {
					$groups = array_map( function ( $group ) {
						return [ 'group' => $group ];
					}, explode( '|', $groups ) );
				}
				$formItems['list_include-groups'] = [
					'section' => 'lists',
					'label-message' => 'securepoll-votereligibility-label-include_groups',
					'type' => 'cloner',
					'format' => 'raw',
					'default' => $groups,
					'fields' => [
						'group' => [
							'type' => 'text',
							'required' => true,
						],
					],
					'hide-if' => [ '===', 'list_populate', '' ],
				];

				$formItems[] = [
					'section' => 'lists',
					'type' => 'info',
					'rawrow' => true,
					'default' => Html::closeElement( 'li' ),
				];
			}

			$formItems[] = [
				'section' => 'lists',
				'type' => 'info',
				'rawrow' => true,
				'default' => '</ul></dd>',
			];
		}

		$formItems[] = [
			'section' => 'lists',
			'type' => 'info',
			'rawrow' => true,
			'default' => Html::closeElement( 'dl' ),
		];

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
				'maxlength' => 250,
			];
		}

		$form = HTMLForm::factory(
			'div', $formItems, $this->specialPage->getContext(), 'securepoll-votereligibility'
		);
		$form->addHeaderText(
			$this->msg( 'securepoll-votereligibility-basic-info' )->parseAsBlock(), 'basic'
		);
		$form->addHeaderText(
			$this->msg( 'securepoll-votereligibility-lists-info' )->parseAsBlock(), 'lists'
		);

		$form->setSubmitTextMsg( 'securepoll-votereligibility-action' );
		$form->setSubmitCallback( [ $this, 'processConfig' ] );
		$result = $form->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitle( $this->msg( 'securepoll-votereligibility-saved' ) );
			$out->addWikiMsg( 'securepoll-votereligibility-saved-text' );
			$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );
		}
	}

	// Based on HTMLDateRangeField::praseDate()
	protected function parseDate( $value ) {
		$value = trim( $value );
		$value .= ' T00:00:00+0000';

		try {
			$date = new DateTime( $value, new DateTimeZone( 'GMT' ) );
			return $date->getTimestamp();
		} catch ( Exception $ex ) {
			return 0;
		}
	}

	public function processConfig( $formData, $form ) {
		static $props = [
			'min-edits', 'not-blocked', 'not-centrally-blocked',
			'central-block-threshold', 'not-bot', 'list_populate',
			'list_edits-before', 'list_edits-before-count',
			'list_edits-between', 'list_edits-between-count',
		];
		static $dateProps = [
			'max-registration', 'list_edits-before-date',
			'list_edits-startdate', 'list_edits-enddate',
		];
		static $listProps = [
			'list_exclude-groups', 'list_include-groups',
		];

		if ( $formData['list_populate'] &&
			!$formData['list_edits-before'] &&
			!$formData['list_edits-between'] &&
			!$formData['list_exclude-groups'] &&
			!$formData['list_exclude-groups']
		) {
			return Status::newFatal( 'securepoll-votereligibility-fail-nothing-to-process' );
		}

		$properties = [];
		$deleteProperties = [];

		foreach ( $props as $prop ) {
			if ( $formData[$prop] !== '' && $formData[$prop] !== false ) {
				$properties[$prop] = $formData[$prop];
			} else {
				$deleteProperties[] = $prop;
			}
		}

		foreach ( $dateProps as $prop ) {
			if ( $formData[$prop] !== '' && $formData[$prop] !== [] ) {
				$dates = array_map( function ( $date ) {
					$date = new DateTime( $date, new DateTimeZone( 'GMT' ) );
					return wfTimestamp( TS_MW, $date->format( 'YmdHis' ) );
				}, (array)$formData[$prop] );
				$properties[$prop] = implode( '|', $dates );
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
				$properties[$prop] = implode( '|', $names );
			} else {
				$deleteProperties[] = $prop;
			}
		}

		$populate = !empty( $properties['list_populate'] );
		if ( $populate ) {
			$properties['need-list'] = 'need-list-' . $this->election->getId();
		}

		if ( $this->parseDate( $formData['list_edits-startdate'] ) >=
			$this->parseDate( $formData['list_edits-enddate'] )
		) {
			return $this->msg( 'securepoll-htmlform-daterange-end-before-start' )->parseAsBlock();
		}

		$comment = isset( $formData['comment'] ) ? $formData['comment'] : '';

		$this->saveProperties( $properties, $deleteProperties, $comment );

		if ( $populate ) {
			// Run pushJobsForElection() in a deferred update to give it outer transaction
			// scope, but keep it presend, so that any errors bubble up to the user.
			DeferredUpdates::addCallableUpdate(
				function () {
					SecurePoll_PopulateVoterListJob::pushJobsForElection( $this->election );
				},
				DeferredUpdates::PRESEND
			);
		}

		return Status::newGood();
	}

	private function executeEdit( $which ) {
		global $wgSecurePollUseNamespace;

		$out = $this->specialPage->getOutput();

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

		$this->specialPage->getOutput()->addModules( 'ext.securepoll' );

		$out = $this->specialPage->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-edit-title', $name ) );

		$formItems = [];

		$formItems['names'] = [
			'label-message' => 'securepoll-votereligibility-label-names',
			'type' => 'textarea',
			'rows' => 20,
			'default' => implode( "\n", $this->fetchList( $property ) ),
		];

		if ( $wgSecurePollUseNamespace ) {
			$formItems['comment'] = [
				'type' => 'text',
				'label-message' => 'securepoll-votereligibility-label-comment',
				'maxlength' => 250,
			];
		}

		$form = new HTMLForm(
			$formItems, $this->specialPage->getContext(), 'securepoll-votereligibility'
		);
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
				SpecialPage::getTitleFor( 'SecurePoll',
					'votereligibility/' . $this->election->getId() )
			);
		}
	}

	private function executeClear( $which ) {
		global $wgSecurePollUseNamespace;

		$out = $this->specialPage->getOutput();

		if ( !isset( self::$lists[$which] ) ) {
			$out->addWikiMsg( 'securepoll-votereligibility-invalid-list' );
			return;
		}
		$property = self::$lists[$which];
		$name = $this->msg( "securepoll-votereligibility-list-$which" )->text();

		$out = $this->specialPage->getOutput();
		$out->setPageTitle( $this->msg( 'securepoll-votereligibility-clear-title', $name ) );

		$wikis = $this->election->getProperty( 'wikis' );
		if ( $wikis ) {
			$wikis = explode( "\n", $wikis );
			if ( !in_array( wfWikiID(), $wikis ) ) {
				$wikis[] = wfWikiID();
			}
		} else {
			$wikis = [ wfWikiID() ];
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
			$wikis = [ $localWiki ];
		}

		foreach ( $wikis as $dbname ) {
			if ( $dbname === $localWiki ) {
				$dbw = $this->context->getDB();
			} else {
				$lb = wfGetLB( $dbname );
				unset( $dbw ); // trigger DBConnRef destruction and connection reuse
				$dbw = $lb->getConnectionRef( DB_MASTER, [], $dbname );
			}

			$dbw->startAtomic( __METHOD__ );

			$id = $dbw->selectField( 'securepoll_elections', 'el_entity', [
				'el_title' => $this->election->title
			] );
			if ( $id ) {
				$list = $dbw->selectField( 'securepoll_properties', 'pr_value', [
					'pr_entity' => $id,
					'pr_key' => $property,
				] );
				if ( $list ) {
					$dbw->delete( 'securepoll_lists', [ 'li_name' => $list ] );
					$dbw->delete( 'securepoll_properties',
						[ 'pr_entity' => $id, 'pr_key' => $property ] );
				}

				if ( $which === 'voter' ) {
					$dbw->delete( 'securepoll_properties', [
						'pr_entity' => $id,
						'pr_key' => [
							'list_populate', 'list_job-key',
							'list_total-count', 'list_complete-count',
							'list_job-key',
						],
					] );
				}
			}

			$dbw->endAtomic( __METHOD__ );
		}

		// Record this election to the SecurePoll namespace, if so configured.
		if ( $wgSecurePollUseNamespace ) {
			// Create a new context to bypass caching
			$context = new SecurePoll_Context;
			$election = $context->getElection( $this->election->getID() );

			list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
				$election
			);
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
