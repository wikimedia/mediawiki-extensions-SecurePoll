<?php

require( dirname(__FILE__).'/cli.inc' );

if ( !isset( $args[0] ) ) {
	echo "Usage: php tallyDebian.php <file>\n";
	exit( 1 );
}

$file = fopen( $args[0], 'r' );
if ( !$file ) {
	echo "Unable to open file \"$args[0]\" for input\n";
}

$votes = array();
$numCands = 0;
while ( !feof( $file ) ) {
	$line = fgets( $file );
	if ( $line === false ) {
		break;
	}
	$line = trim( $line );
	if ( !preg_match( '/^V: ([0-9-]*)$/', $line, $m ) ) {
		echo "Skipping unrecognised line $line\n";
		continue;
	}

	$record = array();
	for ( $i = 0; $i < strlen( $m[1] ); $i++ ) {
		$pref = substr( $m[1], $i, 1 );
		if ( $pref === '-' ) {
			$record[$i] = 1000;
		} else {
			$record[$i] = intval( $pref );
		}
	}
	$votes[] = $record;
	$numCands = max( $numCands, count( $record ) );
}

$options = array();
for ( $i = 0; $i < $numCands - 1; $i++ ) {
	$options[] = chr( ord( 'A' ) + $i );
}
$options[] = 'X';
$question = new SecurePoll_FakeQuestion( $options );
$tallier = new SecurePoll_SchulzeTallier( false, $question );
foreach ( $votes as $vote ) {
	$tallier->addVote( $vote );
}
$tallier->finishTally();
echo $tallier->getTextResult();



class SecurePoll_FakeQuestion {
	var $options;

	function __construct( $options ) {
		$this->options = array();
		foreach ( $options as $i => $option ) {
			$this->options[] = new SecurePoll_FakeOption( $i, $option );
		}
	}

	function getOptions() {
		return $this->options;
	}
}

class SecurePoll_FakeOption {
	var $id, $name;

	function __construct( $id, $name ) {
		$this->id = $id;
		$this->name = $name;
	}

	function getMessage( $key ) {
		return $this->name;
	}

	function parseMessage( $key ) {
		return htmlspecialchars( $this->name );
	}

	function getId() {
		return $this->id;
	}
}

