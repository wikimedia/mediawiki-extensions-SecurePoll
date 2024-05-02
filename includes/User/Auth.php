<?php

namespace MediaWiki\Extension\SecurePoll\User;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;

/**
 * Class for handling guest logins and sessions. Creates Voter objects.
 */
class Auth {
	/** @var Context */
	public $context;

	/**
	 * @var string[] List of available authorization modules (subclasses)
	 */
	private static $authTypes = [
		'local' => LocalAuth::class,
		'remote-mw' => RemoteMWAuth::class,
	];

	/**
	 * Create an auth object of the given type
	 * @param Context $context
	 * @param string $type
	 * @return LocalAuth|RemoteMWAuth
	 * @throws InvalidArgumentException
	 */
	public static function factory( $context, $type ) {
		if ( !isset( self::$authTypes[$type] ) ) {
			throw new InvalidArgumentException( "Invalid authentication type: $type" );
		}
		$class = self::$authTypes[$type];

		return new $class( $context );
	}

	/**
	 * Return descriptors for any additional properties or messages this type
	 * requires for poll creation.
	 *
	 * The descriptors should have an additional key, "SecurePoll_type", with
	 * the value being "property" or "message".
	 *
	 * @return array
	 */
	public static function getCreateDescriptors() {
		return [];
	}

	/**
	 * @param Context $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * Create a voter transparently, without user interaction.
	 * Sessions authorized against local accounts are created this way.
	 * @param Election $election
	 * @return Status
	 */
	public function autoLogin( $election ) {
		return Status::newFatal( 'securepoll-not-logged-in' );
	}

	/**
	 * Create a voter on a direct request from a remote site.
	 * @param Election $election
	 * @return Status
	 */
	public function requestLogin( $election ) {
		return $this->autoLogin( $election );
	}

	/**
	 * Get the voter associated with the current session. Returns false if
	 * there is no session.
	 * @param Election $election
	 * @return Voter|bool
	 */
	public function getVoterFromSession( $election ) {
		$session = SessionManager::getGlobalSession();
		$session->persist();
		if ( isset( $session['securepoll_voter'][$election->getId()] ) ) {
			$voterId = $session['securepoll_voter'][$election->getId()];

			# Perform cookie fraud check
			$status = $this->autoLogin( $election );
			if ( $status->isOK() ) {
				$otherVoter = $status->value;
				'@phan-var Voter $otherVoter'; /** @var Voter $otherVoter */
				if ( $otherVoter->getId() != $voterId ) {
					$otherVoter->addCookieDup( $voterId );
					$session['securepoll_voter'][$election->getId()] = $otherVoter->getId();

					return $otherVoter;
				}
			}

			# Check election ID explicitly on DB_PRIMARY
			$voter = $this->context->getVoter( $voterId, DB_PRIMARY );
			if ( !$voter || $voter->getElectionId() != $election->getId() ) {
				return false;
			} else {
				return $voter;
			}
		} else {
			return false;
		}
	}

	/**
	 * Get a voter object with the relevant parameters.
	 * If no voter exists with those parameters, a new one is created. If one
	 * does exist already, it is returned.
	 * @param array $params
	 * @return Voter
	 */
	public function getVoter( $params ) {
		$dbw = $this->context->getDB();

		# This needs to be protected by FOR UPDATE
		# Otherwise a race condition could lead to duplicate users for a single remote user,
		# and thus to duplicate votes.
		$dbw->startAtomic( __METHOD__ );
		$row = $dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'securepoll_voters' )
			->where( [
				'voter_name' => $params['name'],
				'voter_election' => $params['electionId'],
				'voter_domain' => $params['domain'],
				'voter_url' => $params['url']
			] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			$user = $this->context->newVoterFromRow( $row );
		} else {
			$user = $this->context->createVoter( $params );
		}
		$dbw->endAtomic( __METHOD__ );

		return $user;
	}

	/**
	 * Create a voter without user interaction, and create a session for it.
	 * @param Election $election
	 * @return Status
	 */
	public function newAutoSession( $election ) {
		$status = $this->autoLogin( $election );
		if ( $status->isGood() ) {
			$voter = $status->value;
			'@phan-var Voter $voter'; /** @var Voter $voter */
			$session = SessionManager::getGlobalSession();
			$session['securepoll_voter'][$election->getId()] = $voter->getId();
			$voter->doCookieCheck();
		}

		return $status;
	}

	/**
	 * Create a voter on an explicit request, and create a session for it.
	 * @param Election $election
	 * @return Status
	 */
	public function newRequestedSession( $election ) {
		$session = SessionManager::getGlobalSession();
		$session->persist();

		$status = $this->requestLogin( $election );
		if ( !$status->isOK() ) {
			return $status;
		}

		# Do cookie dup flagging
		$voter = $status->value;
		'@phan-var Voter $voter'; /** @var Voter $voter */
		if ( isset( $session['securepoll_voter'][$election->getId()] ) ) {
			$otherVoterId = $session['securepoll_voter'][$election->getId()];
			if ( $voter->getId() != $otherVoterId ) {
				$voter->addCookieDup( $otherVoterId );
			}
		} else {
			$voter->doCookieCheck();
		}

		$session['securepoll_voter'][$election->getId()] = $voter->getId();

		return $status;
	}
}
