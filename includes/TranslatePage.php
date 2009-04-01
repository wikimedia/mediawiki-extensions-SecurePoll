<?php

class SecurePoll_TranslatePage extends SecurePoll_Page {
	function execute( $params ) {
		global $wgOut, $wgUser, $wgLang, $wgRequest;

		if ( !count( $params ) ) {
			$wgOut->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}
		
		$electionId = intval( $params[0] );
		$this->election = $this->parent->getElection( $electionId );
		if ( !$this->election ) {
			$wgOut->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}
		$this->initLanguage( $wgUser, $this->election );
		$wgOut->setPageTitle( wfMsg( 'securepoll-translate-title', 
			$this->election->getMessage( 'title' ) ) );

		$this->isAdmin = $this->election->isAdmin( $wgUser );

		$primary = $this->election->getLanguage();
		$secondary = $wgRequest->getVal( 'secondary_lang' );
		if ( $secondary !== null ) {
			$wgOut->redirect( $this->getTitle( $secondary )->getFullUrl() );
			return;
		}

		if ( isset( $params[1] ) ) {
			$secondary = $params[1];
		} else {
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

		if ( $wgRequest->wasPosted() && $wgRequest->getVal( 'action' ) == 'submit' ) {
			$this->doSubmit( $secondary );
			return;
		}

		$action = $this->getTitle( $secondary )->getLocalUrl( 'action=submit' );
		$s = 
			Xml::openElement( 'form', array( 'method' => 'post', 'action' => $action ) ) .
			'<table class="TablePager securepoll-trans-table">' .
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
				$s .= '<tr><td>' . htmlspecialchars( "$entityName/$messageName" ) . "</td>\n" .
					'<td>' . nl2br( htmlspecialchars( $primaryText ) ) . '</td>' .
					'<td>' . 
					Xml::textarea( $controlName, $secondaryText, 40, 3, 
						array( 'class' => 'securepoll-translate-box' ) ) .
					"</td></tr>\n";
			}
		}
		$s .= '</table>' . 
			'<p style="text-align: center;">' . 
			Xml::submitButton( wfMsg( 'securepoll-submit-translate' ) ) .
			"</p></form>\n";
		$wgOut->addHTML( $s );
	}

	function getTitle( $lang ) {
		return $this->parent->getTitle( 'translate/' . 
			$this->election->getId() . '/' .
			$lang
		);
	}

	function showLanguageSelector( $selectedCode ) {
		$s = 
			Xml::openElement( 'form', 
				array( 
					'action' => $this->getTitle( '' )->getLocalUrl()
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

	function doSubmit( $secondary ) {
		global $wgRequest, $wgOut;
		$entities = array_merge( array( $this->election ), $this->election->getDescendants() );
		$replaceBatch = array();
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
				}
			}
		}
		if ( $replaceBatch ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->replace( 
				'securepoll_msgs', 
				array( array( 'msg_entity', 'msg_lang', 'msg_key' ) ),
				$replaceBatch,
				__METHOD__
			);
		}
		$wgOut->redirect( $this->getTitle( $secondary )->getFullUrl() );
	}
}
