<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ConvertVotes extends Maintenance {
	/**
	 * @var SecurePoll_Context
	 */
	private $context;

	/**
	 * @var SecurePoll_Election
	 */
	private $election;

	/**
	 * @var array
	 */
	private $votes;

	/**
	 * @var SecurePoll_Crypt
	 */
	private $crypt;

	/**
	 * @var SecurePoll_Ballot
	 */
	private $ballot;

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Converts votes';

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
		$this->context = SecurePoll_Context::newFromXmlFile( $fileName );
		if ( !$this->context ) {
			$this->fatalError( "Unable to parse XML file \"$fileName\"" );
		}
		$electionIds = $this->context->getStore()->getAllElectionIds();
		if ( !count( $electionIds ) ) {
			$this->fatalError( "No elections found in XML file \"$fileName\"" );
		}
		$electionId = reset( $electionIds );
		$this->election = $this->context->getElection( reset( $electionIds ) );
		$this->convert( $electionId );
	}

	private function convertLocalElection( $name ) {
		$this->context = new SecurePoll_Context;
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
		$record = rtrim( $record );
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
