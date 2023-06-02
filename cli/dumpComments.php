<?php

/**
 * Dump the comments from an election from a dump file or local database.
 *
 * For the purposes of the Personal Image Filter referendum, this script
 * dumps the answers to all questions in key order.
 *
 * Can be used to tally very large numbers of votes, when the web interface is
 * not feasible.
 */

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Store\MemoryStore;
use MediaWiki\Extension\SecurePoll\Talliers\CommentDumper;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class DumpComments extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Dump the comments from an election from a dump file or local database' );

		$this->addOption( 'name', 'Name of the election', false, true );
		$this->addArg( 'dump', 'Dump file to process', false );
		$this->addOption( 'html', 'Output results in HTML' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		$context = new Context;
		if ( !$this->hasOption( 'name' ) && $this->hasArg( 0 ) ) {
			$dump = $this->getArg( 0 );
			$context = Context::newFromXmlFile( $dump );
			if ( !$context ) {
				$this->fatalError( "Unable to parse XML file \"{$dump}\"" );
			}
			$store = $context->getStore();
			if ( !$store instanceof MemoryStore ) {
				$class = get_class( $store );
				throw new RuntimeException(
					"Expected instance of MemoryStore, got $class instead"
				);
			}
			$electionIds = $store->getAllElectionIds();
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

		$tallier = new CommentDumper( $context, $election );
		$status = $tallier->execute();
		if ( !$status->isOK() ) {
			$this->fatalError( "Tally error: " . $status->getWikiText() );
		}

		// $tallier = $status->value;
		if ( $this->getOption( 'html' ) ) {
			$this->output( $tallier->getHtmlResult() );
		} else {
			$this->output( $tallier->getTextResult() );
		}
	}
}

$maintClass = DumpComments::class;
require_once RUN_MAINTENANCE_IF_MAIN;
