<?php

require_once '/srv/mediawiki/multiversion/MWVersion.php';
require_once getMediaWiki( 'maintenance/commandLine.inc', 'metawiki' );

$wgDebugLogFile = '/dev/stderr';

$dbr = wfGetDB( DB_REPLICA );
$dbr->debug( true );

$prefix = "Wikimedia_Foundation_elections_2015/MassMessages/Voter_e-mail/";
$textPrefix = '<languages />';
$textSuffix = "\n[[Category:Board elections 2015]]";

$res = $dbr->select(
	'page',
	'page_id',
	[ 'page_namespace' => 0, 'page_title ' . $dbr->buildLike( $prefix, $dbr->anyString() ) ],
	'boardelection-spam-translation'
);

foreach ( $res as $row ) {
	$page = Article::newFromID( $row->page_id );

	print "Got article " . $row->page_id . "\n";

	$content = $page->getContent();

	$len = strlen( $textPrefix );
	if ( substr( $content, 0, $len ) == $textPrefix ) {
		$content = substr( $content, $len );
	}

	$len = strlen( $textSuffix );
	if ( substr( $content, -$len ) == $textSuffix ) {
		$content = substr( $content, 0, -$len );
	}

	$content = trim( $content ) . "\n";

	$lang = substr( $page->getTitle()->getText(), strlen( $prefix ) );
	$file = "/srv/elections-2015-spam/email-translations/$lang";
	file_put_contents( $file, $content );
}
