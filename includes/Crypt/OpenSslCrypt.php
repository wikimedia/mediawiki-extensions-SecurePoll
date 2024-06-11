<?php

namespace MediaWiki\Extension\SecurePoll\Crypt;

use JsonException;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

/**
 * Cryptography module that uses the PHP openssl extension.
 * At the moment, only RSA keys are supported, with a minimum size of 2048 bits.
 *
 * Election properties used:
 *     openssl-encrypt-key:  The public key used for encrypting.
 *     openssl-sign-key:     The private key used for signing.
 *     openssl-decrypt-key:  The private key used for decrypting.
 *     openssl-verify-key:   The public ky used for verification.
 *
 * Generally only openssl-encrypt-key and openssl-sign-key are required for voting,
 * openssl-decrypt-key and openssl-verify-key are for tallying.
 */
class OpenSslCrypt extends Crypt {
	private const CLAIM_TYPE_TOKEN_TYPE = 'typ';
	private const CLAIM_TYPE_SIGNATURE_ALGORITHM = 'alg';
	private const CLAIM_TYPE_ISSUER = 'iss';
	private const CLAIM_TYPE_SUBJECT = 'sub';
	private const CLAIM_TYPE_VOTE = 'mw-ext-sp-vot';
	private const CLAIM_TYPE_ENCRYPT_ALGORITHM = 'mw-ext-sp-alg';
	private const CLAIM_TYPE_ENVELOPE_KEY = 'mw-ext-sp-env';
	private const CLAIM_TYPE_TAG = 'mw-ext-sp-tag';
	private const CLAIM_TYPE_IV = 'mw-ext-sp-iv';
	private const CLAIM_TYPE_MAC = 'mw-ext-sp-mac';

	/** @var Context|null */
	private $context;

	/** @var Election|null */
	private $election;

	/** @var OpenSSLAsymmetricKey|resource|null */
	private $encryptKey = null;

	/** @var OpenSSLAsymmetricKey|resource|null */
	private $signKey = null;

	/** @var OpenSSLAsymmetricKey|resource|null */
	private $decryptKey = null;

	/** @var OpenSSLAsymmetricKey|resource|null */
	private $verifyKey = null;

	/**
	 * Constructor.
	 * @param Context|null $context
	 * @param Election|null $election
	 */
	public function __construct( $context, $election ) {
		if ( !extension_loaded( 'openssl' ) ) {
			throw new RuntimeException( 'The openssl extension must be enabled in php.ini to use this class' );
		}

		$this->context = $context;
		$this->election = $election;
	}

