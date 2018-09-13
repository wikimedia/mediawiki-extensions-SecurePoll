<?php

/**
 * Special:SecurePoll subpage for exporting encrypted election records.
 */
class SecurePoll_DumpPage extends SecurePoll_ActionPage {
	public $headersSent;

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
		$this->initLanguage( $this->specialPage->getUser(), $this->election );

		$out->setPageTitle( $this->msg( 'securepoll-dump-title',
			$this->election->getMessage( 'title' ) )->text() );

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-dump-not-finished',
				$this->specialPage->getLanguage()->date( $this->election->getEndDate() ),
				$this->specialPage->getLanguage()->time( $this->election->getEndDate() ) );
			return;
		}

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );
		if ( $this->election->getProperty( 'voter-privacy' ) && !$isAdmin ) {
			$out->addWikiMsg( 'securepoll-dump-private' );
			return;
		}

		$this->headersSent = false;
		$status = $this->election->dumpVotesToCallback( [ $this, 'dumpVote' ] );
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
		echo "<vote>" . htmlspecialchars( $row->vote_record ) . "</vote>\n";
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
		$this->context->setLanguages( [ $this->election->getLanguage() ] );
	}
}
