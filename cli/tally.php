<?php

/**
 * Tally an election from a dump file or local database.
 *
 * Can be used to tally very large numbers of votes, when the web interface is 
 * not feasible.
 *
 * TODO: The entity classes need a bit of refactoring so that they can operate on
 * a dump file without having to import it into the database. This will avoid 
 * some nasty ID collision issues with the import approach.
 */

$optionsWithArgs = array( 'name' );
require( dirname(__FILE__).'/cli.inc' );

$usage = <<<EOT
Usage: 
  php tally.php [--html] --name <election name>
  php tally.php [--html] <dump file>

EOT;

if ( !isset( $options['name'] ) && isset( $args[0] ) ) {
	echo "Dump files are not supported yet.\n";
	exit( 1 );
} elseif( !isset( $options['name'] ) ) {
	echo $usage;
	exit( 1 );
}

if ( !class_exists( 'SecurePoll' ) ) {
	# Uninstalled mode
	# This may actually work some day, for now it will just give you DB errors
	require( dirname( __FILE__ ) . '/../SecurePoll.php' );
}
$election = SecurePoll::getElectionByTitle( $options['name'] );
if ( !$election ) {
	echo "The specified election does not exist.\n";
	exit( 1 );
}
$election = SecurePoll::getElection( $eid );
spTallyLocal( $election );

function spTallyLocal( $election ) {
	$dbr = wfGetDB( DB_SLAVE );
	$startId = 0;
	$crypt = $election->getCrypt();
	$ballot = $election->getBallot();
	$questions = $election->getQuestions();
	$talliers = $election->getTalliers();

	while ( true ) {
		$res = $dbr->select( 
			'securepoll_votes',
			array( 'vote_id', 'vote_record' ),
			array( 
				'vote_election' => $election->getId(),
				'vote_current' => 1,
				'vote_struck' => 0,
				'vote_id > ' . $dbr->addQuotes( $startId )
			), __METHOD__,
			array( 'LIMIT' => 100, 'ORDER BY' => 'vote_id' )
		);
		if ( !$res->numRows() ) {
			break;
		}
		foreach ( $res as $row ) {
			var_dump( $row );
			$startId = $row->vote_id;
			$record = $row->vote_record;
			if ( $crypt ) {
				$status = $crypt->decrypt( $record );
				if ( !$status->isOK() ) {
					echo $status->getWikiText() . "\n";
					return;
				}
				$record = $status->value;
			}
			$record = rtrim( $record );
			$scores = $ballot->unpackRecord( $record );
			foreach ( $questions as $question ) {
				$qid = $question->getId();
				if ( !isset( $scores[$qid] ) ) {
					echo wfMsg( 'securepoll-tally-error' ) . "\n";
					return;
				}
				if ( !$talliers[$qid]->addVote( $scores[$qid] ) ) {
					echo wfMsg( 'securepoll-tally-error' ) . "\n";
					return;
				}
			}
		}
	}
	$first = true;
	foreach ( $questions as $question ) {
		if ( $first ) {
			$first = false;
		} else {
			echo "\n";
		}
		$tallier = $talliers[$question->getId()];
		$tallier->finishTally();
		echo $question->getMessage( 'text' ) . "\n" .
			$tallier->getTextResult();
	}
}
