<?php

/**
 * Cryptography module
 */
abstract class SecurePoll_Crypt {
	/**
	 * Encrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	abstract function encrypt( $record );

	/**
	 * Decrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	abstract function decrypt( $record );

	/**
	 * Returns true if the object can decrypt data, false otherwise.
	 */
	abstract function canDecrypt();

	static $cryptTypes = array(
		'none' => false,
		'gpg' => 'SecurePoll_GpgCrypt',
	);

	/**
	 * Create an encryption object of the given type. Currently only "gpg" is
	 * implemented.
	 */
	static function factory( $context, $type, $election ) {
		if ( !isset( self::$cryptTypes[$type] ) ) {
			throw new MWException( "Invalid crypt type: $type" );
		}
		$class = self::$cryptTypes[$type];
		return $class ? new $class( $context, $election ) : false;
	}

	/**
	 * Return descriptors for any properties this type requires for poll
	 * creation, for the election, questions, and options.
	 *
	 * The returned array should have three keys, "election", "question", and
	 * "option", each mapping to an array of HTMLForm descriptors.
	 *
	 * The descriptors should have an additional key, "SecurePoll_type", with
	 * the value being "property" or "message".
	 *
	 * @return array
	 */
	static function getCreateDescriptors() {
		return array(
			'election' => array(),
			'question' => array(),
			'option' => array(),
		);
	}
}

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
class SecurePoll_GpgCrypt {
	public $context, $election;
	public $recipient, $signer, $homeDir;

	static function getCreateDescriptors() {
		global $wgSecurePollGpgSignKey;

		$ret = SecurePoll_Crypt::getCreateDescriptors();
		$ret['election'] += array(
			'gpg-encrypt-key' => array(
				'label-message' => 'securepoll-create-label-gpg_encrypt_key',
				'type' => 'textarea',
				'required' => true,
				'SecurePoll_type' => 'property',
				'rows' => 5,
				'validation-callback' => 'SecurePoll_GpgCrypt::checkEncryptKey',
			),
		);

		if ( $wgSecurePollGpgSignKey ) {
			$ret['election'] += array(
				'gpg-sign-key' => array(
					'type' => 'api',
					'default' => $wgSecurePollGpgSignKey,
					'SecurePoll_type' => 'property',
				),
			);
		} else {
			$ret['election'] += array(
				'gpg-sign-key' => array(
					'label-message' => 'securepoll-create-label-gpg_sign_key',
					'type' => 'textarea',
					'SecurePoll_type' => 'property',
					'rows' => 5,
					'validation-callback' => 'SecurePoll_GpgCrypt::checkSignKey',
				),
			);
		}

		return $ret;
	}

	public static function checkEncryptKey( $key ) {
		$that = new SecurePoll_GpgCrypt( null, null );
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

		$that = new SecurePoll_GpgCrypt( null, null );
		$status = $that->setupHome();
		if ( $status->isOK() ) {
			$status = $that->importKey( $key );
		}
		$that->deleteHome();
		return $status->isOK() ? true : $status->getMessage();
	}

	/**
	 * Constructor.
	 * @param $context
	 * @param $election SecurePoll_Election
	 */
	function __construct( $context, $election ) {
		$this->context = $context;
		$this->election = $election;
	}

	/**
	 * Create a new GPG home directory
	 * @return Status
	 */
	function setupHome() {
		global $wgSecurePollTempDir;
		if ( $this->homeDir ) {
			# Already done
			return Status::newGood();
		}

		# Create the directory
		$this->homeDir = $wgSecurePollTempDir . '/securepoll-' . sha1( mt_rand() . mt_rand() );
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
	function setupHomeAndKeys() {
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
	 * @param $key string The full key data.
	 * @return Status
	 */
	function importKey( $key ) {
		# Import the key
		file_put_contents( "{$this->homeDir}/key", $key );
		$status = $this->runGpg( ' --import ' . wfEscapeShellArg( "{$this->homeDir}/key" ) );
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
	function deleteHome() {
		if ( !$this->homeDir ) {
			return;
		}
		$dir = opendir( $this->homeDir );
		if ( !$dir ) {
			return;
		}
		while ( false !== ( $file = readdir( $dir ) ) ) {
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
	 * @param $params string
	 * @return Status
	 */
	protected function runGpg( $params ) {
		global $wgSecurePollGPGCommand, $wgSecurePollShowErrorDetail;
		$ret = 1;
		$command = wfEscapeShellArg( $wgSecurePollGPGCommand ) .
			' --homedir ' . wfEscapeShellArg( $this->homeDir ) . ' --trust-model always --batch --yes ' .
			$params .
			' 2>&1';
		$output = wfShellExec( $command, $ret );
		if ( $ret ) {
			if ( $wgSecurePollShowErrorDetail ) {
				return Status::newFatal( 'securepoll-full-gpg-error', $command, $output );
			} else {
				return Status::newFatal( 'securepoll-secret-gpg-error' );
			}
		} else {
			return Status::newGood( $output );
		}
	}

	/**
	 * Encrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	function encrypt( $record ) {
		$status = $this->setupHomeAndKeys();
		if ( !$status->isOK() ) {
			$this->deleteHome();
			return $status;
		}

		# Write unencrypted record
		file_put_contents( "{$this->homeDir}/input", $record );

		# Call GPG
		$args = '--encrypt --armor' .
			# Don't use compression, this may leak information about the plaintext
			' --compress-level 0' .
			' --recipient ' . wfEscapeShellArg( $this->recipient );
		if ( $this->signer !== null ) {
			$args .= ' --sign --local-user ' . $this->signer;
		}
		$args .= ' --output ' . wfEscapeShellArg( "{$this->homeDir}/output" ) .
			' ' . wfEscapeShellArg( "{$this->homeDir}/input" ) . ' 2>&1';
		$status = $this->runGpg( $args );

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
	 * @param $encrypted string
	 * @return Status
	 */
	function decrypt( $encrypted ) {
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
		$args = '--decrypt' .
			' --output ' . wfEscapeShellArg( "{$this->homeDir}/output" ) .
			' ' . wfEscapeShellArg( "{$this->homeDir}/input" ) . ' 2>&1';
		$status = $this->runGpg( $args );

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
	function canDecrypt() {
		$decryptKey = strval( $this->election->getProperty( 'gpg-decrypt-key' ) );
		return $decryptKey !== '';
	}

}
