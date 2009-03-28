<?php

class SecurePoll_User {
	var $id, $name, $domain, $wiki, $type, $authority;
	var $properties = array();

	static $paramNames = array( 'id', 'name', 'domain', 'wiki', 'type', 'authority', 'properties' );

	function __construct( $params ) {
		foreach ( self::$paramNames as $name ) {
			if ( isset( $params[$name] ) ) {
				$this->$name = $params[$name];
			}
		}
	}

	static function newFromId( $id ) {
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( 'securepoll_voters', '*', array( 'voter_id' => $id ), __METHOD__ );
		if ( !$row ) {
			return false;
		}
		return self::newFromRow( $row );
	}

	static function newFromRow( $row ) {
		return new self( array(
			'id' => $row->voter_id,
			'name' => $row->voter_name,
			'domain' => $row->voter_domain,
			'type' => $row->voter_type,
			'authority' => $row->voter_authority,
			'properties' => self::decodeProperties( $row->voter_properties )
		) );
	}

	static function createUser( $params ) {
		$db = wfGetDB( DB_MASTER );
		$id = $db->nextSequenceValue( 'voters_voter_id' );
		$row = array(
			'voter_id' => $id,
			'voter_name' => $params['name'],
			'voter_type' => $params['type'],
			'voter_domain' => $params['domain'],
			'voter_authority' => $params['authority'],
			'voter_properties' => self::encodeProperties( $params['properties'] )
		);
		$db->insert( 'securepoll_voters', $row, __METHOD__ );
		$params['id'] = $db->insertId();
		return new self( $params );
	}

	function getId() { return $this->id; }
	function getName() { return $this->name; }
	function getType() { return $this->type; }
	function getDomain() { return $this->domain; }
	function getAuthority() { return $this->authority; }

	function getLanguage() {
		return $this->getProperty( 'language', 'en' );
	}

	function getProperty( $name, $default = false ) {
		if ( isset( $this->properties[$name] ) ) {
			return $this->properties[$name];
		} else {
			return $default;
		}
	}

	/**
	 * Checks if the user is allowed to administrate elections
	 * by checking for the 'securepoll' user right
	 *
	 * @return boolean: true if the user has 'securepoll' right, otherwise false
	 */
	function isAdmin() {
		global $wgUser;
		return $wgUser->isAllowed( 'securepoll' );
	}

	function isRemote() {
		return $this->type !== 'local';
	}

	static function decodeProperties( $blob ) {
		if ( strval( $blob ) == '' ) {
			return array();
		} else {
			return unserialize( $blob );
		}
	}

	static function encodeProperties( $props ) {
		return serialize( $props );
	}
}
