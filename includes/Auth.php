<?php

class SecurePoll_Auth {
	static $authTypes = array(
		'local' => 'SecurePoll_LocalAuth',
		'remote-mw' => 'SecurePoll_RemoteMWAuth',
	);

	static function factory( $type ) {
		if ( !isset( self::$authTypes[$type] ) ) {
			throw new MWException( "Invalid authentication type: $type" );
		}
		$class = self::$authTypes[$type];
		return new $class;
	}

	function login( $election ) {
		if( session_id() == '' ) {
			wfSetupSession();
		}
		if ( isset( $_SESSION['bvUser'] ) ) {
			$user = SecurePoll_User::newFromId( $_SESSION['bvUser'] );
		} else {
			return false;
		}
	}

	function getUser( $params ) {
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( 
			'securepoll_voters', '*', 
			array( 
				'voter_name' => $params['name'], 
				'voter_domain' => $params['domain'],
				'voter_authority' => $params['authority'] 
			),
			__METHOD__ );
		if ( $row ) {
			return SecurePoll_User::newFromRow( $row );
		} else {
			return SecurePoll_User::createUser( $params );
		}
	}
}

class SecurePoll_LocalAuth extends SecurePoll_Auth {
	function login( $election ) {
		global $wgUser, $wgServer, $wgLang;
		$user = parent::login( $election );
		if ( !$user && $wgUser->isLoggedIn() ) {
			$params = array(
				'name' => $wgUser->getName(),
				'type' => 'local',
				'domain' => $wgServer,
				'authority' => $wgServer . $wgUser->getUserPage()->getFullURL(),
				'properties' => array(
					'wiki' => wfWikiID(),
					'blocked' => $wgUser->isBlocked(),
					'edit-count' => $wgUser->getEditCount(),
					'bot' => $wgUser->isBot(),
					'language' => $wgLang->getCode(),
				)
			);
			$user = $this->getUser( $params );
		}
		return $user;
	}
}

class SecurePoll_RemoteMWAuth extends SecurePoll_Auth {
	function login( $election ) {
		global $wgRequest;

		$user = parent::login( $election );
		if ( $user ) {
			return $user;
		}

		$urlParamNames = array( 'sid', 'casid', 'wiki', 'site', 'domain' );
		$params = array();
		$vars = array();
		foreach ( $urlParamNames as $name ) {
			$value = $wgRequest->getVal( $name );
			if ( !preg_match( '/^[\w.-]*$/', $value ) ) {
				wfDebug( __METHOD__." Invalid parameter: $name\n" );
				return false;
			}
			$vars["\$$name"] = $value;
			$params[$name] = $value;
		}

		$electionParamNames = array( 'remote-mw-api-url', 'remote-mw-cookie', 'remote-mw-ca-cookie' );
		foreach ( $electionParamNames as $name ) {
			$value = $election->getProperty( $name );
			if ( $value !== false ) {
				$value = strtr( $value, $vars );
			}
			$params[$name] = $value;
		}

		if ( !$params['sid'] ) {
			return false;
		}
		if ( !$params['remote-mw-cookie'] ) {
			wfDebug( __METHOD__.": No remote cookie configured!\n" );
			return false;
		}
		$cookies = array( $params['remote-mw-cookie'] => $params['sid'] );
		if ( $params['casid'] && $params['remote-mw-ca-cookie'] ) {
			$cookies[$params['remote-mw-ca-cookie']] = $params['casid'];
		}
		$cookieHeader = $this->encodeCookies( $cookies );
		$url = $params['remote-mw-api-url'] . 
			'?action=query&format=php' .
			'&meta=userinfo&uiprop=blockinfo|rights|editcount|options' . 
			'&meta=siteinfo';
		$curlParams = array( 
			CURLOPT_COOKIE => $cookieHeader,

			// Use the default SSL certificate file
			// Necessary on some versions of cURL, others do this by default
			CURLOPT_CAINFO => '/etc/ssl/certs/ca-certificates.crt'
		);

		wfDebug( "Fetching URL $url\n" );
		$value = Http::get( $url, 20, $curlParams );

		if ( !$value ) {
			wfDebug( __METHOD__.": No response from server\n" );
			$_SESSION['bvCurlError'] = curl_error( $c );
			return false;
		}

		$decoded = unserialize( $value );
		$userinfo = $decoded['query']['userinfo'];
		$siteinfo = $decoded['query']['general'];
		if ( isset( $userinfo['anon'] ) ) {
			wfDebug( __METHOD__.": User is not logged in\n" );
			return false;
		}
		if ( !isset( $userinfo['name'] ) ) {
			wfDebug( __METHOD__.": No username in response\n" );
			return false;
		}
		if ( isset( $userinfo['options']['language'] ) ) {
			$language = $userinfo['options']['language'];
		} else {
			$language = 'en';
		}
		$urlInfo = wfParseUrl( $decoded['query']['general']['base'] );
		$domain = $urlInfo === false ? false : $urlInfo['host'];
		$userPage = $siteinfo['server'] . 
			str_replace( $siteinfo['articlepath'], '$1', '' ) . 
			'User:' .
			urlencode( str_replace( $userinfo['name'], ' ', '_' ) );

		wfDebug( __METHOD__." got response for user {$userinfo['name']}@{$params['wiki']}\n" );
		return $this->getUser( array(
			'name' => $userinfo['name'],
			'type' => 'remote-mw',
			'domain' => $domain,
			'authority' => $userPage,
			'properties' => array(
				'wiki' => $siteinfo['wikiid'],
				'blocked' => isset( $userinfo['blockedby'] ),
				'edit-count' => $userinfo['edit-count'],
				'bot' => in_array( 'bot', $userinfo['rights'] ),
				'language' => $language,
			)
		) );
	}

	function encodeCookies( $cookies ) {
		$s = '';
		foreach ( $cookies as $name => $value ) {
			if ( $s !== '' ) {
				$s .= ';';
			}
			$s .= urlencode( $name ) . '=' . urlencode( $value );
		}
		return $s;
	}
}
