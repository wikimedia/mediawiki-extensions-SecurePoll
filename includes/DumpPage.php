<?php

/**
 * Special:SecurePoll subpage for exporting encrypted election records.
 */
class SecurePoll_DumpPage extends SecurePoll_Page {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		global $wgOut, $wgUser;

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

		$wgOut->setPageTitle( wfMsg( 'securepoll-dump-title', 
			$this->election->getMessage( 'title' ) ) );

		if ( !$this->election->getCrypt() ) {
			$wgOut->addWikiMsg( 'securepoll-dump-no-crypt' );
			return;
		}
		
		if ( !$this->election->isFinished() ) {
			global $wgLang;
			$wgOut->addWikiMsg( 'securepoll-dump-not-finished', 
				$wgLang->date( $this->election->getEndDate() ),
				$wgLang->time( $this->election->getEndDate() ) );
			return;
		}

		if ( !$this->openRandom() ) {
			$wgOut->addWikiMsg( 'securepoll-dump-no-urandom' );
			return;
		}

		$wgOut->disable();
		header( 'Content-Type: text/plain' );
		$filename = urlencode( "SecurePoll-$electionId-" . wfTimestampNow() );
		header( "Content-Disposition: attachment; filename=$filename" );
		$db = wfGetDB( DB_SLAVE );
		$res = $db->select( 
			'securepoll_votes',
			array( 'vote_id', 'vote_record' ),
			array(
				'vote_election' => $electionId,
				'vote_current' => 1,
				'vote_struck' => 0
			),
			__METHOD__
		);

		$order = $this->shuffle( range( 0, $res->numRows() - 1 ) );
		foreach ( $order as $i ) {
			$res->seek( $i );
			echo $res->fetchObject()->vote_record . "\n\n";
		}
		$this->closeRandom();
	}

	/**
	 * Open the /dev/urandom device
	 * @return bool success
	 */
	function openRandom() {
		if ( wfIsWindows() ) {
			return false;
		}
		$this->urandom = fopen( '/dev/urandom', 'rb' );
		if ( !$this->urandom ) {
			return false;
		}
		return true;
	}

	/**
	 * Close the urandom device
	 */
	function closeRandom() {
		fclose( $this->urandom );
	}

	/**
	 * Get a random integer between 0 and ($maxp1 - 1)
	 */
	function random( $maxp1 ) {
		$numBytes = ceil( strlen( base_convert( $maxp1, 10, 16 ) ) / 2 );
		if ( $numBytes == 0 ) {
			return 0;
		}
		$data = fread( $this->urandom, $numBytes );
		if ( strlen( $data ) != $numBytes ) {
			throw new MWException( __METHOD__.': not enough bytes' );
		}
		$x = 0;
		for ( $i = 0; $i < $numBytes; $i++ ) {
			$x *= 256;
			$x += ord( substr( $data, $i, 1 ) );
		}
		return $x % $maxp1;
	}

	/**
	 * Works like shuffle() except more secure. Returns the new array instead 
	 * of modifying it. The algorithm is the Knuth/Durstenfeld kind.
	 */
	function shuffle( $a ) {
		$a = array_values( $a );
		for ( $i = count( $a ) - 1; $i >= 0; $i-- ) {
			$target = $this->random( $i + 1 );
			$tmp = $a[$i];
			$a[$i] = $a[$target];
			$a[$target] = $tmp;
		}
		return $a;
	}
}
