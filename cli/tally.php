<?php

/**
 * Tally an election from a dump file or local database.
 *
 * Can be used to tally very large numbers of votes, when the web interface is
 * not feasible.
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class TallyElection extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Tally an election from a dump file or local database' );

		$this->addOption( 'name', 'Name of the election', false, true );
		$this->addArg( 'dump', 'Dump file to process', false );
		$this->addOption( 'html', 'Output results in HTML' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		global $wgTitle;

		// TODO: Is this necessary?
		$wgTitle = Title::newFromText( 'Special:SecurePoll' );

		$context = new SecurePoll_Context;
		if ( !$this->hasOption( 'name' ) && $this->hasArg( 0 ) ) {
			$dump = $this->getArg( 0 );
			$context = SecurePoll_Context::newFromXmlFile( $dump );
			if ( !$context ) {
				$this->fatalError( "Unable to parse XML file \"{$dump}\"" );
			}
			$electionIds = $context->getStore()->getAllElectionIds();
			if ( !count( $electionIds ) ) {
				$this->fatalError( "No elections found in XML file \"{$dump}\"" );
			}
			$election = $context->getElection( reset( $electionIds ) );
		} elseif ( $this->hasOption( 'name' ) ) {
			$election = $context->getElectionByTitle( $this->getOption( 'name' ) );
			if ( !$election ) {
				$this->fatalError( "The specified election does not exist." );
			}
		} else {
			$this->fatalError( 'Need to pass either --name or the dump file as an argument' );
		}

		$status = $election->tally();
		if ( !$status->isOK() ) {
			$this->fatalError( 'Tally error: ' . $status->getWikiText() );
		}
		$tallier = $status->value;
		if ( $this->getOption( 'html' ) ) {
			$this->output( $tallier->getHtmlResult() );
		} else {
			$this->output( $tallier->getTextResult() );
		}
	}
}

$maintClass = TallyElection::class;
require_once RUN_MAINTENANCE_IF_MAIN;
