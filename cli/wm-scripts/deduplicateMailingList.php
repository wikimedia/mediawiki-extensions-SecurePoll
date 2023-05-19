<?php

namespace MediaWiki\Extension\SecurePoll;

use Generator;
use InvalidArgumentException;
use Maintenance;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";

require __DIR__ . '/includes/MailingListEntry.php';

/**
 * Deduplicate a set of mailing lists produced by makeMailingList.php. If
 * multiple users from different wikis are present, choose the one with the
 * greatest edit count.
 *
 * The arguments are a list of mailing list files. If there are no arguments,
 * a mailing list is read from stdin.
 */
class DeduplicateMailingList extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'output-file',
			'The output file name',
			false, true, 'o' );
		$this->addArg( 'input-file', 'The input file or a list of files', false );
	}

	public function execute() {
		if ( $this->hasOption( 'output-file' ) ) {
			$outFile = fopen( $this->getOption( 'output-file' ), 'w' );
			if ( !$outFile ) {
				$this->fatalError( "Unable to open output file" );
			}
		} else {
			$outFile = STDOUT;
		}
		$entries = [];
		/** @var MailingListEntry $newEntry */
		foreach ( $this->readEntries() as $newEntry ) {
			$name = $newEntry->userName;
			if ( !isset( $entries[$name] )
				|| $newEntry->editCount > $entries[$name]->editCount
			) {
				$entries[$name] = $newEntry;
			}
		}
		foreach ( $entries as $entry ) {
			fwrite( $outFile, $entry->toString() );
		}
	}

	/**
	 * Read entries from the input files, returning an iterator over
	 * MailingListEntry objects
	 *
	 * @return Generator|iterable<MailingListEntry>
	 */
	private function readEntries() {
		$args = $this->getArgs();
		$numArgs = count( $args );
		if ( !$numArgs ) {
			$this->error( "Reading from stdin..." );
			foreach ( $this->readEntriesFromFile( STDIN, 'stdin' ) as $entry ) {
				yield $entry;
			}
		} else {
			for ( $fileIndex = 0; $fileIndex < count( $args ); $fileIndex++ ) {
				$fileName = $this->getArg( $fileIndex );
				$file = fopen( $fileName, 'r' );
				if ( !$file ) {
					$this->fatalError( "Unable to open input file \"$fileName\"" );
				}
				foreach ( $this->readEntriesFromFile( $file, $fileName ) as $entry ) {
					yield $entry;
				}
			}
		}
	}

	/**
	 * Read entries from one file-like resource.
	 *
	 * @param resource $file
	 * @param string $fileName
	 * @return Generator|iterable<MailingListEntry>
	 */
	private function readEntriesFromFile( $file, $fileName ) {
		for ( $lineNum = 1; true; $lineNum++ ) {
			$line = fgets( $file );
			if ( $line === false ) {
				break;
			}
			if ( $line === "\n" ) {
				continue;
			}
			try {
				yield MailingListEntry::newFromString( $line );
			} catch ( InvalidArgumentException $e ) {
				$this->error( "Skipping invalid entry in file $fileName line $lineNum" );
			}
		}
	}
}

$maintClass = DeduplicateMailingList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
