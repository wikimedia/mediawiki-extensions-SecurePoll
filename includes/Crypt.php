<?php

/**
 * Cryptography module
 */
abstract class SecurePoll_Crypt {
	abstract function encrypt( $record );
	abstract function decrypt( $record );

	static function factory( $type, $election ) {
		if ( $type === 'gpg' ) {
			return new SecurePoll_GpgCrypt( $election );
		} else {
			return false;
		}
	}
}

class SecurePoll_GpgCrypt {
	var $election;
	var $recipient, $signer, $homeDir;

	function __construct( $election ) {
		$this->election = $election;
	}

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
	}

	function runGpg( $params ) {
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

	function encrypt( $record ) {
		$status = $this->setupHome();
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

	function decrypt( $encrypted ) {
		$status = $this->setupHome();
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
}