	/**
	 * Encrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	public function encrypt( $record ) {
		$status = $this->setupKeys();
		if ( !$status->isOK() ) {
			$this->cleanup();
			return $status;
		}

		if ( $this->encryptKey === null || $this->signKey === null ) {
			return Status::newFatal( 'securepoll-openssl-invalid-key' );
		}

		$cipherAlg = 'aes-256-gcm';
		$keyLength = 32;
		$signAlg = 'RS256';
		$hashAlg = 'sha256';

		$this->clearErrors();
		$ivLength = openssl_cipher_iv_length( $cipherAlg );
		if ( $ivLength === false ) {
			return $this->getErrorStatus( 'openssl_cipher_iv_length' );
		}

		$iv = random_bytes( $ivLength );
		$tag = '';
		$secretKey = random_bytes( $keyLength );

		// authenticate vote metadata with our secret key, as otherwise in scenarios
		// where the signing key is compromised or doesn't exist, the metadata could be forged/changed
		$issuedBy = self::getIssuer();
		$subject = (string)$this->election->getId();
		$aad = $issuedBy . '|' . $subject;

		$this->clearErrors();
		$ciphertext = openssl_encrypt(
			$record,
			$cipherAlg,
			$secretKey,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$aad,
			16
		);

		if ( $ciphertext === false ) {
			return $this->getErrorStatus( 'openssl_encrypt' );
		}

		$this->clearErrors();
		$result = openssl_public_encrypt(
			$secretKey,
			$envelopeKey,
			$this->encryptKey,
			OPENSSL_PKCS1_OAEP_PADDING
		);

		if ( !$result ) {
			return $this->getErrorStatus( 'openssl_public_encrypt' );
		}

		$header = [
			self::CLAIM_TYPE_SIGNATURE_ALGORITHM => $signAlg,
			self::CLAIM_TYPE_TOKEN_TYPE => 'JWT'
		];

		$claims = [
			self::CLAIM_TYPE_ISSUER => $issuedBy,
			self::CLAIM_TYPE_SUBJECT => $subject,
			self::CLAIM_TYPE_VOTE => base64_encode( $ciphertext ),
			self::CLAIM_TYPE_ENCRYPT_ALGORITHM => $cipherAlg,
			self::CLAIM_TYPE_ENVELOPE_KEY => base64_encode( $envelopeKey ),
			self::CLAIM_TYPE_TAG => base64_encode( $tag ),
			self::CLAIM_TYPE_IV => base64_encode( $iv ),
			self::CLAIM_TYPE_MAC => hash_hmac( 'sha256', $envelopeKey, $secretKey ),
		];

		$jwt = $this->jwtEncode( $header ) . '.' . $this->jwtEncode( $claims );
		$this->clearErrors();
		$result = openssl_sign( $jwt, $sig, $this->signKey, $hashAlg );
		if ( !$result ) {
			return $this->getErrorStatus( 'openssl_sign' );
		}

		$jwt .= '.' . $this->jwtEncode( $sig );

		return Status::newGood( $jwt );
	}

	/**
	 * Decrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 *
	 * This may be run in an offline scenario with no access to the public encryption
	 * key or private signing key. This method requires the private decryption key and
	 * public verification key to be supplied via the decryption data.
	 *
	 * @param string $record
	 * @return Status
	 */
	public function decrypt( $record ) {
		$status = $this->setupKeys();
		if ( !$status->isOK() ) {
			$this->cleanup();
			return $status;
		}

		if ( $this->decryptKey === null ) {
			return Status::newFatal( 'securepoll-no-decryption-key' );
		}

		if ( $this->verifyKey === null ) {
			return Status::newFatal( 'securepoll-no-verification-key' );
		}

		// $record may contain a leading line break in dump-based tallying, so trim it out before verifying the JWT
		$parts = explode( '.', trim( $record ) );
		if ( count( $parts ) !== 3 ) {
			return $this->getErrorStatus( 'verify_jwt', 'jwt does not contain exactly 3 parts' );
		}

		try {
			$header = $this->jwtDecode( $parts[0] );
			$claims = $this->jwtDecode( $parts[1] );
			$sig = $this->jwtDecode( $parts[2], false );
		} catch ( JsonException $e ) {
			return $this->getErrorStatus( 'verify_jwt', $e->getMessage() );
		}

		if ( $header[self::CLAIM_TYPE_SIGNATURE_ALGORITHM] === 'RS256' ) {
			$hashAlg = 'sha256';
		} else {
			return $this->getErrorStatus( 'verify_jwt', 'jwt header alg is not RS256' );
		}

		$requiredClaims = [
			self::CLAIM_TYPE_ISSUER,
			self::CLAIM_TYPE_SUBJECT,
			self::CLAIM_TYPE_VOTE,
			self::CLAIM_TYPE_ENCRYPT_ALGORITHM,
			self::CLAIM_TYPE_ENVELOPE_KEY,
			self::CLAIM_TYPE_TAG,
			self::CLAIM_TYPE_IV,
			self::CLAIM_TYPE_MAC,
		];
		foreach ( $requiredClaims as $claim ) {
			if ( !isset( $claims[$claim] ) || !is_string( $claims[$claim] ) || $claims[$claim] === '' ) {
				return $this->getErrorStatus( 'verify_jwt', "jwt missing claim $claim" );
			}
		}

		$data = $parts[0] . '.' . $parts[1];
		$this->clearErrors();
		$result = openssl_verify( $data, $sig, $this->verifyKey, $hashAlg );
		if ( $result === false || $result === -1 ) {
			return $this->getErrorStatus( 'openssl_verify' );
		} elseif ( $result === 0 ) {
			return $this->getErrorStatus( 'verify_jwt', 'invalid signature' );
		}

		if ( $claims[self::CLAIM_TYPE_ISSUER] !== self::getIssuer()
			|| $claims[self::CLAIM_TYPE_SUBJECT] !== (string)$this->election->getId()
		) {
			return $this->getErrorStatus( 'verify_jwt', 'jwt is not for the current election' );
		}

		if ( !in_array( $claims[self::CLAIM_TYPE_ENCRYPT_ALGORITHM], openssl_get_cipher_methods() ) ) {
			return $this->getErrorStatus( 'decrypt_vote', 'vote encryption algorithm not supported' );
		}

		$decoded = [
			self::CLAIM_TYPE_VOTE => false,
			self::CLAIM_TYPE_ENVELOPE_KEY => false,
			self::CLAIM_TYPE_IV => false,
			self::CLAIM_TYPE_TAG => false
		];
		'@phan-var array<string,string|false> $decoded';

		foreach ( $decoded as $claim => &$value ) {
			$value = base64_decode( $claims[$claim], true );
			if ( $value === false ) {
				return $this->getErrorStatus( 'decrypt_vote', "invalid base64 in $claim" );
			}
		}

		$this->clearErrors();
		$result = openssl_private_decrypt(
			$decoded[self::CLAIM_TYPE_ENVELOPE_KEY],
			$secretKey,
			$this->decryptKey,
			OPENSSL_PKCS1_OAEP_PADDING
		);

		if ( !$result ) {
			return $this->getErrorStatus( 'openssl_private_decrypt' );
		}

		$mac = hash_hmac( 'sha256', $decoded[self::CLAIM_TYPE_ENVELOPE_KEY], $secretKey );
		if ( !hash_equals( $claims[self::CLAIM_TYPE_MAC], $mac ) ) {
			$this->cleanup();
			return Status::newFatal( 'securepoll-wrong-decryption-key' );
		}

		$aad = $claims['iss'] . '|' . $claims['sub'];
		$this->clearErrors();
		$vote = openssl_decrypt(
			$decoded[self::CLAIM_TYPE_VOTE],
			$claims[self::CLAIM_TYPE_ENCRYPT_ALGORITHM],
			$secretKey,
			OPENSSL_RAW_DATA,
			$decoded[self::CLAIM_TYPE_IV],
			$decoded[self::CLAIM_TYPE_TAG],
			$aad
		);

		if ( $vote === false ) {
			return $this->getErrorStatus( 'openssl_decrypt' );
		}

		return Status::newGood( $vote );
	}

