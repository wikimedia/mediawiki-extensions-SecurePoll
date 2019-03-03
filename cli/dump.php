<?php

/**
 * Generate an XML dump of an election, including configuration and votes.
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class DumpElection extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Generate an XML dump of an election, including configuration and votes';

		$this->addArg( 'electionname', 'Name of the election' );
		$this->addOption( 'o', 'Output to the specified file' );
		$this->getOption( 'by-id', 'Get election using its numerical ID, instead of its title' );
		$this->getOption( 'votes', 'Include vote records' );
		$this->getOption( 'all-langs', 'Include messages for all languages instead of just the primary' );
		$this->getOption( 'jump', 'Produce a configuration dump suitable for setting up a jump wiki' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$context = new SecurePoll_Context;

		$name = $this->getArg( 0 );
		if ( $this->getOption( 'by-id' ) ) {
			$election = $context->getElection( $name );
		} else {
			$election = $context->getElectionByTitle( $name );
		}

		if ( !$election ) {
			$this->fatalError( "There is no election called \"$name\"" );
		}

		$fileName = $this->getOption( 'o', '-' );

		if ( $fileName === '-' ) {
			$outFile = STDOUT;
		} else {
			$outFile = fopen( $fileName, 'w' );
		}

		if ( !$outFile ) {
			$this->fatalError( "Unable to open $fileName for writing" );
		}

		if ( $this->getOption( 'all-langs' ) ) {
			$langs = $election->getLangList();
		} else {
			$langs = [ $election->getLanguage() ];
		}
		$confXml = $election->getConfXml( [
			'jump' => $this->getOption( 'jump', false ),
			'langs' => $langs
		] );

		$cbdata = [
			'header' => "<SecurePoll>\n<election>\n$confXml",
			'outFile' => $outFile
		];
		$election->cbdata = $cbdata;

		# Write vote records
		if ( $this->getOption( 'votes' ) ) {
			$status = $election->dumpVotesToCallback( [ $this, 'dumpVote' ] );
			if ( !$status->isOK() ) {
				$this->fatalError( $status->getWikiText() );
			}
		}
		if ( $election->cbdata['header'] ) {
			fwrite( $outFile, $election->cbdata['header'] );
		}

		fwrite( $outFile, "</election>\n</SecurePoll>\n" );
	}

	public function dumpVote( $election, $row ) {
		if ( $election->cbdata['header'] ) {
			fwrite( $election->cbdata['outFile'], $election->cbdata['header'] );
			$election->cbdata['header'] = false;
		}
		fwrite( $election->cbdata['outFile'], "<vote>" . $row->vote_record . "</vote>\n" );
	}
}

$maintClass = DumpElection::class;
require_once RUN_MAINTENANCE_IF_MAIN;
