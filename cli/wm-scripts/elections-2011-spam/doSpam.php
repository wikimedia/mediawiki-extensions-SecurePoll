<?php

require( '/home/wikipedia/common/wmf-deployment/maintenance/commandLine.inc' );

ini_set( 'display_errors', 1 );

$err = fopen( 'php://stderr', 'a' );

$nomail = file( '/home/andrew/elections-2011-spam/nomail-list-stripped' );
$nomail = array_map( 'trim', $nomail );

$wikis = CentralAuthUser::getWikiList();
#$wikis = array( 'frwiki', 'dewiki', 'commonswiki', 'usabilitywiki' );
$wgConf->loadFullData();

$users = array();

$specialWikis = array_map( 'trim', file( '/home/wikipedia/common/special.dblist' ) );

fwrite( $err, "Loading data from database (pass 1)\n" );
foreach( $wikis as $w ) {
	fwrite( $err, "$w...\n" );
	list($site,$siteLang) = $wgConf->siteFromDB( $w );
	$tags = array();
	$pendingChecks = array();
	
	if ( in_array( $w, $specialWikis ) ) {
		$tags[] = 'special';
	}
	
	$defaultLang = $wgConf->get( 'wgLanguageCode', $w, null, array( 'lang' => $siteLang ), $tags );

	$db = wfGetDB( DB_SLAVE, null, $w );

	try {
		$res = $db->select( array( 'securepoll_lists', 'user', 'user_properties' ), '*', array( 'li_name' => 'board-vote-2011' ), __METHOD__, array(),
					array( 'user' => array( 'left join', 'user_id=li_member' ),
						'user_properties' => array( 'left join', array('up_user=li_member', 'up_property' => 'language' ) ) ) );
	
		while( $row = $db->fetchObject( $res ) ) {
			$lang = $row->up_value;
			if (!$lang) { $lang = $defaultLang; }
			$mail = $row->user_email;
			$name = $row->user_name;
			
			if ( !isset($users[$name]) ) {
				$users[$name] = array();
			}
			$users[$name][$w] = array( 'name' => $name, 'mail' => $mail, 'lang' => $lang,
						'editcount' => $row->user_editcount, 'project' => $site,
						'db' => $w, 'id' => $row->user_id );
			$pendingChecks[$row->user_id] = $row->user_name;
		}
		
		if ( count($pendingChecks) > 100 ) {
			runChecks( $w, $pendingChecks );
			
			$pendingChecks = array();
		}
	} catch (MWException $excep) {
		fwrite($err, "Error in query: ".$excep->getMessage()."\n" );
	}
}

fwrite($err, "Pass 2: Checking for users listed twice.\n" );
foreach( $users as $name => $info ) {
	if ( in_array( $name, $nomail ) ) {
		fwrite( $err, "Name $name is on the nomail list, ignoring\n" );
		continue;
	} elseif ( count($info) == 0 ) {
		fwrite( $err, "User $name has been eliminated due to block or bot status\n" );
		continue;
	} elseif (count($info) == 1) {
		extract(reset($info));
		if (!$mail) continue;
		print "$mail\t$lang\t$project\t$name\n";
	} else {
		// Eek, multiple wikis. Grab the best language by looking at the wiki with the most edits.
		$bestEditCount = -1;
		$bestSite = null;
		$mail = null;
		foreach( $info as $site => $wiki ) {
			if ($bestEditCount < $wiki['editcount']) {
				$bestEditCount = $wiki['editcount'];
				$bestSite = $site;
				
				if ($wiki['mail'])
					$mail = $wiki['mail'];
			}
			
			if (!$mail && $wiki['mail'])
				$mail = $wiki['mail'];
		}
		
		if (!$mail) continue;

		$bestWiki = $info[$bestSite];
		print "$mail\t{$bestWiki[lang]}\t{$bestWiki[project]}\t$name\n";
	}
}

fwrite($err, "Done.\n" );

// Checks for ineligibility due to blocks or groups
function runChecks( $wiki, $usersToCheck /* user ID */ ) {
	global $users;
	$dbr = wfGetDB( DB_SLAVE, null, $wiki );
	
	$res = $dbr->select( 'ipblocks', 'ipb_user',
		array( 'ipb_user' => array_keys($usersToCheck), 'ipb_expiry > ' . $dbr->addQuotes( $dbr->timestamp( wfTimestampNow() ) ) ),
		__METHOD__ );
		
	foreach( $res as $row ) {
		$userName = $usersToCheck[$row->ipb_user];
		if ( isset( $users[$userName][$wiki] ) ) {
			unset( $users[$userName][$wiki] );
		}
	}
	
	$res = $dbr->select( 'user_groups', 'ug_user',
		array( 'ug_user' => array_keys($usersToCheck), 'ug_group' => 'bot' ),
		__METHOD__ );
		
	foreach( $res as $row ) {
		$userName = $usersToCheck[$row->ug_user];
		if ( isset( $users[$userName][$wiki] ) ) {
			unset( $users[$userName][$wiki] );
		}
	}
}
