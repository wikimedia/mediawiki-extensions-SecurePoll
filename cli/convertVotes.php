<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Crypt\Crypt;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Store\MemoryStore;
use MediaWiki\Extension\SecurePoll\VoteRecord;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;

class ConvertVotes extends Maintenance {
	/**
	 * @var Context
	 */
	private $context;

	/**
	 * @var Election
	 */
	private $election;

	/**
	 * @var string[][]
	 */
	private $votes;

	/**
	 * @var Crypt|false
	 */
	private $crypt;

	/**
	 * @var Ballot
	 */
	private $ballot;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Converts votes' );

		$this->addOption( 'name', 'Name of the election', false, true );
		$this->addArg( 'dump', 'Dump file to process', false );
		$this->addOption( 'no-proof-protection', 'Disable protection for proof of vote (vote buying)' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		if ( !$this->hasOption( 'name' ) && $this->hasArg( 0 ) ) {
			$this->convertFile( $this->getArg( 0 ) );
		} elseif ( $this->hasOption( 'name' ) ) {
			$this->convertLocalElection( $this->getOption( 'name' ) );
		} else {
			$this->fatalError( 'Need to pass either --name or the dump file as an argument' );
		}
	}

	private function convertFile( $fileName ) {
		$this->context = Context::newFromXmlFile( $fileName );
		if ( !$this->context ) {
			$this->fatalError( "Unable to parse XML file \"$fileName\"" );
		}
		$store = $this->context->getStore();
		if ( !$store instanceof MemoryStore ) {
			$class = get_class( $store );
			throw new RuntimeException(
				"Expected instance of MemoryStore, got $class instead"
			);
		}
		$electionIds = $store->getAllElectionIds();
		if ( !count( $electionIds ) ) {
			$this->fatalError( "No elections found in XML file \"$fileName\"" );
		}
		$electionId = reset( $electionIds );
		$this->election = $this->context->getElection( reset( $electionIds ) );
		$this->convert( $electionId );
	}

	private function convertLocalElection( $name ) {
		$this->context = new Context;
		$this->election = $this->context->getElectionByTitle( $name );
		if ( !$this->election ) {
			$this->fatalError( "The specified election does not exist." );
		}
		$this->convert( $this->election->getId() );
	}

	private function convert( $electionId ) {
		$this->votes = [];
		$this->crypt = $this->election->getCrypt();
		$this->ballot = $this->election->getBallot();

		$status = $this->context->getStore()->callbackValidVotes(
			$electionId, [ $this, 'convertVote' ] );

		if ( $this->crypt ) {
			// Delete temporary files
			$this->crypt->cleanup();
		}

		if ( !$status->isOK() ) {
			$this->fatalError( "Error: " . $status->getWikiText() );
		}

		$s = '';
		foreach ( $this->election->getQuestions() as $question ) {
			if ( $s !== '' ) {
				$s .= str_repeat( '-', 80 ) . "\n\n";
			}
			$s .= $question->getMessage( 'text' ) . "\n";
			$names = [];
			foreach ( $question->getOptions() as $option ) {
				$names[$option->getId()] = $option->getMessage( 'text' );
			}
			ksort( $names );
			$names = array_values( $names );
			foreach ( $names as $i => $name ) {
				$s .= ( $i + 1 ) . '. ' . $name . "\n";
			}
			// @phan-suppress-next-line PhanTypeInvalidDimOffset False positive
			$votes = $this->votes[$question->getId()];
			sort( $votes );
			$s .= implode( "\n", $votes ) . "\n";
		}
		$this->output( $s );
	}

	private function convertVote( $store, $record ) {
		if ( $this->crypt ) {
			$status = $this->crypt->decrypt( $record );
			if ( !$status->isOK() ) {
				return $status;
			}
			$record = $status->value;
		}
		$status = VoteRecord::readBlob( $record );
		if ( !$status->isOK() ) {
			return $status;
		}
		/** @var VoteRecord $voteRecord */
		$voteRecord = $status->value;
		$record = $voteRecord->getBallotData();
		$record = $this->ballot->convertRecord( $record );
		if ( $record === false ) {
			$this->fatalError( 'Error: missing question in vote record' );
		}
		foreach ( $record as $qid => $qrecord ) {
			$this->votes[$qid][] = $qrecord;
		}
		return Status::newGood();
	}
}

$maintClass = ConvertVotes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
