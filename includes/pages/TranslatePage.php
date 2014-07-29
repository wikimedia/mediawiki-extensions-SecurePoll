<?php

/**
 * A SecurePoll subpage for translating election messages.
 */
class SecurePoll_TranslatePage extends SecurePoll_ActionPage {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	public function execute( $params ) {
		global $wgSecurePollUseNamespace;
		$out = $this->specialPage->getOutput();
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
		$this->initLanguage( $this->specialPage->getUser(), $this->election );
		$out->setPageTitle( $this->msg( 'securepoll-translate-title',
			$this->election->getMessage( 'title' ) )->text() );

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

		$this->isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );

		$primary = $this->election->getLanguage();
		$secondary = $request->getVal( 'secondary_lang' );
		if ( $secondary !== null ) {
			# Language selector submitted: redirect to the subpage
			$out->redirect( $this->getTitle( $secondary )->getFullUrl() );
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
		$primaryName = $this->specialPage->getLanguage()->getLanguageName( $primary );
		$secondaryName = $this->specialPage->getLanguage()->getLanguageName( $secondary );
		if ( strval( $secondaryName ) === '' ) {
			$out->addWikiMsg( 'securepoll-invalid-language', $secondary );
			$this->showLanguageSelector( $primary );
			return;
		}

		# Set a subtitle to return to the language selector
		$this->specialPage->setSubtitle( array(
			$this->getTitle(),
			$this->msg( 'securepoll-translate-title', $this->election->getMessage( 'title' ) )->text()
		) );

		# If the request was posted, do the submit
		if ( $request->wasPosted() && $request->getVal( 'action' ) == 'submit' ) {
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
			'<tr><th>' . $this->msg( 'securepoll-header-trans-id' )->escaped() . '</th>' .
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
					Xml::input( 'comment', 45, false, array( 'maxlength' => 250 ) )  .
					"</p>";
			}

			$s .=
			'<p style="text-align: center;">' .
			Xml::submitButton( $this->msg( 'securepoll-submit-translate' )->text() ) .
			"</p>";
		}
		$s .= "</form>\n";
		$out->addHTML( $s );
	}

	/**
	 * @return Title
	 */
	public function getTitle( $lang = false ) {
		$subpage = 'translate/' . $this->election->getId();
		if ( $lang !== false ) {
			$subpage .= '/' . $lang;
		}
		return $this->specialPage->getTitle( $subpage );
	}

	/**
	 * Show a language selector to allow the user to choose the language to
	 * translate.
	 */
	public function showLanguageSelector( $selectedCode ) {
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
			'<p>' . Xml::submitButton( $this->msg( 'securepoll-submit-select-lang' )->text() ) . '</p>' .
			"</form>\n";
		$this->specialPage->getOutput()->addHTML( $s );
	}

	/**
	 * Submit message text changes.
	 */
	public function doSubmit( $secondary ) {
		global $wgSecurePollUseNamespace;

		$out = $this->specialPage->getOutput();
		$request = $this->specialPage->getRequest();

		if ( !$this->isAdmin ) {
			$out->addWikiMsg( 'securepoll-need-admin' );
			return;
		}

		$entities = array_merge( array( $this->election ), $this->election->getDescendants() );
		$replaceBatch = array();
		$jumpReplaceBatch = array();
		foreach ( $entities as $entity ) {
			foreach ( $entity->getMessageNames() as $messageName ) {
				$controlName = 'trans_' . $entity->getId() . '_' . $messageName;
				$value = $request->getText( $controlName );
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
				$wp->doEditContent( $content, $request->getText( 'comment' ) );
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
		$out->redirect( $this->getTitle( $secondary )->getFullUrl() );
	}
}