	/**
	 * @param string $prefix Prefix for error message
	 * @param ?string $error Error message, or null to use openssl_error_string()
	 * @return Status
	 */
	private function getErrorStatus( string $prefix, ?string $error = null ): Status {
		global $wgSecurePollShowErrorDetail;

		$error ??= openssl_error_string();
		$this->cleanup();
		wfDebug( "$prefix:$error" );

		if ( $wgSecurePollShowErrorDetail ) {
			return Status::newFatal( 'securepoll-full-openssl-error', "$prefix:$error" );
		} else {
			return Status::newFatal( 'securepoll-secret-openssl-error' );
		}
	}

	/**
	 * Load our keys from the election store.
	 * Keys that do not exist in the store will remain null, however if a private key is supplied
	 * but not its corresponding public key, we will derive it.
	 *
	 * @return Status
	 */
	private function setupKeys(): Status {
		if ( $this->decryptKey === null ) {
			$decryptKey = $this->context->decryptData['openssl-decrypt-key'] ??
				strval( $this->election->getProperty( 'openssl-decrypt-key' ) );
			if ( $decryptKey !== '' ) {
				$this->clearErrors();
				$result = openssl_pkey_get_private( $decryptKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_private' );
				}

				$this->decryptKey = $result;
			}
		}

		if ( $this->encryptKey === null ) {
			$encryptKey = strval( $this->election->getProperty( 'openssl-encrypt-key' ) );
			if ( $encryptKey === '' && $this->decryptKey !== null ) {
				$this->clearErrors();
				$result = openssl_pkey_get_details( $this->decryptKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_details' );
				}

				$encryptKey = $result['key'];
			}

			if ( $encryptKey !== '' ) {
				$this->clearErrors();
				$result = openssl_pkey_get_public( $encryptKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_public' );
				}

				$this->encryptKey = $result;

				$this->clearErrors();
				$keyDetails = openssl_pkey_get_details( $this->encryptKey );
				if ( $keyDetails === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_details' );
				}

				if ( $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA ) {
					return $this->getErrorStatus( 'setup_keys', 'encryption key is not RSA' );
				}

				if ( $keyDetails['bits'] < 2048 ) {
					return $this->getErrorStatus( 'setup_keys', 'encryption key is weaker than 2048 bits' );
				}
			}
		}

		if ( $this->signKey === null ) {
			$signKey = strval( $this->election->getProperty( 'openssl-sign-key' ) );
			if ( $signKey !== '' ) {
				$this->clearErrors();
				$result = openssl_pkey_get_private( $signKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_private' );
				}

				$this->signKey = $result;

				$this->clearErrors();
				$keyDetails = openssl_pkey_get_details( $this->signKey );
				if ( $keyDetails === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_details' );
				}

				if ( $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA ) {
					return $this->getErrorStatus( 'setup_keys', 'signing key is not RSA' );
				}

				if ( $keyDetails['bits'] < 2048 ) {
					return $this->getErrorStatus( 'setup_keys', 'signing key is weaker than 2048 bits' );
				}
			}
		}

		if ( $this->verifyKey === null ) {
			$verifyKey = $this->context->decryptData['openssl-verify-key'] ??
				strval( $this->election->getProperty( 'openssl-verify-key' ) );
			if ( $verifyKey === '' && $this->signKey !== null ) {
				$this->clearErrors();
				$result = openssl_pkey_get_details( $this->signKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_details' );
				}

				$verifyKey = $result['key'];
			}

			if ( $verifyKey !== '' ) {
				$this->clearErrors();
				$result = openssl_pkey_get_public( $verifyKey );
				if ( $result === false ) {
					return $this->getErrorStatus( 'openssl_pkey_get_public' );
				}

				$this->verifyKey = $result;
			}
		}

		return Status::newGood();
	}

