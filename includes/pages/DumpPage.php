<?php

/**
 * Special:SecurePoll subpage for exporting encrypted election records.
 */
class SecurePoll_DumpPage extends SecurePoll_ActionPage {
	public $headersSent;

	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
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
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$out->setPageTitle( $this->msg( 'securepoll-dump-title',
			$this->election->getMessage( 'title' ) )->text() );

		if ( !$this->election->getCrypt() ) {
			$out->addWikiMsg( 'securepoll-dump-no-crypt' );
			return;
		}

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );
		if ( $this->election->getProperty( 'voter-privacy' ) && !$isAdmin ) {
			$out->addWikiMsg( 'securepoll-dump-private' );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-dump-not-finished',
				$this->specialPage->getLanguage()->date( $this->election->getEndDate() ),
				$this->specialPage->getLanguage()->time( $this->election->getEndDate() ) );
			return;
		}

		$this->headersSent = false;
		$status = $this->election->dumpVotesToCallback( array( $this, 'dumpVote' ) );
		if ( !$status->isOK() && !$this->headersSent ) {
			$out->addWikiText( $status->getWikiText() );
			return;
		}
		if ( !$this->headersSent ) {
			$this->sendHeaders();
		}
		echo "</election>\n</SecurePoll>\n";
	}

	function dumpVote( $election, $row ) {
		if ( !$this->headersSent ) {
			$this->sendHeaders();
		}
		$record = $row->vote_record;
		if ( $this->election->getCrypt() ) {
			$status = $this->election->getCrypt()->decrypt( $record );
			if ( !$status->isOK() ) {
				// Decrypt failed, e.g. invalid or absent private key
				// Still, return the encrypted vote
				echo "<vote>\n<encrypted>" . $record . "</encrypted>\n</vote>\n";
			} else {
				$decrypted_record = $status->value;
				echo "<vote>\n<encrypted>" . $record .
					"</encrypted>\n<decrypted>" . $decrypted_record .
					"</decrypted>\n</vote>\n";
			}
		} else {
			echo "<vote>\n<decrypted>" . $record . "</decrypted>\n</vote>\n";
		}
	}

	public function sendHeaders() {
		$this->headersSent = true;
		$this->specialPage->getOutput()->disable();
		header( 'Content-Type: application/vnd.mediawiki.securepoll' );
		$electionId = $this->election->getId();
		$filename = urlencode( "$electionId-" . wfTimestampNow() . '.securepoll' );
		header( "Content-Disposition: attachment; filename=$filename" );
		echo "<SecurePoll>\n<election>\n" .
			$this->election->getConfXml();
		$this->context->setLanguages( array( $this->election->getLanguage() ) );
	}
}
