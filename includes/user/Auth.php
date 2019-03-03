<?php

use MediaWiki\Session\SessionManager;

/**
 * Class for handling guest logins and sessions. Creates SecurePoll_Voter objects.
 */
class SecurePoll_Auth {
	/** @var SecurePoll_Context */
	public $context;

	/**
	 * List of available authorization modules (subclasses)
	 */
	private static $authTypes = [
		'local' => 'SecurePoll_LocalAuth',
		'remote-mw' => 'SecurePoll_RemoteMWAuth',
	];

	/**
	 * Create an auth object of the given type
	 * @param SecurePoll_Context $context
	 * @param string $type
	 * @return SecurePoll_LocalAuth|SecurePoll_RemoteMWAuth
	 * @throws MWException
	 */
	public static function factory( $context, $type ) {
		if ( !isset( self::$authTypes[$type] ) ) {
			throw new MWException( "Invalid authentication type: $type" );
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

	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * Create a voter transparently, without user interaction.
	 * Sessions authorized against local accounts are created this way.
	 * @param SecurePoll_Election $election
	 * @return Status
	 */
	public function autoLogin( $election ) {
		return Status::newFatal( 'securepoll-not-logged-in' );
	}

	/**
	 * Create a voter on a direct request from a remote site.
	 * @param SecurePoll_Election $election
	 * @return Status
	 */
	public function requestLogin( $election ) {
		return $this->autoLogin( $election );
	}

	/**
	 * Get the voter associated with the current session. Returns false if
	 * there is no session.
	 * @param SecurePoll_Election $election
	 * @return SecurePoll_Election
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
				if ( $otherVoter->getId() != $voterId ) {
					$otherVoter->addCookieDup( $voterId );
					$session['securepoll_voter'][$election->getId()] = $otherVoter->getId();
					return $otherVoter;
				}
			}

			# Sanity check election ID
			$voter = $this->context->getVoter( $voterId );
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
	 * @return SecurePoll_Voter
	 */
	public function getVoter( $params ) {
		$dbw = $this->context->getDB();

		# This needs to be protected by FOR UPDATE
		# Otherwise a race condition could lead to duplicate users for a single remote user,
		# and thus to duplicate votes.
		$dbw->startAtomic( __METHOD__ );
		$row = $dbw->selectRow(
			'securepoll_voters', '*',
			[
				'voter_name' => $params['name'],
				'voter_election' => $params['electionId'],
				'voter_domain' => $params['domain'],
				'voter_url' => $params['url']
			],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
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
	 * @param SecurePoll_Election $election
	 * @return Status
	 */
	public function newAutoSession( $election ) {
		$status = $this->autoLogin( $election );
		if ( $status->isGood() ) {
			$voter = $status->value;
			$session = SessionManager::getGlobalSession();
			$session['securepoll_voter'][$election->getId()] = $voter->getId();
			$voter->doCookieCheck();
		}
		return $status;
	}

	/**
	 * Create a voter on an explicit request, and create a session for it.
	 * @param SecurePoll_Election $election
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

/**
 * Authorization class for locally created accounts.
 * Certain functions in this class are also used for sending local voter
 * parameters to a remote SecurePoll installation.
 */
class SecurePoll_LocalAuth extends SecurePoll_Auth {
	/**
	 * Create a voter transparently, without user interaction.
	 * Sessions authorized against local accounts are created this way.
	 * @param SecurePoll_Election $election
	 * @return Status
	 */
	public function autoLogin( $election ) {
		global $wgUser;
		if ( $wgUser->isAnon() ) {
			return Status::newFatal( 'securepoll-not-logged-in' );
		}
		$params = $this->getUserParams( $wgUser );
		$params['electionId'] = $election->getId();
		$qualStatus = $election->getQualifiedStatus( $params );
		if ( !$qualStatus->isOK() ) {
			return $qualStatus;
		}
		$voter = $this->getVoter( $params );
		return Status::newGood( $voter );
	}

	/**
	 * Get voter parameters for a local User object.
	 * @param User $user
	 * @return array
	 */
	public function getUserParams( $user ) {
		global $wgServer;
		$params = [
			'name' => $user->getName(),
			'type' => 'local',
			'domain' => preg_replace( '!.*/(.*)$!', '$1', $wgServer ),
			'url' => $user->getUserPage()->getCanonicalURL(),
			'properties' => [
				'wiki' => wfWikiID(),
				'blocked' => $user->isBlocked(),
				'central-block-count' => $this->getCentralBlockCount( $user ),
				'edit-count' => $user->getEditCount(),
				'bot' => $user->isAllowed( 'bot' ),
				'language' => $user->getOption( 'language' ),
				'groups' => $user->getGroups(),
				'lists' => $this->getLists( $user ),
				'central-lists' => $this->getCentralLists( $user ),
				'registration' => $user->getRegistration(),
			]
		];

		Hooks::run( 'SecurePoll_GetUserParams', [ $this, $user, &$params ] );
		return $params;
	}

	/**
	 * Get the lists a given local user belongs to
	 * @param User $user
	 * @return array
	 */
	public function getLists( $user ) {
		$dbr = $this->context->getDB();
		$res = $dbr->select(
			'securepoll_lists',
			[ 'li_name' ],
			[ 'li_member' => $user->getId() ],
			__METHOD__
		);
		$lists = [];
		foreach ( $res as $row ) {
			$lists[] = $row->li_name;
		}
		return $lists;
	}

	/**
	 * Get the CentralAuth lists the user belongs to
	 * @param User $user
	 * @return array
	 */
	public function getCentralLists( $user ) {
		if ( !class_exists( CentralAuthUser::class ) ) {
			return [];
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->isAttached() ) {
			return [];
		}
		$dbc = CentralAuthUser::getCentralSlaveDB();
		$res = $dbc->select(
			'securepoll_lists',
			[ 'li_name' ],
			[ 'li_member' => $centralUser->getId() ],
			__METHOD__
		);
		$lists = [];
		foreach ( $res as $row ) {
			$lists[] = $row->li_name;
		}
		return $lists;
	}

	/**
	 * Checks how many central wikis the user is blocked on
	 * @param User $user
	 * @return int the number of wikis the user is blocked on.
	 */
	public function getCentralBlockCount( $user ) {
		if ( !class_exists( CentralAuthUser::class ) ) {
			return 0;
		}

		$centralUser = new CentralAuthUser( $user->getName() );

		$attached = $centralUser->queryAttached();
		$blockCount = 0;

		foreach ( $attached as $data ) {
			if ( $data['blocked'] ) {
				$blockCount++;
			}
		}

		return $blockCount;
	}
}

/**
 * Class for guest login from one MW instance running SecurePoll to another.
 */
class SecurePoll_RemoteMWAuth extends SecurePoll_Auth {
	public static function getCreateDescriptors() {
		return [
			'script-path' => [
				'label-message' => 'securepoll-create-label-remote_mw_script_path',
				'type' => 'url',
				'required' => true,
				'SecurePoll_type' => 'property',
			],
		];
	}

	/**
	 * Create a voter on a direct request from a remote site.
	 * @param SecurePoll_Election $election
	 * @return Status
	 */
	public function requestLogin( $election ) {
		global $wgRequest, $wgSecurePollScript, $wgConf;

		$urlParamNames = [ 'id', 'token', 'wiki', 'site', 'lang', 'domain' ];
		$vars = [];
		$params = [];
		foreach ( $urlParamNames as $name ) {
			$value = $wgRequest->getVal( $name );
			if ( !preg_match( '/^[\w.-]*$/', $value ) ) {
				wfDebug( __METHOD__ . " Invalid parameter: $name\n" );
				return false;
			}
			$params[$name] = $value;
			$vars["\$$name"] = $value;
		}

		$wgConf->loadFullData();

		// Get the site and language from $wgConf, if necessary.
		if ( !isset( $params['site'] ) || !isset( $params['lang'] ) ) {
			list( $site, $lang ) = $wgConf->siteFromDB( $params['wiki'] );
			if ( !isset( $params['site'] ) ) {
				$params['site'] = $site;
				$vars['$site'] = $site;
			}
			if ( !isset( $params['lang'] ) ) {
				$params['lang'] = $lang;
				$vars['$lang'] = $lang;
			}
		}

		// In some cases it doesn't matter what we pass for $suffix. When it
		// does, the correct value is $params['site'] unless there is a string
		// back-mapping for it in $wgConf->suffixes.
		$suffixes = array_flip( $wgConf->suffixes );
		$suffix = isset( $suffixes[$params['site']] ) && is_string( $suffixes[$params['site']] )
			? $suffixes[$params['site']]
			: $params['site'];

		$server = $wgConf->get( 'wgServer', $params['wiki'], $suffix, $params );
		$params['wgServer'] = $server;
		$vars["\$wgServer"] = $server;

		$url = $election->getProperty( 'remote-mw-script-path' );
		$url = strtr( $url, $vars );
		if ( substr( $url, -1 ) != '/' ) {
			$url .= '/';
		}
		$url .= $wgSecurePollScript . '?' .
			wfArrayToCgi( [
				'token' => $params['token'],
				'id' => $params['id']
			] );

		// Use the default SSL certificate file
		// Necessary on some versions of cURL, others do this by default
		$curlParams = [ CURLOPT_CAINFO => '/etc/ssl/certs/ca-certificates.crt', 'timeout' => 20 ];

		$value = Http::get( $url, $curlParams, __METHOD__ );

		if ( !$value ) {
			return Status::newFatal( 'securepoll-remote-auth-error' );
		}

		$status = unserialize( $value );
		$status->cleanCallback = false;

		if ( !$status || !( $status instanceof Status ) ) {
			return Status::newFatal( 'securepoll-remote-parse-error' );
		}
		if ( !$status->isOK() ) {
			return $status;
		}
		$params = $status->value;
		$params['type'] = 'remote-mw';
		$params['electionId'] = $election->getId();

		$qualStatus = $election->getQualifiedStatus( $params );
		if ( !$qualStatus->isOK() ) {
			return $qualStatus;
		}

		return Status::newGood( $this->getVoter( $params ) );
	}

	/**
	 * Apply a one-way hash function to a string.
	 *
	 * The aim is to encode a user's login token so that it can be transmitted to the
	 * voting server without giving the voting server any special rights on the wiki
	 * (apart from the ability to verify the user). We truncate the hash at 26
	 * hexadecimal digits, to provide 24 bits less information than original token.
	 * This makes discovery of the token difficult even if the hash function is
	 * completely broken.
	 * @param string $token
	 * @return string
	 */
	public static function encodeToken( $token ) {
		return substr( sha1( __CLASS__ . '-' . $token ), 0, 26 );
	}
}
