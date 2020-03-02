<?php

require_once '/srv/mediawiki/multiversion/MWMultiVersion.php';
require_once MWMultiVersion::getMediaWiki( 'maintenance/commandLine.inc', 'enwiki' );

$wgConf->loadFullData();

/**
 * A list of usernames that don't want email about elections
 * e.g. copied from https://meta.wikimedia.org/wiki/Wikimedia_nomail_list
 * @var array
 */
$nomail = [];
$raw = file_get_contents(
	'https://meta.wikimedia.org/wiki/Wikimedia_Foundation_nomail_list?action=raw'
);
if ( preg_match( '/(?<=<pre>).*(?=<\/pre>)/ms', $raw, $matches ) ) {
	$nomail = array_filter( array_map( 'trim', explode( "\n", $matches[0] ) ) );
}

/**
 * Name of the list of allowed voters
 * @var string
 */
$listName = 'board-vote-2015a';

/**
 * ID number of the election
 * @var int
 */
$electionId = 512;

$specialWikis = MWWikiversions::readDbListFile( 'special' );

function getDefaultLang( $db ) {
	global $wgConf, $specialWikis;
	static $langs = [];

	if ( empty( $langs[$db] ) ) {
		list( $site, $siteLang ) = $wgConf->siteFromDB( $db );
		$tags = [];
		if ( in_array( $db, $specialWikis ) ) {
			$tags[] = 'special';
		}
		$langs[$db] = RequestContext::sanitizeLangCode(
			$wgConf->get( 'wgLanguageCode', $db, null, [ 'lang' => $siteLang ], $tags ) );
	}

	return $langs[$db];
}

function getLanguage( $userId, $wikiId ) {
	$db = CentralAuthUser::getLocalDB( $wikiId );
	$lang = false;
	try {
		$lang = RequestContext::sanitizeLangCode(
			$db->selectField( 'user_properties', 'up_value',
			[ 'up_user' => $userId, 'up_property' => 'language' ] ) );
	} catch ( Exception $e ) {
		// echo 'Caught exception: ' .  $e->getMessage() . "\n";
	}
	if ( !$lang ) {
		$lang = getDefaultLang( $wikiId );
	}
	return $lang;
}

$voted = [];
$vdb = wfGetDB( DB_REPLICA, [], 'votewiki' );
$voted = $vdb->selectFieldValues( 'securepoll_voters', 'voter_name',
	[ 'voter_election' => $electionId ] );

$db = CentralAuthUser::getCentralSlaveDB();
$res = $db->select(
	[ 'securepoll_lists', 'globaluser' ],
	[
		'gu_id',
		'gu_name',
		'gu_email',
		'gu_home_db',
	],
	[
		'gu_id=li_member',
		'li_name' => $listName,
		'gu_email_authenticated is not null',
		'gu_email is not null',
	]
);

$users = [];
foreach ( $res as $row ) {
	if ( !$row->gu_email ) {
		continue;
	}
	if ( in_array( $row->gu_email, $nomail ) ) {
		// echo "Skipping {$row->gu_email}; in nomail list.\n";
		continue;
	}
	if ( in_array( $row->gu_name, $voted ) ) {
		// echo "Skipping {$row->gu_name}; already voted.\n";
		continue;
	} else {
		$users[] = [
			'id'      => $row->gu_id,
			'mail'    => $row->gu_email,
			'name'    => $row->gu_name,
			'project' => $row->gu_home_db,
		];
	}
}

/**
 * @suppress SecurityCheck-XSS
 * @param string $val
 */
function out( $val ) {
	echo $val;
}

foreach ( $users as $user ) {
	if ( empty( $user['project'] ) ) {
		$caUser = new CentralAuthUser( $user['name'] );
		$user['project'] = $caUser->getHomeWiki();
	}
	$user['lang'] = getLanguage( $user['id'], $user['project'] );
	out( "{$user['mail']}\t{$user['lang']}\t{$user['project']}\t{$user['name']}\n" );
}
