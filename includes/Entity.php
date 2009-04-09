<?php

/**
 * There are three types of entity: elections, questions and options. The 
 * entity abstraction provides generic i18n support, allowing localised message
 * text to be attached to the entity, without introducing a dependency on the 
 * editability of the MediaWiki namespace. Users are only allowed to edit messages
 * for the elections that they administer.
 *
 * Entities also provide a persistent key/value pair interface for non-localised 
 * properties, and a descendant tree which is used to accelerate message loading.
 */
class SecurePoll_Entity {
	var $id;
	var $messagesLoaded = array();
	var $properties;

	static $languages = array();
	static $messageCache = array();

	/**
	 * Create an entity of the given type. This is typically called from the 
	 * child constructor.
	 * @param $type string
	 * @param $id integer
	 */
	function __construct( $type, $id = false ) {
		$this->type = $type;
		$this->id = $id;
	}

	/**
	 * Get the type of the entity.
	 * @return string
	 */
	function getType() {
		return $this->type;
	}

	/**
	 * Get a list of localisable message names. This is used to provide the 
	 * translate subpage with a list of messages to localise.
	 */
	function getMessageNames() {
		# STUB
		return array();
	}

	/**
	 * Get the entity ID.
	 */
	function getId() {
		return $this->id;
	}

	/**
	 * Set the global language fallback sequence. 
	 *
	 * @param $languages array A list of language codes. When a message is 
	 *     requested, the first code in the array will be tried first, followed
	 *     by the subsequent codes.
	 */
	static function setLanguages( $languages ) {
		self::$languages = $languages;
	}

	/**
	 * Get the child entity objects. When the messages of an object are loaded,
	 * the messages of the children are loaded automatically, to reduce the 
	 * query count.
	 *
	 * @return array
	 */
	function getChildren() {
		return array();
	}

	/**
	 * Get all children, grandchildren, etc. in a single flat array of entity 
	 * objects.
	 * @return array
	 */
	function getDescendants() {
		$descendants = array();
		$children = $this->getChildren();
		foreach ( $children as $child ) {
			$descendants[] = $child;
			$descendants = array_merge( $descendants, $child->getDescendants() );
		}
		return $descendants;
	}

	/**
	 * Load messages for a given language. It's not generally necessary to call 
	 * this since getMessage() does it automatically.
	 */
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

	/**
	 * Load the properties for the entity. It is not generally necessary to 
	 * call this function from another class since getProperty() does it 
	 * automatically.
	 */
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

	/**
	 * Get a message, or false if the message does not exist. Does not use
	 * the fallback sequence.
	 *
	 * @param $name string
	 * @param $language string
	 */
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

	/**
	 * Get a message, and go through the fallback sequence if it is not found.
	 * If the message is not found even after looking at all possible languages,
	 * a placeholder string is returned.
	 *
	 * @param $name string
	 */
	function getMessage( $name ) {
		$id = $this->getId();
		foreach ( self::$languages as $language ) {
			if ( empty( $this->messagesLoaded[$language] ) ) {
				$this->loadMessages( $language );
			}
			if ( isset( self::$messageCache[$language][$id][$name] ) ) {
				return self::$messageCache[$language][$id][$name];
			}
		}
		return "[$name]";
	}

	/**
	 * Get a property value. If it does not exist, the $default parameter
	 * is passed back.
	 * @param $name string
	 * @param $default mixed
	 */
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
