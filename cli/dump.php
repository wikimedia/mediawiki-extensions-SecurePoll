<?php

/**
 * Generate an XML dump of an election, including configuration and votes.
 */

$optionsWithArgs = [ 'o' ];
require __DIR__ . '/cli.inc';

$usage = <<<EOT
Usage: php dump.php [options...] <election name>
Options:
    -o <outfile>                Output to the specified file
    --by-id                     Get election using its numerical ID, instead of its title
    --votes                     Include vote records
    --all-langs                 Include messages for all languages instead of just the primary
    --jump                      Produce a configuration dump suitable for setting up a jump wiki
EOT;

if ( !isset( $args[0] ) ) {
	spFatal( $usage );
}

$context = new SecurePoll_Context;
if ( isset( $options['by-id'] ) ) {
  $election = $context->getElection( $args[0] );
} else {
  $election = $context->getElectionByTitle( $args[0] );
}

if ( !$election ) {
	spFatal( "There is no election called \"$args[0]\"" );
}

if ( !isset( $options['o'] ) ) {
	$fileName = '-';
} else {
	$fileName = $options['o'];
}
if ( $fileName === '-' ) {
	$outFile = STDOUT;
} else {
	$outFile = fopen( $fileName, 'w' );
}
if ( !$outFile ) {
	spFatal( "Unable to open $fileName for writing" );
}

if ( isset( $options['all-langs'] ) ) {
	$langs = $election->getLangList();
} else {
	$langs = [ $election->getLanguage() ];
}
$confXml = $election->getConfXml( [
	'jump' => isset( $options['jump'] ),
	'langs' => $langs
] );

$cbdata = [
	'header' => "<SecurePoll>\n<election>\n$confXml",
	'outFile' => $outFile
];
$election->cbdata = $cbdata;

# Write vote records
if ( isset( $options['votes'] ) ) {
	$status = $election->dumpVotesToCallback( 'spDumpVote' );
	if ( !$status->isOK() ) {
		spFatal( $status->getWikiText() );
	}
}
if ( $election->cbdata['header'] ) {
	fwrite( $outFile, $election->cbdata['header'] );
}

fwrite( $outFile, "</election>\n</SecurePoll>\n" );

function spFatal( $message ) {
	fwrite( STDERR, rtrim( $message ) . "\n" );
	exit( 1 );
}

function spDumpVote( $election, $row ) {
	if ( $election->cbdata['header'] ) {
		fwrite( $election->cbdata['outFile'], $election->cbdata['header'] );
		$election->cbdata['header'] = false;
	}
	fwrite( $election->cbdata['outFile'], "<vote>" . $row->vote_record . "</vote>\n" );
}
