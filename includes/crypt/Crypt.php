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
	abstract public function encrypt( $record );

	/**
	 * Decrypt some data. When successful, the value member of the Status object
	 * will contain the encrypted record.
	 * @param string $record
	 * @return Status
	 */
	abstract public function decrypt( $record );

	/**
	 * Returns true if the object can decrypt data, false otherwise.
	 */
	abstract public function canDecrypt();

	public static $cryptTypes = [
		'none' => false,
		'gpg' => 'SecurePoll_GpgCrypt',
	];

	/**
	 * Create an encryption object of the given type. Currently only "gpg" is
	 * implemented.
	 * @param SecurePoll_Context $context
	 * @param string $type
	 * @param SecurePoll_Election $election
	 * @return bool|SecurePoll_GpgCrypt
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
}
