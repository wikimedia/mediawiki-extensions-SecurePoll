<?php

namespace MediaWiki\Extension\SecurePoll\Crypt;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Shell\Shell;
use MediaWiki\Status\Status;
use MWCryptRand;
use Wikimedia\Rdbms\IDatabase;

/**
 * Cryptography module that shells out to GPG
 *
 * Election properties used:
 *     gpg-encrypt-key:    The public key used for encrypting (from gpg --export)
 *     gpg-sign-key:       The private key used for signing (from gpg --export-secret-keys)
 *     gpg-decrypt-key:    The private key used for decrypting.
 *
 * Generally only gpg-encrypt-key and gpg-sign-key are required for voting,
 * gpg-decrypt-key is for tallying.
 */
class GpgCrypt extends Crypt {
	/** @var Context|null */
	public $context;
	/** @var Election|null */
	public $election;
	/** @var string|null */
	public $recipient;
	/** @var string|null */
	public $signer;
	/** @var string|null */
	public $homeDir;

	public static function getCreateDescriptors() {
		global $wgSecurePollGpgSignKey;

		$ret = parent::getCreateDescriptors();
		$ret['election'] += [
			'gpg-encrypt-key' => [
				'label-message' => 'securepoll-create-label-gpg_encrypt_key',
				'type' => 'textarea',
				'SecurePoll_type' => 'property',
				'rows' => 5,
				'validation-callback' => [ self::class, 'checkEncryptKey' ],
			],
		];

		if ( $wgSecurePollGpgSignKey ) {
			$ret['election'] += [
				'gpg-sign-key' => [
					'type' => 'api',
					'default' => $wgSecurePollGpgSignKey,
					'SecurePoll_type' => 'property',
				],
			];
		} else {
			$ret['election'] += [
				'gpg-sign-key' => [
					'label-message' => 'securepoll-create-label-gpg_sign_key',
					'type' => 'textarea',
					'SecurePoll_type' => 'property',
					'rows' => 5,
					'validation-callback' => [ self::class, 'checkSignKey' ],
				],
			];
		}

		return $ret;
	}

	public function getTallyDescriptors(): array {
		return [
			'gpg-decrypt-key' => [
				'label-message' => 'securepoll-tally-gpg-decrypt-key',
				'type' => 'textarea',
				'required' => true,
				'rows' => 5,
				'validation-callback' => [ self::class, 'checkEncryptKey' ],
			],
		];
	}

