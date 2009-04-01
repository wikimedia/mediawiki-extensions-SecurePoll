<?php

class SecurePoll_Entity {
	var $id;
	var $messagesLoaded = array();
	var $properties;

	static $languages = array();
	static $messageCache = array();

	function __construct( $type, $id = false ) {
		$this->type = $type;
		$this->id = $id;
	}

	function getType() {
		return $this->type;
	}

	function getMessageNames() {
		# STUB
		return array();
	}

	function getId() {
		return $this->id;
	}

	static function setLanguages( $languages ) {
		self::$languages = $languages;
	}

	function getChildren() {
		return array();
	}

	function getDescendants() {
		$descendants = array();
		$children = $this->getChildren();
		foreach ( $children as $child ) {
			$descendants[] = $child;
			$descendants = array_merge( $descendants, $child->getDescendants() );
		}
		return $descendants;
	}

	function loadMessages( $lang = false ) {
		if ( $lang === false ) {
			$lang = reset( self::$languages );
		}
		$ids = array( $this->getId() );
		foreach ( $this->getDescendants() as $child ) {
			$id = $child->getId();
			if ( !isset( self::$messageCache[$lang][$id] ) ) {
				$ids[] = $id;
			}
		}
		if ( !count( $ids ) ) {
			return;
		}

		$db = wfGetDB( DB_MASTER );
		$res = $db->select(
			'securepoll_msgs',
			'*',
			array(
				'msg_entity' => $ids,
				'msg_lang' => $lang
			),
			__METHOD__
		);
		foreach ( $res as $row ) {
			self::$messageCache[$row->msg_lang][$row->msg_entity][$row->msg_key] = $row->msg_text;
		}
		$this->messagesLoaded[$lang] = true;
	}

	function loadProperties() {
		$db = wfGetDB( DB_MASTER );
		$res = $db->select(
			'securepoll_properties',
			'*',
			array( 'pr_entity' => $this->getId() ),
			__METHOD__ );
		$this->properties = array();
		foreach ( $res as $row ) {
			$this->properties[$row->pr_key] = $row->pr_value;
		}
	}

	function getRawMessage( $name, $language ) {
		if ( empty( $this->messagesLoaded[$language] ) ) {
			$this->loadMessages( $language );
		}
		if ( !isset( self::$messageCache[$language][$this->getId()][$name] ) ) {
			return false;
		} else {
			return self::$messageCache[$language][$this->getId()][$name];
		}
	}

	function getMessage( $name, $language = false ) {
		if ( $language === false ) {
			$language = reset( self::$languages );
		}
		if ( empty( $this->messagesLoaded[$language] ) ) {
			$this->loadMessages( $language );
		}
		$id = $this->getId();
		foreach ( self::$languages as $language ) {
			if ( isset( self::$messageCache[$language][$id][$name] ) ) {
				return self::$messageCache[$language][$id][$name];
			}
		}
		return "[$name]";
	}

	function getProperty( $name, $default = false ) {
		if ( $this->properties === null ) {
			$this->loadProperties();
		}
		if ( isset( $this->properties[$name] ) ) {
			return $this->properties[$name];
		} else {
			return $default;
		}
	}

}
