<?php

namespace MediaWiki\Extension\SecurePoll\Crypt;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Shell\Shell;
use MediaWiki\Status\Status;
use Wikimedia\Rdbms\IDatabase;

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

	/**
	 * Returns a list of supported Crypt subclasses for encrypting votes.
	 *
	 * @return (class-string|false)[]
	 */
	public static function getCryptTypes() {
		$cryptTypes = [
			'none' => false
		];

		if ( !Shell::isDisabled() ) {
			$cryptTypes['gpg'] = GpgCrypt::class;
		}

		if ( extension_loaded( 'openssl' ) ) {
			$cryptTypes['openssl'] = OpenSslCrypt::class;
		}

		return $cryptTypes;
	}

	/**
	 * Create an encryption object of the given type.
	 * @param Context $context
	 * @param string $type
	 * @param Election $election
	 * @return self|false False when encryption type is set to "none"
	 */
	public static function factory( $context, $type, $election ) {
		$cryptTypes = self::getCryptTypes();

		if ( !isset( $cryptTypes[$type] ) ) {
			throw new InvalidArgumentException( "Invalid crypt type: $type" );
		}
		$class = $cryptTypes[$type];

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
	abstract public function getTallyDescriptors(): array;

	/**
	 * Update the given context with any information needed for tallying.
	 *
	 * This allows some information, e.g. private keys, to be used for a
	 * single request and not added to the database.
	 *
	 * @param Context $context
	 * @param array $data
	 */
	abstract public function updateTallyContext( Context $context, array $data ): void;

	/**
	 * Update the database with any information needed for tallying via a job.
	 *
	 * This may include adding private keys to the database. While not ideal,
	 * this is nothing new in SecurePoll. It should only be done once an
	 * election is finished, and should be cleaned up.
	 *
	 * @see Crypt::cleanupDbForTallyJob
	 * @param int $electionId
	 * @param IDatabase $dbw
	 * @param array $data
	 */
	abstract public function updateDbForTallyJob(
		int $electionId,
		IDatabase $dbw,
		array $data
	): void;

	/**
	 * Clean up the database after tallying via a job.
	 *
	 * @see Crypt::updateDbForTallyJob
	 * @param int $electionId
	 * @param IDatabase $dbw
	 */
	abstract public function cleanupDbForTallyJob( int $electionId, IDatabase $dbw ): void;
}
