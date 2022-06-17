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

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;

class DumpElection extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generate an XML dump of an election, including configuration and votes' );

		$this->addArg( 'electionname', 'Name of the election' );
		$this->addOption( 'o', 'Output to the specified file', false, true );
		$this->addOption( 'by-id', 'Get election using its numerical ID, instead of its title' );
		$this->addOption( 'votes', 'Include vote records' );
		$this->addOption( 'all-langs', 'Include messages for all languages instead of just the primary' );
		$this->addOption( 'jump', 'Produce a configuration dump suitable for setting up a jump wiki' );
		$this->addOption( 'private', 'Include encryption keys' );

		$this->requireExtension( 'SecurePoll' );
	}

	/**
	 * @suppress PhanUndeclaredProperty cbdata is unknown to Election
	 */
	public function execute() {
		$context = new Context;

		$name = $this->getArg( 0 );
		if ( $this->hasOption( 'by-id' ) ) {
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

		if ( $this->hasOption( 'all-langs' ) ) {
			$langs = $election->getLangList();
		} else {
			$langs = [ $election->getLanguage() ];
		}
		$confXml = $election->getConfXml( [
			'jump' => $this->getOption( 'jump', false ),
			'langs' => $langs,
			'private' => $this->hasOption( 'private' )
		] );

		$cbdata = [
			'header' => "<SecurePoll>\n<election>\n$confXml",
			'outFile' => $outFile
		];
		$election->cbdata = $cbdata;

		# Write vote records
		if ( $this->hasOption( 'votes' ) ) {
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

	/**
	 * @suppress PhanUndeclaredProperty cbdata is unknown to Election
	 * @param Election $election
	 * @param \stdClass $row
	 */
	public function dumpVote( $election, $row ) {
		if ( $election->cbdata['header'] ) {
			fwrite( $election->cbdata['outFile'], $election->cbdata['header'] );
			$election->cbdata['header'] = false;
		}
		fwrite( $election->cbdata['outFile'],
			"<vote>\n" . rtrim( $row->vote_record ) . "\n</vote>\n" );
	}
}

$maintClass = DumpElection::class;
require_once RUN_MAINTENANCE_IF_MAIN;
