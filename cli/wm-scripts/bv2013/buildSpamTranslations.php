<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../../../..';
}
require_once( "$IP/maintenance/commandLine.inc" );

$wgDebugLogFile = '/dev/stderr';

$dbr = wfGetDB( DB_SLAVE );
$dbr->debug( true );

$prefix = "Wikimedia_Foundation_elections_2013/Voter_e-mail/";
$textPrefix = '<languages />';
$textSuffix = "\n[[Category:Board elections 2013]]";

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
	$file = "/a/common/elections-2013-spam/email-translations/$lang";
	file_put_contents( $file, $content );
}
