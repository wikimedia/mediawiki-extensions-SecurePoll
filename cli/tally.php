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

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Store\MemoryStore;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\MediaWikiServices;

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
			if ( !$election->isFinished() ) {
				$this->fatalError( "Cannot tally the election until after voting is complete" );
			}
		} else {
			$this->fatalError( 'Need to pass either --name or the dump file as an argument' );
		}

		$startTime = time();
		$status = $election->tally();
		if ( !$status->isOK() ) {
			$this->fatalError( 'Tally error: ' . $status->getWikiText() );
		}
		/** @var ElectionTallier $tallier */
		$tallier = $status->value;
		'@phan-var ElectionTallier $tallier';

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
		$dbw->replace(
			'securepoll_properties',
			[
				[
					'pr_entity',
					'pr_key'
				],
			],
			[
				[
					'pr_entity' => $election->getId(),
					'pr_key' => 'tally-result',
					'pr_value' => json_encode( $tallier->getJSONResult() ),
				],
				[
					'pr_entity' => $election->getId(),
					'pr_key' => 'tally-result-time',
					'pr_value' => time(),
				],
			],
			__METHOD__
		);

		if ( $this->hasOption( 'html' ) ) {
			$this->output( $tallier->getHtmlResult() );
		} else {
			$this->output( $tallier->getTextResult() );
		}
		$endTime = time();
		$timeElapsed = $endTime - $startTime;
		$this->output( "\n" );
		$this->output( "Script finished in $timeElapsed s" );
		$this->output( "\n" );
	}
}

$maintClass = TallyElection::class;
require_once RUN_MAINTENANCE_IF_MAIN;
