<?php

namespace MediaWiki\Extensions\SecurePoll\Crypt;

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Shell\Shell;
use MWCryptRand;
use MWException;
use Status;

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
class GpgCrypt {
	public $context, $election;
	public $recipient, $signer, $homeDir;

	public static function getCreateDescriptors() {
		global $wgSecurePollGpgSignKey;

		$ret = Crypt::getCreateDescriptors();
		$ret['election'] += [
			'gpg-encrypt-key' => [
				'label-message' => 'securepoll-create-label-gpg_encrypt_key',
				'type' => 'textarea',
				'required' => true,
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

	public static function checkEncryptKey( $key ) {
		$that = new GpgCrypt( null, null );
		$status = $that->setupHome();
		if ( $status->isOK() ) {
			$status = $that->importKey( $key );
		}
		$that->deleteHome();

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
		$that->deleteHome();

		return $status->isOK() ? true : $status->getMessage();
	}

	/**
	 * Constructor.
	 * @param Context $context
	 * @param Election $election
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

		return Status::newGood();
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
			throw new MWException( 'GPG keys are configured incorrectly' );
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

		return Status::newGood( $m[1] );
	}

	/**
	 * Delete the temporary home directory
	 */
	public function deleteHome() {
		if ( !$this->homeDir ) {
			return;
		}
		$dir = opendir( $this->homeDir );
		if ( !$dir ) {
			return;
		}
		// @codingStandardsIgnoreStart
		while ( false !== ( $file = readdir( $dir ) ) ) {
			// @codingStandardsIgnoreEnd
			if ( $file == '.' || $file == '..' ) {
				continue;
			}
			unlink( "$this->homeDir/$file" );
		}
		closedir( $dir );
		rmdir( $this->homeDir );
		$this->homeDir = false;
		$this->recipient = false;
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
		$command = Shell::command( $params )->includeStderr();

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
			$this->deleteHome();

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
		$this->deleteHome();

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
			$this->deleteHome();

			return $status;
		}

		# Import the decryption key
		$decryptKey = strval( $this->election->getProperty( 'gpg-decrypt-key' ) );
		if ( $decryptKey === '' ) {
			$this->deleteHome();

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
			$status->value = file_get_contents( "{$this->homeDir}/output" );
		}

		# Delete temporary files
		$this->deleteHome();

		return $status;
	}

	/**
	 * @return bool
	 */
	public function canDecrypt() {
		$decryptKey = strval( $this->election->getProperty( 'gpg-decrypt-key' ) );

		return $decryptKey !== '';
	}

}