	public function updateDbForTallyJob(
		int $electionId,
		IDatabase $dbw,
		array $data
	): void {
		// Add private key to DB if it was entered in the form
		if ( isset( $data['gpg-decrypt-key'] ) ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'securepoll_properties' )
				->row( [
					'pr_entity' => $electionId,
					'pr_key' => 'gpg-decrypt-key',
					'pr_value' => $data['gpg-decrypt-key'],
				] )
				->onDuplicateKeyUpdate()
				->uniqueIndexFields( [ 'pr_entity', 'pr_key' ] )
				->set( [
					'pr_entity' => $electionId,
					'pr_key' => 'gpg-decrypt-key',
					'pr_value' => $data['gpg-decrypt-key'],
				] )
				->caller( __METHOD__ )
				->execute();
			$dbw->newInsertQueryBuilder()
				->insertInto( 'securepoll_properties' )
				->ignore()
				->row( [
					'pr_entity' => $electionId,
					'pr_key' => 'delete-gpg-decrypt-key',
					'pr_value' => 1,
				] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public function cleanupDbForTallyJob( int $electionId, IDatabase $dbw ): void {
		$result = $dbw->newSelectQueryBuilder()
			->select( 'pr_entity' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $electionId,
				'pr_key' => 'delete-gpg-decrypt-key',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Only delete key if it was added for this job
		if ( !$result->numRows() ) {
			return;
		}

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_properties' )
			->where( [
				'pr_entity' => $electionId,
				'pr_key' => [ 'gpg-decrypt-key', 'delete-gpg-decrypt-key' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public static function checkEncryptKey( $key ) {
		if ( $key === '' ) {
			return Status::newFatal( 'htmlform-required' )->getMessage();
		}
		$that = new GpgCrypt( null, null );
		$status = $that->setupHome();
		if ( $status->isOK() ) {
			$status = $that->importKey( $key );
		}
		$that->cleanup();

		return $status->isOK() ? true : $status->getMessage();
	}

	public static function checkSignKey( $key ) {
		if ( !strval( $key ) ) {
			return true;
		}

		$that = new GpgCrypt( null, null );
		$status = $that->setupHome();
		if ( $status->isOK() ) {
			$status = $that->importKey( $key );
		}
		$that->cleanup();

		return $status->isOK() ? true : $status->getMessage();
	}

	/**
	 * Constructor.
	 * @param Context|null $context
	 * @param Election|null $election
	 */
	public function __construct( $context, $election ) {
		$this->context = $context;
		$this->election = $election;
	}

	/**
	 * Create a new GPG home directory
	 * @return Status
	 */
	public function setupHome() {
		global $wgSecurePollTempDir;
		if ( $this->homeDir ) {
			# Already done
			return Status::newGood();
		}

		# Create the directory
		$this->homeDir = $wgSecurePollTempDir . '/securepoll-' . MWCryptRand::generateHex( 40 );
		if ( !mkdir( $this->homeDir ) ) {
			$this->homeDir = null;

			return Status::newFatal( 'securepoll-no-gpg-home' );
		}
		chmod( $this->homeDir, 0700 );

		// T288366 Tallies fail on beta/prod with little visibility
		// Add logging to gain more context into where it fails
		$this->adHocDebug(
			'Created the temp directory for GPG decryption',
			[
				'tmpDir' => $this->homeDir,
			]
		);

		return Status::newGood();
	}

	/**
	 * Log the message and context to the AdHocDebug channel.
	 *
	 * @see https://phabricator.wikimedia.org/T288366
	 *
	 * @param string $message
	 * @param array $context
	 */
	private function adHocDebug( string $message, array $context = [] ) {
		if ( $this->election ) {
			$context += [
				'electionId' => $this->election->getId(),
			];
		}

		LoggerFactory::getInstance( 'AdHocDebug' )
			->info( $message, $context );
	}

	/**
	 * Create a new GPG home directory and import keys
	 * @return Status
	 */
	public function setupHomeAndKeys() {
		$status = $this->setupHome();
		if ( !$status->isOK() ) {
			return $status;
		}

		if ( $this->recipient ) {
			# Already done
			return Status::newGood();
		}

		# Fetch the keys
		$encryptKey = strval( $this->election->getProperty( 'gpg-encrypt-key' ) );
		if ( $encryptKey === '' ) {
			throw new InvalidDataException( 'GPG keys are configured incorrectly' );
		}

		# Import the encryption key
		$status = $this->importKey( $encryptKey );
		if ( !$status->isOK() ) {
			return $status;
		}
		$this->recipient = $status->value;

		# Import the sign key
		$signKey = strval( $this->election->getProperty( 'gpg-sign-key' ) );
		if ( $signKey ) {
			$status = $this->importKey( $signKey );
			if ( !$status->isOK() ) {
				return $status;
			}
			$this->signer = $status->value;
		} else {
			$this->signer = null;
		}

		return Status::newGood();
	}

	/**
	 * Import a given exported key.
	 * @param string $key The full key data.
	 * @return Status
	 */
	public function importKey( $key ) {
		# Import the key
		file_put_contents( "{$this->homeDir}/key", $key );
		$status = $this->runGpg( '--import', "{$this->homeDir}/key" );
		if ( !$status->isOK() ) {
			return $status;
		}
		# Extract the key ID
		if ( !preg_match( '/^gpg: key (\w+):/m', $status->value, $m ) ) {
			return Status::newFatal( 'securepoll-gpg-parse-error' );
		}

		// T288366 Tallies fail on beta/prod with little visibility
		// Add logging to gain more context into where it fails
		$this->adHocDebug(
			'Imported GPG decryption key',
			[
				'fileLocation' => "{$this->homeDir}/key",
			]
		);

		return Status::newGood( $m[1] );
	}

	/**
	 * @internal for use by classes that call GpgCrypt
	 * because cleanup has to happen after all decryptions
	 *
	 * Delete the temporary home directory
	 */
	public function cleanup() {
		if ( !$this->homeDir ) {
			return;
		}

		$this->deleteDir( $this->homeDir );
		$this->homeDir = null;
		$this->recipient = null;
	}

	private function deleteDir( $dirname ) {
		$dir = opendir( $dirname );
		if ( !$dir ) {
			return;
		}

		// @codingStandardsIgnoreStart
		while ( false !== ( $file = readdir( $dir ) ) ) {
			// @codingStandardsIgnoreEnd
			if ( $file == '.' || $file == '..' ) {
				continue;
			}
			if ( !is_dir( "$dirname/$file" ) ) {
				unlink( "$dirname/$file" );
			} else {
				$this->deleteDir( "$dirname/$file" );
			}
		}
		closedir( $dir );
		rmdir( $dirname );

		// T288366 Tallies fail on beta/prod with little visibility
		// Add logging to gain more context into where it fails
		$this->adHocDebug( 'Cleaned up GPG data after tally' );
	}

	/**
	 * Shell out to GPG with the given additional command-line parameters
	 * @param string ...$params
	 * @return Status
	 */
	protected function runGpg( ...$params ) {
		global $wgSecurePollGPGCommand, $wgSecurePollShowErrorDetail;

		$params = array_merge(
			[
				$wgSecurePollGPGCommand,
				'--homedir',
				$this->homeDir,
				'--trust-model',
				'always',
				'--batch',
				'--yes',
			],
			$params
		);
		$command = Shell::command( $params )->disableSandbox()->includeStderr();

		$result = $command->execute();

		if ( $result->getExitCode() ) {
			if ( $wgSecurePollShowErrorDetail ) {
				return Status::newFatal(
					'securepoll-full-gpg-error',
					(string)$command,
					$result->getStdout()
				);
			} else {
				return Status::newFatal( 'securepoll-secret-gpg-error' );
			}
		} else {
			return Status::newGood( $result->getStdout() );
		}
	}

	/**
	 * Encrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	public function encrypt( $record ) {
		$status = $this->setupHomeAndKeys();
		if ( !$status->isOK() ) {
			$this->cleanup();

			return $status;
		}

		# Write unencrypted record
		file_put_contents( "{$this->homeDir}/input", $record );

		# Call GPG
		$args = array_merge(
			[
				'--encrypt',
				'--armor',
				# Don't use compression, this may leak information about the plaintext
				'--compress-level',
				'0',
				'--recipient',
				$this->recipient,
			],
			$this->signer !== null ? [
				'--sign',
				'--local-user',
				$this->signer,
			] : [],
			[
				// Don't use --output due to T258763
				'-o',
				"{$this->homeDir}/output",
				"{$this->homeDir}/input",
			]
		);
		$status = $this->runGpg( ...$args );

		# Read result
		if ( $status->isOK() ) {
			$status->value = file_get_contents( "{$this->homeDir}/output" );
		}

		# Delete temporary files
		$this->cleanup();

		return $status;
	}

	/**
	 * Decrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $encrypted
	 * @return Status
	 */
	public function decrypt( $encrypted ) {
		$status = $this->setupHomeAndKeys();
		if ( !$status->isOK() ) {

			$this->cleanup();

			return $status;
		}

		# Import the decryption key
		$decryptKey = $this->context->decryptData[ 'gpg-decrypt-key' ] ??
			strval( $this->election->getProperty( 'gpg-decrypt-key' ) );
		if ( $decryptKey === '' ) {
			$this->cleanup();

			return Status::newFatal( 'securepoll-no-decryption-key' );
		}
		$this->importKey( $decryptKey );

		# Write out encrypted record
		file_put_contents( "{$this->homeDir}/input", $encrypted );

		# Call GPG
		$status = $this->runGpg(
			'--decrypt',
			// Don't use --output due to T258763
			'-o',
			"{$this->homeDir}/output",
			"{$this->homeDir}/input"
		);

		# Read result
		if ( $status->isOK() ) {
			// T288366 Tallies fail on beta/prod with little visibility
			// Add logging to gain more context into where it fails
			$this->adHocDebug( 'Successfully decrypted vote' );

			$status->value = file_get_contents( "{$this->homeDir}/output" );
		}

		return $status;
	}

	/**
	 * @return bool
	 */
	public function canDecrypt() {
		$decryptKey = strval( $this->election->getProperty( 'gpg-decrypt-key' ) );

		return $decryptKey !== '';
	}

	/**
	 * Update the given context with any information needed for tallying.
	 *
	 * This allows some information, e.g. private keys, to be used for a
	 * single request and not added to the database.
	 *
	 * @param Context $context
	 * @param array $data
	 */
	public function updateTallyContext( Context $context, array $data ): void {
	}
}
