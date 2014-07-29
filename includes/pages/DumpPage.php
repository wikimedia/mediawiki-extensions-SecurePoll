<?php

/**
 * Special:SecurePoll subpage for exporting encrypted election records.
 */
class SecurePoll_DumpPage extends SecurePoll_Page {
	public $headersSent;

	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	public function execute( $params ) {

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
		$this->initLanguage( $this->parent->getUser(), $this->election );

		$out->setPageTitle( $this->msg( 'securepoll-dump-title',
			$this->election->getMessage( 'title' ) )->text() );

		if ( !$this->election->getCrypt() ) {
			$out->addWikiMsg( 'securepoll-dump-no-crypt' );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-dump-not-finished',
				$this->parent->getLanguage()->date( $this->election->getEndDate() ),
				$this->parent->getLanguage()->time( $this->election->getEndDate() ) );
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
		echo "<vote>" . $row->vote_record . "</vote>\n";
	}

	public function sendHeaders() {
		$this->headersSent = true;
		$this->parent->getOutput()->disable();
		header( 'Content-Type: application/vnd.mediawiki.securepoll' );
		$electionId = $this->election->getId();
		$filename = urlencode( "$electionId-" . wfTimestampNow() . '.securepoll' );
		header( "Content-Disposition: attachment; filename=$filename" );
		echo "<SecurePoll>\n<election>\n" .
			$this->election->getConfXml();
		$this->context->setLanguages( array( $this->election->getLanguage() ) );
	}
}
