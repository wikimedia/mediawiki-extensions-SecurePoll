<?php

/**
 * A SecurePoll subpage for translating election messages.
 */
class SecurePoll_TranslatePage extends SecurePoll_Page {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		global $wgOut, $wgUser, $wgLang, $wgRequest, $wgSecurePollUseNamespace;

		if ( !count( $params ) ) {
			$wgOut->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$wgOut->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}
		$this->initLanguage( $wgUser, $this->election );
		$wgOut->setPageTitle( wfMsg( 'securepoll-translate-title',
			$this->election->getMessage( 'title' ) ) );

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

			$wgOut->addWikiMsg( 'securepoll-edit-redirect',
				Message::rawParam( Linker::makeExternalLink( $jumpUrl, $wiki ) )
			);

			return;
		}

		$this->isAdmin = $this->election->isAdmin( $wgUser );

		$primary = $this->election->getLanguage();
		$secondary = $wgRequest->getVal( 'secondary_lang' );
		if ( $secondary !== null ) {
			# Language selector submitted: redirect to the subpage
			$wgOut->redirect( $this->getTitle( $secondary )->getFullUrl() );
			return;
		}

		if ( isset( $params[1] ) ) {
			$secondary = $params[1];
		} else {
			# No language selected, show the selector
			$this->showLanguageSelector( $primary );
			return;
		}

		$secondary = $params[1];
		$primaryName = $wgLang->getLanguageName( $primary );
		$secondaryName = $wgLang->getLanguageName( $secondary );
		if ( strval( $secondaryName ) === '' ) {
			$wgOut->addWikiMsg( 'securepoll-invalid-language', $secondary );
			$this->showLanguageSelector( $primary );
			return;
		}

		# Set a subtitle to return to the language selector
		$this->parent->setSubtitle( array(
			$this->getTitle(),
			wfMsg( 'securepoll-translate-title', $this->election->getMessage( 'title' ) ) ) );

		# If the request was posted, do the submit
		if ( $wgRequest->wasPosted() && $wgRequest->getVal( 'action' ) == 'submit' ) {
			$this->doSubmit( $secondary );
			return;
		}

		# Show the form
		$action = $this->getTitle( $secondary )->getLocalUrl( 'action=submit' );
		$s =
			Xml::openElement( 'form', array( 'method' => 'post', 'action' => $action ) ) .
			'<table class="mw-datatable TablePager securepoll-trans-table">' .
			'<col class="securepoll-col-trans-id" width="1*"/>' .
			'<col class="securepoll-col-primary" width="30%"/>' .
			'<col class="securepoll-col-secondary"/>' .
			'<tr><th>' . wfMsgHtml( 'securepoll-header-trans-id' ) . '</th>' .
			'<th>' . htmlspecialchars( $primaryName ) . '</th>' .
			'<th>' . htmlspecialchars( $secondaryName ) . '</th></tr>';
		$entities = array_merge( array( $this->election ), $this->election->getDescendants() );
		foreach ( $entities as $entity ) {
			$entityName = $entity->getType() . '(' . $entity->getId() . ')';
			foreach ( $entity->getMessageNames() as $messageName ) {
				$controlName = 'trans_' . $entity->getId() . '_' . $messageName;
				$primaryText = $entity->getRawMessage( $messageName, $primary );
				$secondaryText = $entity->getRawMessage( $messageName, $secondary );
				$attribs = array( 'class' => 'securepoll-translate-box' );
				if ( !$this->isAdmin ) {
					$attribs['readonly'] = '1';
				}
				$s .= '<tr><td>' . htmlspecialchars( "$entityName/$messageName" ) . "</td>\n" .
					'<td>' . nl2br( htmlspecialchars( $primaryText ) ) . '</td>' .
					'<td>' .
					Xml::textarea( $controlName, $secondaryText, 40, 3, $attribs ) .
					"</td></tr>\n";
			}
		}
		$s .= '</table>';
		if ( $this->isAdmin ) {
			if ( $wgSecurePollUseNamespace ) {
				$s .=
					'<p style="text-align: center;">' .
					wfMsgHtml( 'securepoll-translate-label-comment' ) .
					Xml::input( 'comment' ) .
					"</p>";
			}

			$s .=
			'<p style="text-align: center;">' .
			Xml::submitButton( wfMsg( 'securepoll-submit-translate' ) ) .
			"</p>";
		}
		$s .= "</form>\n";
		$wgOut->addHTML( $s );
	}

	/**
	 * @return Title
	 */
	function getTitle( $lang = false ) {
		$subpage = 'translate/' . $this->election->getId();
		if ( $lang !== false ) {
			$subpage .= '/' . $lang;
		}
		return $this->parent->getTitle( $subpage );
	}

	/**
	 * Show a language selector to allow the user to choose the language to
	 * translate.
	 */
	function showLanguageSelector( $selectedCode ) {
		$s =
			Xml::openElement( 'form',
				array(
					'action' => $this->getTitle( false )->getLocalUrl()
				)
			) .
			Xml::openElement(
				'select',
				array( 'id' => 'secondary_lang', 'name' => 'secondary_lang' )
			) . "\n";

		$languages = Language::getLanguageNames();
		ksort( $languages );
		foreach ( $languages as $code => $name ) {
			$s .= "\n" . Xml::option( "$code - $name", $code, $code == $selectedCode );
		}
		$s .= "\n</select>\n" .
			'<p>' . Xml::submitButton( wfMsg( 'securepoll-submit-select-lang' ) ) . '</p>' .
			"</form>\n";
		global $wgOut;
		$wgOut->addHTML( $s );
	}

	/**
	 * Submit message text changes.
	 */
	function doSubmit( $secondary ) {
		global $wgRequest, $wgOut, $wgSecurePollUseNamespace;

		if ( !$this->isAdmin ) {
			$wgOut->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		$entities = array_merge( array( $this->election ), $this->election->getDescendants() );
		$replaceBatch = array();
		$jumpReplaceBatch = array();
		foreach ( $entities as $entity ) {
			foreach ( $entity->getMessageNames() as $messageName ) {
				$controlName = 'trans_' . $entity->getId() . '_' . $messageName;
				$value = $wgRequest->getText( $controlName );
				if ( $value !== '' ) {
					$replaceBatch[] = array(
						'msg_entity' => $entity->getId(),
						'msg_lang' => $secondary,
						'msg_key' => $messageName,
						'msg_text' => $value
					);

					// Jump wikis don't have subentities
					if ( $entity === $this->election ) {
						$jumpReplaceBatch[] = array(
							'msg_entity' => $entity->getId(),
							'msg_lang' => $secondary,
							'msg_key' => $messageName,
							'msg_text' => $value
						);
					}
				}
			}
		}
		if ( $replaceBatch ) {
			$wikis = $this->election->getProperty( 'wikis' );
			if ( $wikis ) {
				$wikis = explode( "\n", $wikis );
			} else {
				$wikis = array();
			}

			// First, the main wiki
			$dbw = $this->context->getDB();
			$dbw->replace(
				'securepoll_msgs',
				array( array( 'msg_entity', 'msg_lang', 'msg_key' ) ),
				$replaceBatch,
				__METHOD__
			);

			if ( $wgSecurePollUseNamespace ) {
				// Create a new context to bypass caching
				$context = new SecurePoll_Context;
				$election = $context->getElection( $this->election->getId() );

				list( $title, $content ) = SecurePollContentHandler::makeContentFromElection(
					$election, "msg/$secondary" );
				$wp = WikiPage::factory( $title );
				$wp->doEditContent( $content, $wgRequest->getText( 'comment' ) );
			}

			// Then each jump-wiki
			foreach ( $wikis as $dbname ) {
				if ( $dbname === wfWikiID() ) {
					continue;
				}

				$lb = wfGetLB( $dbname );
				$dbw = $lb->getConnection( DB_MASTER, array(), $dbname );
				try {
					$id = $dbw->selectField( 'securepoll_elections', 'el_entity', array(
						'el_title' => $this->election->title
					) );
					if ( $id ) {
						foreach ( $jumpReplaceBatch as &$row ) {
							$row['msg_entity'] = $id;
						}
						unset( $row );

						$dbw->replace(
							'securepoll_msgs',
							array( array( 'msg_entity', 'msg_lang', 'msg_key' ) ),
							$jumpReplaceBatch,
							__METHOD__
						);
					}
				} catch ( Exception $ex ) {
					// Log the exception, but don't abort the updating of the rest of the jump-wikis
					MWExceptionHandler::logException( $ex );
				}
				$lb->reuseConnection( $dbw );
			}
		}
		$wgOut->redirect( $this->getTitle( $secondary )->getFullUrl() );
	}
}