	/**
	 * Clears the openssl error string queue.
	 * Some successful openssl operations still append to this queue, so it must be
	 * cleared before running operations which may fail and which we want failure details for.
	 *
	 * @return void
	 */
	private function clearErrors(): void {
		while ( true ) {
			$error = openssl_error_string();
			if ( !$error ) {
				break;
			}
		}
	}

	/**
	 * Encode data using base64 url-safe variant
	 *
	 * @param array|string $data
	 * @return string
	 */
	private function jwtEncode( $data ): string {
		if ( is_array( $data ) ) {
			$data = json_encode( $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode data using base64 url-safe variant
	 *
	 * @param string $data
	 * @param bool $parseJson If true, parse result as JSON
	 * @return array|string Array if $parseJson is true, string otherwise
	 */
	private function jwtDecode( string $data, $parseJson = true ) {
		$data = base64_decode( strtr( $data, '-_', '+/' ) );

		if ( $parseJson ) {
			return json_decode( $data, true, 4, JSON_THROW_ON_ERROR );
		} else {
			return $data;
		}
	}

	public function cleanup() {
		$this->encryptKey = null;
		$this->signKey = null;
		$this->decryptKey = null;
		$this->verifyKey = null;
		$this->clearErrors();
	}

	public function canDecrypt() {
		$decryptKey = strval( $this->election->getProperty( 'openssl-decrypt-key' ) );

		return $decryptKey !== '';
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
	public static function getCreateDescriptors() {
		global $wgSecurePollOpenSslSignKey;

		$ret = parent::getCreateDescriptors();

		$ret['election'] += [
			'openssl-encrypt-key' => [
				'label-message' => 'securepoll-create-label-openssl_encrypt_key',
				'type' => 'textarea',
				'SecurePoll_type' => 'property',
				'rows' => 5,
				'validation-callback' => static function ( string $key ) {
					return self::checkPublicKey( $key );
				},
			]
		];

		if ( $wgSecurePollOpenSslSignKey ) {
			$ret['election'] += [
				'openssl-sign-key' => [
					'type' => 'api',
					'default' => $wgSecurePollOpenSslSignKey,
					'SecurePoll_type' => 'property',
				]
			];
		} else {
			$ret['election'] += [
				'openssl-sign-key' => [
					'label-message' => 'securepoll-create-label-openssl_sign_key',
					'type' => 'textarea',
					'SecurePoll_type' => 'property',
					'rows' => 5,
					'validation-callback' => static function ( string $key ) {
						return self::checkPrivateKey( $key );
					},
				]
			];
		}

		return $ret;
	}

	public function getTallyDescriptors(): array {
		$verifyKeyRequired = strval( $this->election->getProperty( 'openssl-sign-key' ) ) === '';

		return [
			'openssl-decrypt-key' => [
				'label-message' => 'securepoll-tally-openssl-decrypt-key',
				'type' => 'textarea',
				'required' => true,
				'rows' => 5,
				'validation-callback' => static function ( string $key ) {
					return self::checkPrivateKey( $key );
				},
			],
			'openssl-verify-key' => [
				'label-message' => 'securepoll-tally-openssl-verify-key',
				'type' => 'textarea',
				'required' => $verifyKeyRequired,
				'rows' => 5,
				'validation-callback' => static function ( string $key ) use ( $verifyKeyRequired ) {
					return self::checkPublicKey( $key, $verifyKeyRequired );
				},
			],
		];
	}

	/**
	 * Check validity of an encryption key
	 *
	 * @param string $key
	 * @param bool $required If the key is required to be provided
	 * @return Message|true
	 */
	private static function checkPublicKey( string $key, bool $required = true ) {
		if ( $key === '' ) {
			if ( $required ) {
				return Status::newFatal( 'htmlform-required' )->getMessage();
			} else {
				// not required so we're fine with this being empty
				return true;
			}
		}

		return self::checkKeyInternal( openssl_pkey_get_public( $key ) );
	}

	/**
	 * Check validity of a decryption or signing key
	 * @param string $key
	 * @return Message|true
	 */
	public static function checkPrivateKey( string $key ) {
		if ( $key === '' ) {
			return Status::newFatal( 'htmlform-required' )->getMessage();
		}

		return self::checkKeyInternal( openssl_pkey_get_private( $key ) );
	}

	/**
	 * Internal validation routine for public or private keys
	 *
	 * @param OpenSSLAsymmetricKey|resource|false $key
	 * @return Message|true
	 */
	private static function checkKeyInternal( $key ) {
		if ( $key === false ) {
			return Status::newFatal( 'securepoll-openssl-invalid-key' )->getMessage();
		}

		$result = openssl_pkey_get_details( $key );
		if ( $result === false || $result['type'] !== OPENSSL_KEYTYPE_RSA || $result['bits'] < 2048 ) {
			return Status::newFatal( 'securepoll-openssl-invalid-key' )->getMessage();
		}

		return true;
	}

	public function updateTallyContext( Context $context, array $data ): void {
		// no-op
	}

	public function updateDbForTallyJob( int $electionId, IDatabase $dbw, array $data ): void {
		// Add private key to DB if it was entered in the form
		if ( isset( $data['openssl-decrypt-key'] ) ) {
			$dbw->newReplaceQueryBuilder()
				->replaceInto( 'securepoll_properties' )
				->uniqueIndexFields( [ 'pr_entity', 'pr_key' ] )
				->row( [
					'pr_entity' => $electionId,
					'pr_key' => 'openssl-decrypt-key',
					'pr_value' => $data['openssl-decrypt-key']
				] )
				->caller( __METHOD__ )->execute();
		}
	}

	public function cleanupDbForTallyJob( int $electionId, IDatabase $dbw ): void {
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'securepoll_properties' )
			->where( [
				'pr_entity' => $electionId,
				'pr_key' => 'openssl-decrypt-key',
			] )
			->caller( __METHOD__ )->execute();
	}

	/**
	 * Retrieve the URL of Special:SecurePoll, for validating JWTs.
	 *
	 * @return string
	 */
	private static function getIssuer(): string {
		return SpecialPage::getTitleFor( 'SecurePoll' )->getCanonicalURL();
	}
}
