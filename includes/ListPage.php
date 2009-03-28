<?php

class SecurePoll_ListPage extends SecurePoll_Page {

	function displayList() {
		global $wgOut, $wgLang, $wgUser;

		$userRights = $wgUser->getRights();
		$admin = $this->isAdmin();
		$dbr =& $this->getDB();

		$res = $dbr->select( 'securepoll_votes', '*', array(), __METHOD__, array( 'ORDER BY' => 'vote_user_key' ) );
		if ( $dbr->numRows( $res ) == 0 ) {
			$wgOut->addWikiMsg( 'securepoll_novotes' );
			return;
		}
		$thisTitle = SpecialPage::getTitleFor( 'SecurePoll' );
		$sk = $wgUser->getSkin();
		$dumpLink = $sk->makeKnownLinkObj( $thisTitle, wfMsg( 'securepoll_dumplink' ), "action=dump" );

		$intro = wfMsg( 'securepoll_listintro', $dumpLink );
		$hTime = wfMsg( 'securepoll_time' );
		$hUser = wfMsg( 'securepoll_user' );
		$hIp = wfMsg( 'securepoll_ip' );
		$hUa = wfMsg( 'securepoll_ua' );

		$s = "$intro <table border=1><tr><th>
			$hUser
		  </th><th>
			$hTime
		  </th>";

		if ( $admin ) {
			$s .= "<th>
			    $hIp
			  </th><th>
			    $hUa
			  </th><th>&nbsp;</th>";
		}
		$s .= "</tr>";

		while ( $row = $dbr->fetchObject( $res ) ) {
			$user = $row->vote_user_key;
			$time = $wgLang->timeanddate( $row->vote_timestamp );
			$cellOpen = "<td>";
			$cellClose = "</td>";
			if ( !$row->vote_current ) {
				$cellOpen .= "<font color=\"#666666\">";
				$cellClose = "</font>$cellClose";
			}
			if ( $row->vote_strike ) {
				$cellOpen .= "<del>";
				$cellClose = "</del>$cellClose";
			}
			$s .= "<tr>$cellOpen
				  $user
				{$cellClose}{$cellOpen}
				  $time
				{$cellClose}";

			if ( $admin ) {
				if ( $row->vote_strike ) {
					$strikeLink = $sk->makeKnownLinkObj( $thisTitle, wfMsg( 'securepoll_unstrike' ),
					  "action=unstrike&id={$row->vote_id}" );
				} else {
					$strikeLink = $sk->makeKnownLinkObj( $thisTitle, wfMsg( 'securepoll_strike' ),
					  "action=strike&id={$row->vote_id}" );
				}

				$s .= "{$cellOpen}
				  {$row->vote_ip}
				{$cellClose}{$cellOpen}
				  {$row->vote_ua}
				{$cellClose}<td>
				  {$strikeLink}
				</td></tr>";
			} else {
				$s .= "</tr>";
			}
		}
		$s .= "</table>";
		$wgOut->addHTML( $s );
	}

	function strike( $id, $unstrike ) {
		global $wgOut;

		$dbw =& $this->getDB();

		if ( !$this->isAdmin() ) {
			$wgOut->addWikiMsg( 'securepoll_needadmin' );
			return;
		}
		$value = $unstrike ? 0 : 1;
		$dbw->update( 'securepoll_votes', array( 'vote_strike' => $value ), array( 'vote_id' => $id ), __METHOD__ );

		$title = SpecialPage::getTitleFor( 'SecurePoll' );
		$wgOut->redirect( $title->getFullURL( "action=list" ) );
	}
}
