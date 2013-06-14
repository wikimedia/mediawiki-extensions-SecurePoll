<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../../../..';
}
require_once( "$IP/maintenance/commandLine.inc" );

$wgDebugLogFile = '/dev/stderr';

$dbr = wfGetDB( DB_SLAVE );
$dbr->debug( true );
$prefix = "Board_elections/2013/Email/";

$textPrefix = '<div style="{{quote style}}">';
$textSuffix = "</div>\n[[Category:Board elections 2011]]";

$res = $dbr->select(
	'page',
	'page_id',
	array( 'page_namespace' => 0, 'page_title ' . $dbr->buildLike( $prefix, $dbr->anyString() ) ),
	'boardelection-spam-translation'
);

foreach( $res as $row ) {
	$page = Article::newFromID( $row->page_id );

	print "Got article " . $row->page_id . "\n";

	$content = $page->getContent();

	$len = strlen( $textPrefix );
	if ( substr( $content, 0, $len ) == $textPrefix ) {
		$content = substr( $content, $len );
	}

	if ( substr( $content, - $len ) == $textSuffix ) {
		$content = substr( $content, 0, - $len );
	}

	$content = trim( $content ) . "\n";

	$lang = substr( $page->getTitle()->getText(), strlen( $prefix ) );
	$file = dirname( __FILE__ ) . "/email-translations/" . $lang;
	file_put_contents( $file, $content );
}
