<?php

namespace MediaWiki\Extension\SecurePoll\User;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;

/**
 * Class for guest login from one MW instance running SecurePoll to another.
 */
class RemoteMWAuth extends Auth {
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
	 * @param Election $election
	 * @return Status
	 */
	public function requestLogin( $election ) {
		global $wgRequest, $wgConf;

		$urlParamNames = [
			'id',
			'token',
			'wiki',
			'site',
			'lang',
			'domain'
		];
		$vars = [];
		$params = [];
		foreach ( $urlParamNames as $name ) {
			$value = $wgRequest->getVal( $name );
			if ( !preg_match( '/^[\w.-]*$/', (string)$value ) ) {
				throw new InvalidArgumentException( "Invalid parameter: $name" );
			}
			$params[$name] = $value;
			$vars["\$$name"] = $value;
		}

		$wgConf->loadFullData();

		// Get the site and language from $wgConf, if necessary.
		if ( !isset( $params['site'] ) || !isset( $params['lang'] ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			[ $site, $lang ] = $wgConf->siteFromDB( $params['wiki'] );
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
		$suffix = isset( $suffixes[$params['site']] ) && is_string(
			$suffixes[$params['site']]
		) ? $suffixes[$params['site']] : $params['site'];

		$server = $wgConf->get( 'wgServer', $params['wiki'], $suffix, $params );
		$params['wgServer'] = $server;
		$vars["\$wgServer"] = $server;

		$url = $election->getProperty( 'remote-mw-script-path' );
		$url = strtr( $url, $vars );
		if ( substr( $url, -1 ) != '/' ) {
			$url .= '/';
		}
		$url .= 'api.php';

		$options = [
			// Use the default SSL certificate file
			// Necessary on some versions of cURL, others do this by default
			CURLOPT_CAINFO => '/etc/ssl/certs/ca-certificates.crt',
			'timeout' => 20,
			'postData' => [
				'action' => 'securepollauth',
				'id' => $params['id'],
				'format' => 'json',
				'token' => $params['token'],
			],
		];

		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->post( $url, $options, __METHOD__ );

		if ( !$response ) {
			return Status::newFatal( 'securepoll-remote-auth-error' );
		}

		/** @var array|null $json */
		$json = json_decode( $response, true );
		if ( $json === null ) {
			return Status::newFatal( 'securepoll-remote-parse-error' );
		}

		if ( isset( $json['error'] ) ) {
			return Status::newFatal( $json['error']['code'] );
		}

		$params = $json['securepollauth'];
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
