<?php

namespace MediaWiki\Extensions\SecurePoll\Crypt;

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MWException;
use Status;

/**
 * Cryptography module
 */
abstract class Crypt {
	/**
	 * Encrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	abstract public function encrypt( $record );

	/**
	 * Decrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	abstract public function decrypt( $record );

	/**
	 * @internal Generic clean up function. Internal functions can call this to clean up
	 * after themselves or callers can manually clean up after processing.
	 * Ideally, functions would be self-contained but due to performance
	 * constraints, this is not always possible. In those cases, the caller
	 * should be responsible for cleanup.
	 */
	abstract public function cleanup();

	/**
	 * Returns true if the object can decrypt data, false otherwise.
	 */
	abstract public function canDecrypt();

	/** @var (string|false)[] */
	public static $cryptTypes = [
		'none' => false,
		'gpg' => GpgCrypt::class,
	];

	/**
	 * Create an encryption object of the given type. Currently only "gpg" is
	 * implemented.
	 * @param Context $context
	 * @param string $type
	 * @param Election $election
	 * @return bool|GpgCrypt
	 */
	public static function factory( $context, $type, $election ) {
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
	public static function getCreateDescriptors() {
		return [
			'election' => [],
			'question' => [],
			'option' => [],
		];
	}

	/**
	 * Return descriptors for any properties this type requires for poll
	 * tallying.
	 *
	 * @return array
	 */
	public static function getTallyDescriptors(): array {
		return [];
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
	public static function updateTallyContext( Context $context, array $data ): void {
	}
}
