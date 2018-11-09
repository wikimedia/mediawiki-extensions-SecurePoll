<?php

require __DIR__ . '/cli.inc';
$testDir = __DIR__ . '/debtest';
if ( !is_dir( $testDir ) ) {
	mkdir( $testDir );
}

/**
 * @param string $val To echo
 * @param-taint $val none
 */
function out( $val ) {
	echo $val;
}

$spDebianVoteDir = '/home/tstarling/src/voting/debian-vote/debian-vote-0.8-fixed';

if ( count( $args ) ) {
	foreach ( $args as $arg ) {
		if ( !file_exists( $arg ) ) {
			out( "File not found: $arg\n" );
			exit( 1 );
		}
		$debResult = spRunDebianVote( $arg );
		if ( spRunTest( $arg, $debResult ) ) {
			out( "$arg OK\n" );
		}
	}
	exit( 0 );
}

for ( $i = 1; true; $i++ ) {
	$fileName = "$testDir/$i.in";
	spGenerateTest( $fileName );
	$debResult = spRunDebianVote( $fileName );
	if ( spRunTest( $fileName, $debResult ) ) {
		unlink( $fileName );
	}
	if ( $i % 1000 == 0 ) {
		out( "$i tests done\n" );
	}
}

/**
 * @suppress SecurityCheck-XSS
 * @param string $fileName
 * @param string $debResult
 * @return bool
 */
function spRunTest( $fileName, $debResult ) {
	$file = fopen( $fileName, 'r' );
	if ( !$file ) {
		out( "Unable to open file \"$fileName\" for input\n" );
		return;
	}

	$votes = [];
	$numCands = 0;
	while ( !feof( $file ) ) {
		$line = fgets( $file );
		if ( $line === false ) {
			break;
		}
		$line = trim( $line );
		if ( !preg_match( '/^V: ([0-9-]*)$/', $line, $m ) ) {
			out( "Skipping unrecognized line $line\n" );
			continue;
		}

		$record = [];
		for ( $i = 0, $len = strlen( $m[1] ); $i < $len; $i++ ) {
			$pref = substr( $m[1], $i, 1 );
			if ( $i == strlen( $m[1] ) - 1 ) {
				$id = 'X';
			} else {
				$id = chr( ord( 'A' ) + $i );
			}
			if ( $pref === '-' ) {
				$record[$id] = 1000;
			} else {
				$record[$id] = intval( $pref );
			}
		}
		$votes[] = $record;
		$numCands = max( $numCands, count( $record ) );
	}

	$options = [];
	for ( $i = 0; $i < $numCands - 1; $i++ ) {
		$id = chr( ord( 'A' ) + $i );
		$options[$id] = $id;
	}
	$options['X'] = 'X';
	$question = new SecurePoll_FakeQuestion( $options );
	$tallier = new SecurePoll_SchulzeTallier( false, $question );
	foreach ( $votes as $vote ) {
		$tallier->addVote( $vote );
	}
	$tallier->finishTally();
	$ranks = $tallier->getRanks();
	$winners = [];
	foreach ( $ranks as $oid => $rank ) {
		if ( $rank === 1 ) {
			$winners[] = $oid;
		}
	}
	if ( count( $winners ) > 1 ) {
		$expected = 'result: tie between options ' . implode( ', ', $winners );
	} else {
		$expected = 'result: option ' . reset( $winners ) . ' wins';
	}
	if ( $debResult === $expected ) {
		return true;
	}

	out( "Mismatch in file $fileName\n" );
	out( "Debian got: $debResult\n" );
	out( "We got: $expected\n\n" );
	out( $tallier->getTextResult() );
	return false;
}

function spRunDebianVote( $fileName ) {
	global $spDebianVoteDir;
	$result = wfShellExec(
		wfEscapeShellArg(
			"$spDebianVoteDir/debian-vote",
			$fileName
		)
	);
	if ( !$result ) {
		out( "Error running debian vote!\n" );
		exit( 1 );
	}
	$result = rtrim( $result );
	$lastLineEnd = strrpos( $result, "\n" );
	$lastLine = substr( $result, $lastLineEnd + 1 );
	return $lastLine;
}

function spGetRandom( $min, $max ) {
	return mt_rand( 0, mt_getrandmax() ) / mt_getrandmax() * ( $max - $min ) + $min;
}

function spGenerateTest( $fileName ) {
	global $spDebianVoteDir;
	wfShellExec(
		wfEscapeShellArg( "$spDebianVoteDir/votegen" ) . ' > ' .
		wfEscapeShellArg( $fileName )
	);
}

class SecurePoll_FakeQuestion {
	public $options;

	public function __construct( $options ) {
		$this->options = [];
		foreach ( $options as $i => $option ) {
			$this->options[] = new SecurePoll_FakeOption( $i, $option );
		}
	}

	public function getOptions() {
		return $this->options;
	}
}

class SecurePoll_FakeOption {
	public $id, $name;

	public function __construct( $id, $name ) {
		$this->id = $id;
		$this->name = $name;
	}

	public function getMessage( $key ) {
		return $this->name;
	}

	public function parseMessage( $key ) {
		return htmlspecialchars( $this->name );
	}

	public function getId() {
		return $this->id;
	}
}
