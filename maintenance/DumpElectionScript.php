<?php

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\DumpElection;
use MediaWiki\Maintenance\Maintenance;

/**
 * Generate an XML dump of an election, including configuration and votes.
 *
 * Including "Script" in the name to differentiate it from includes/DumpElection.php
 */
class DumpElectionScript extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generate an XML dump of an election, including configuration and votes' );

		$this->addArg( 'electionname', 'Name of the election' );
		$this->addOption( 'o', 'Output to the specified file', false, true );
		$this->addOption( 'by-id', 'Get election using its numerical ID, instead of its title' );
		$this->addOption( 'votes', 'Include vote records' );
		$this->addOption( 'all-langs', 'Include messages for all languages instead of just the primary' );
		$this->addOption( 'jump', 'Produce a configuration dump suitable for setting up a redirect poll' );
		$this->addOption( 'private', 'Include encryption keys' );

		$this->requireExtension( 'SecurePoll' );
	}

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

		if ( $this->hasOption( 'all-langs' ) ) {
			$langs = $election->getLangList();
		} else {
			$langs = [ $election->getLanguage() ];
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

		try {
			$dump = DumpElection::createXMLDump( $election, [
				'jump' => $this->getOption( 'jump', false ),
				'langs' => $langs,
				'private' => $this->hasOption( 'private' )
			], $this->hasOption( 'votes' ) );
		} catch ( Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}

		fwrite( $outFile, $dump );
	}
}

// @codeCoverageIgnoreStart
$maintClass = DumpElectionScript::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
