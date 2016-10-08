<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../../../..';
}
require_once( "$IP/maintenance/commandLine.inc" );

$wgDebugLogFile = '/dev/stderr';

$dbr = wfGetDB( DB_SLAVE );
$dbr->setFlag( DBO_DEBUG );

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
	$page = WikiPage::newFromID( $row->page_id );

	print "Got article " . $row->page_id . "\n";

	$text = ContentHandler::getContentText( $page->getContent() );

	$len = strlen( $textPrefix );
	if ( substr( $text, 0, $len ) == $textPrefix ) {
		$text = substr( $text, $len );
	}

	if ( substr( $text, - $len ) == $textSuffix ) {
		$text = substr( $text, 0, - $len );
	}

	$text = trim( $text ) . "\n";

	$lang = substr( $page->getTitle()->getText(), strlen( $prefix ) );
	$file = "/a/common/elections-2013-spam/email-translations/$lang";
	file_put_contents( $file, $text );
}
