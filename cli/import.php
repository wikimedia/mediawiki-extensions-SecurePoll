<?php

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Store\Store;
use MediaWiki\Extension\SecurePoll\Store\XMLStore;
use MediaWiki\Maintenance\Maintenance;

class ImportElectionConfiguration extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( <<<EOT
Import configuration files into the local SecurePoll database. Files can be
generated with dump.php.

Note that any vote records will NOT be imported.

For the moment, the entity IDs are preserved, to allow easier implementation of
the message update feature. This means conflicting entity IDs in the local
database will generate an error.
EOT
		);

		$this->addOption(
			'update-msgs',
			'Update the internationalised text for the elections, do not update configuration.'
		);
		$this->addOption(
			'replace',
			'If an election with a conflicting title exists already, replace it, '
				. 'updating its configuration. The default is to exit with an error.'
		);

		$this->addArg( 'file', 'File to import' );

		$this->requireExtension( 'SecurePoll' );
	}

	public function execute() {
		# Most of the code here will eventually be refactored into the update interfaces
		# of the entity and context classes, but that project can wait until we have a
		# setup UI.

		$fileName = $this->getArg( 0 );
		if ( !file_exists( $fileName ) ) {
			$this->fatalError( "The specified file \"{$fileName}\" does not exist\n" );
		}

		$store = new XMLStore( $fileName );
		$success = $store->readFile();
		if ( !$success ) {
			$this->fatalError( "Error reading XML dump, possibly corrupt\n" );
		}
		$electionIds = $store->getAllElectionIds();
		if ( !count( $electionIds ) ) {
			$this->fatalError( "No elections found to import.\n" );
		}

		$xc = new Context;
		$xc->setStore( $store );
		$dbw = $this->getDB( DB_PRIMARY );

		# Start the configuration transaction
		$this->beginTransaction( $dbw, __METHOD__ );
		foreach ( $electionIds as $id ) {
			$elections = $store->getElectionInfo( [ $id ] );
			$electionInfo = reset( $elections );

			$existingId = $dbw->newSelectQueryBuilder()
				->select( 'el_entity' )
				->from( 'securepoll_elections' )
				->where( [ 'el_title' => $electionInfo['title'] ] )
				->forUpdate()
				->caller( __METHOD__ )
				->fetchField();

			if ( $existingId !== false ) {
				if ( $this->hasOption( 'replace' ) ) {
					$this->deleteElection( $existingId );
					$success = $this->importConfiguration( $store, $electionInfo );
				} elseif ( $this->hasOption( 'update-msgs' ) ) {
					# Do the message update and move on to the next election
					$success = $this->updateMessages( $store, $electionInfo );
				} else {
					$this->output( "Conflicting election title found \"{$electionInfo['title']}\"\n" );
					$this->output( "Use --replace to replace the existing election.\n" );
					$success = false;
				}
			} elseif ( $this->hasOption( 'update-msgs' ) ) {
				$this->output( "Cannot update messages: election \"{$electionInfo['title']}\" not found.\n" );
				$this->output( "Import the configuration first, without the --update-msgs switch.\n" );
				$success = false;
			} else {
				$success = $this->importConfiguration( $store, $electionInfo );
			}
			if ( !$success ) {
				$this->rollbackTransaction( $dbw, __METHOD__ );
				$this->fatalError( "Faied!\n" );
			}
		}
		$this->commitTransaction( $dbw, __METHOD__ );
		$this->output( "Finished!\n" );
	}

	/**
	 * @param int|string $electionId
	 */
	private function deleteElection( $electionId ) {
		$delete = new DeletePoll();
		$delete->loadWithArgv(
			[ '--id=' . $electionId ]
		);
		$delete->execute();
	}

	/**
	 * @param string $type
	 * @param string $id
	 */
	private function insertEntity( $type, $id ) {
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_entity' )
			->row( [
				'en_id' => $id,
				'en_type' => $type,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param Store $store
	 * @param array $electionInfo
	 * @return bool
	 */
	private function importConfiguration( $store, $electionInfo ) {
		$dbw = $this->getDB( DB_PRIMARY );
		$sourceIds = [];

		# Election
		$this->insertEntity( 'election', $electionInfo['id'] );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_elections' )
			->row( [
				'el_entity' => $electionInfo['id'],
				'el_title' => $electionInfo['title'],
				'el_ballot' => $electionInfo['ballot'],
				'el_tally' => $electionInfo['tally'],
				'el_primary_lang' => $electionInfo['primaryLang'],
				'el_start_date' => $dbw->timestamp( $electionInfo['startDate'] ),
				'el_end_date' => $dbw->timestamp( $electionInfo['endDate'] ),
				'el_auth_type' => $electionInfo['auth']
			] )
			->caller( __METHOD__ )
			->execute();
		$sourceIds[] = $electionInfo['id'];

		if ( isset( $electionInfo['questions'] ) ) {
			# Questions
			$index = 1;
			foreach ( $electionInfo['questions'] as $questionInfo ) {
				$this->insertEntity( 'question', $questionInfo['id'] );
				$dbw->newInsertQueryBuilder()
					->insertInto( 'securepoll_questions' )
					->row( [
						'qu_entity' => $questionInfo['id'],
						'qu_election' => $electionInfo['id'],
						'qu_index' => $index++,
					] )
					->caller( __METHOD__ )
					->execute();
				$sourceIds[] = $questionInfo['id'];

				# Options
				$insertBatch = [];
				foreach ( $questionInfo['options'] as $optionInfo ) {
					$this->insertEntity( 'option', $optionInfo['id'] );
					$insertBatch[] = [
						'op_entity' => $optionInfo['id'],
						'op_election' => $electionInfo['id'],
						'op_question' => $questionInfo['id']
					];
					$sourceIds[] = $optionInfo['id'];
				}
				if ( $insertBatch ) {
					$dbw->newInsertQueryBuilder()
						->insertInto( 'securepoll_options' )
						->rows( $insertBatch )
						->caller( __METHOD__ )
						->execute();
				}
			}
		}

		# Messages
		$this->insertMessages( $store, $sourceIds );

		# Properties
		$properties = $store->getProperties( $sourceIds );
		$insertBatch = [];
		foreach ( $properties as $id => $entityProps ) {
			foreach ( $entityProps as $key => $value ) {
				$insertBatch[] = [
					'pr_entity' => $id,
					'pr_key' => $key,
					'pr_value' => $value
				];
			}
		}
		if ( $insertBatch ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'securepoll_properties' )
				->rows( $insertBatch )
				->caller( __METHOD__ )
				->execute();
		}
		return true;
	}

	/**
	 * @param Store $store
	 * @param array $entityIds
	 */
	private function insertMessages( $store, $entityIds ) {
		$langs = $store->getLangList( $entityIds );
		$insertBatch = [];
		foreach ( $langs as $lang ) {
			$messages = $store->getMessages( $lang, $entityIds );
			foreach ( $messages as $id => $entityMsgs ) {
				foreach ( $entityMsgs as $key => $text ) {
					$insertBatch[] = [
						'msg_entity' => $id,
						'msg_lang' => $lang,
						'msg_key' => $key,
						'msg_text' => $text
					];
				}
			}
		}
		if ( $insertBatch ) {
			$dbw = $this->getDB( DB_PRIMARY );
			$dbw->newInsertQueryBuilder()
				->insertInto( 'securepoll_msgs' )
				->rows( $insertBatch )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * @param Store $store
	 * @param array $electionInfo
	 * @return bool
	 */
	private function updateMessages( $store, $electionInfo ) {
		$entityIds = [ $electionInfo['id'] ];
		if ( isset( $electionInfo['questions'] ) ) {
			foreach ( $electionInfo['questions'] as $questionInfo ) {
				$entityIds[] = $questionInfo['id'];
				foreach ( $questionInfo['options'] as $optionInfo ) {
					$entityIds[] = $optionInfo['id'];
				}
			}
		}

		# Delete existing messages
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_msgs' )
			->where( [ 'msg_entity' => $entityIds ] )
			->caller( __METHOD__ )
			->execute();

		# Insert new messages
		$this->insertMessages( $store, $entityIds );
		return true;
	}
}

$maintClass = ImportElectionConfiguration::class;
require_once RUN_MAINTENANCE_IF_MAIN;
