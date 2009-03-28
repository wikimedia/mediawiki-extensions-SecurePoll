<?php

class SecurePoll_DumpPage extends SecurePoll_Page {

	function dump() {
		global $wgOut, $wgLang;
		$dbr =& $this->getDB();

		$res = $dbr->select( 'securepoll_votes', array( 'vote_record' ), array( 'vote_current' => 1, 'vote_strike' => 0 ), __METHOD__ );
		if ( $dbr->numRows( $res ) == 0 ) {
			$wgOut->addWikiMsg( 'securepoll_novotes' );
			return;
		}

		$s = "<pre>";
		while ( $row = $dbr->fetchObject( $res ) ) {
			$s .= $row->vote_record . "\n\n";
		}
		$s .= "</pre>";
		$wgOut->addHTML( $s );
	}
}
