<?php

require( "/home/wikipedia/common/wmf-deployment/maintenance/commandLine.inc" );

$wgDebugLogFile = '/dev/stderr';

$dbr = wfGetDB( DB_SLAVE );
$dbr->debug(true);
$prefix = "Board_elections/2011/Email/";

$textPrefix = '<div style="{{quote style}}">';
$textSuffix = "</div>\n[[Category:Board elections 2011]]";

$res = $dbr->select( 'page', 'page_id', array( 'page_namespace' => 0, 'page_title like '.$dbr->addQuotes( $prefix.'%' ) ), 'boardelection-spam-translation' );

while( $row = $dbr->fetchObject( $res ) ) {
	$page = Article::newFromID( $row->page_id );

	print "Got article ".$row->page_id."\n";

	$content = $page->getContent();
	
	if ( substr( $content, 0, strlen($textPrefix) ) == $textPrefix ) {
		$content = substr( $content, strlen($textPrefix) );
	}
	
	if ( substr( $content, - strlen($textSuffix) ) == $textSuffix ) {
		$content = substr( $content, 0, - strlen($textSuffix) );
	}
	
	$content = trim($content) . "\n";
	
	$lang = substr( $page->getTitle()->getText(), strlen($prefix) );
	$file = dirname(__FILE__)."/email-translations/".$lang;
	file_put_contents( $file, $content );
}
