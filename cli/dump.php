<?php

/**
 * Generate an XML dump of an election, including configuration and votes.
 */


$optionsWithArgs = array( 'o' );
require( dirname(__FILE__).'/cli.inc' );

if ( !isset( $args[0] ) ) {
	spFatal( "Usage: php dump.php [-o <outfile>] <election name>" );
}

$election = SecurePoll::getElectionByTitle( $args[0] );
if ( !$election ) {
	spFatal( "There is no election called \"$args[0]\"" );
}

if ( !isset( $options['o'] ) ) {
	$fileName = '-';
} else {
	$fileName = $options['o'];
}
if ( $fileName === '-' ) {
	$outFile = STDIN;
} else {
	$outFile = fopen( $fileName, 'w' );
}
if ( !$outFile ) {
	spFatal( "Unable to open $fileName for writing" );
}

SecurePoll_Entity::setLanguages( array( $election->getLanguage() ) );

$cbdata = array(
	'header' => "<SecurePoll>\n<election>\n" . $election->getConfXml(),
	'outFile' => $outFile
);

# Write vote records
$election->cbdata = $cbdata;
$status = $election->dumpVotesToCallback( 'spDumpVote' );
if ( !$status->isOK() ) {
	spFatal( $status->getWikiText() );
}
if ( $election->cbdata['header'] ) {
	echo $election->cbdata['header'];
}

fwrite( $outFile, "</election>\n</SecurePoll>\n" );

function spFatal( $message ) {
	fwrite( STDERR, rtrim( $message ) . "\n" );
	exit( 1 );
}

function spDumpVote( $election, $row ) {
	if ( $election->cbdata['header'] ) {
		echo $election->cbdata['header'];
		$election->cbdata['header'] = false;
	}
	fwrite( $election->cbdata['outFile'], "<vote>" . $row->vote_record . "</vote>\n" );
}

