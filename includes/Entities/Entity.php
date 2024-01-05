<?php

namespace MediaWiki\Extension\SecurePoll\Entities;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use Xml;

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
class Entity {
	/** @var int|false */
	public $id;
	/** @var int|null */
	public $electionId;
	/** @var Context */
	public $context;
	/** @var array */
	public $messagesLoaded = [];
	/** @var array|null */
	public $properties;
	/** @var string */
	public $type;

	/**
	 * Create an entity of the given type. This is typically called from the
	 * child constructor.
	 * @param Context $context
	 * @param string $type
	 * @param array $info Associative array of entity info
	 */
	public function __construct( $context, $type, $info ) {
		$this->context = $context;
		$this->type = $type;
		$this->id = $info['id'] ?? false;
		$this->electionId = $info['election'] ?? null;
	}

	/**
	 * Get the type of the entity.
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get a list of localisable message names. This is used to provide the
	 * translate subpage with a list of messages to localise.
	 * @return array
	 */
	public function getMessageNames() {
		# STUB
		return [];
	}

	/**
	 * Get the entity ID.
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get the parent election
	 * @return Election|null
	 */
	public function getElection() {
		return $this->electionId !== null ? $this->context->getElection( $this->electionId ) : null;
	}

	/**
	 * Get the child entity objects. When the messages of an object are loaded,
	 * the messages of the children are loaded automatically, to reduce the
	 * query count.
	 *
	 * @return array
	 */
	public function getChildren() {
		return [];
	}

	/**
	 * Get all children, grandchildren, etc. in a single flat array of entity
	 * objects.
	 * @return array
	 */
	public function getDescendants() {
		$descendants = [];
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
	 * @param string|false $lang
	 */
	public function loadMessages( $lang = false ) {
		if ( $lang === false ) {
			$lang = reset( $this->context->languages );
		}
		$ids = [ $this->getId() ];
		foreach ( $this->getDescendants() as $child ) {
			$ids[] = $child->getId();
		}
		$this->context->getMessages( $lang, $ids );
		$this->messagesLoaded[$lang] = true;
	}

	/**
	 * Load the properties for the entity. It is not generally necessary to
	 * call this function from another class since getProperty() does it
	 * automatically.
	 */
	public function loadProperties() {
		$properties = $this->context->getStore()->getProperties( [ $this->getId() ] );
		if ( count( $properties ) ) {
			$this->properties = reset( $properties );
		} else {
			$this->properties = [];
		}
	}

	/**
	 * Get a message, or false if the message does not exist. Does not use
	 * the fallback sequence.
	 *
	 * @param string $name
	 * @param string $language
	 * @return string|false
	 */
	public function getRawMessage( $name, $language ) {
		if ( empty( $this->messagesLoaded[$language] ) ) {
			$this->loadMessages( $language );
		}

		return $this->context->getMessage( $language, $this->getId(), $name );
	}

	/**
	 * Get a message, and go through the fallback sequence if it is not found.
	 * If the message is not found even after looking at all possible languages,
	 * a placeholder string is returned.
	 *
	 * @param string $name
	 * @return string
	 */
	public function getMessage( $name ) {
		foreach ( $this->context->languages as $language ) {
			if ( empty( $this->messagesLoaded[$language] ) ) {
				$this->loadMessages( $language );
			}
			$message = $this->getRawMessage( $name, $language );
			if ( $message !== false ) {
				return $message;
			}
		}

		return "[$name]";
	}

	/**
	 * Get a message, and interpret it as wikitext, converting it to HTML.
	 * @param string $name
	 * @param bool $block
	 * @return string
	 */
	public function parseMessage( $name, $block = true ) {
		global $wgTitle;
		$parserOptions = $this->context->getParserOptions();
		if ( $wgTitle ) {
			$title = $wgTitle;
		} else {
			$title = SpecialPage::getTitleFor( 'SecurePoll' );
		}
		$wikiText = $this->getMessage( $name );
		$out = MediaWikiServices::getInstance()->getParser()->parse(
			$wikiText,
			$title,
			$parserOptions
		);

		$html = $out->getText( [ 'unwrap' => true ] );
		if ( !$block ) {
			$html = \Parser::stripOuterParagraph( $html );
		}
		return $html;
	}

	/**
	 * Get a message and convert it from wikitext to HTML, without <p> tags.
	 * @param string $name
	 * @return string
	 */
	public function parseMessageInline( $name ) {
		return $this->parseMessage( $name, false );
	}

	/**
	 * Get a list of languages for which we have translations, for this entity
	 * and its descendants.
	 * @return string[]
	 */
	public function getLangList() {
		$ids = [ $this->getId() ];
		foreach ( $this->getDescendants() as $child ) {
			$ids[] = $child->getId();
		}

		return $this->context->getStore()->getLangList( $ids );
	}

	/**
	 * Get a property value. If it does not exist, the $default parameter
	 * is passed back.
	 * @param string $name
	 * @param mixed $default
	 * @return bool|mixed
	 */
	public function getProperty( $name, $default = false ) {
		if ( $this->properties === null ) {
			$this->loadProperties();
		}

		return $this->properties[$name] ?? $default;
	}

	/**
	 * Get all defined properties as an associative array
	 * @return array
	 */
	public function getAllProperties() {
		if ( $this->properties === null ) {
			$this->loadProperties();
		}

		return $this->properties;
	}

	/**
	 * Get configuration XML. Overridden by most subclasses.
	 * @param array $params
	 * @return string
	 */
	public function getConfXml( $params = [] ) {
		return "<{$this->type}>\n" . $this->getConfXmlEntityStuff( $params ) . "</{$this->type}>\n";
	}

	/**
	 * Get an XML snippet giving the messages and properties
	 * @param array $params
	 * @return string
	 */
	public function getConfXmlEntityStuff( $params = [] ) {
		$s = Xml::element( 'id', [], (string)$this->getId() ) . "\n";
		$excludedNames = $this->getPropertyDumpExclusion( $params );
		foreach ( $this->getAllProperties() as $name => $value ) {
			if ( !in_array( $name, $excludedNames ) ) {
				$s .= Xml::element( 'property', [ 'name' => $name ], $value ) . "\n";
			}
		}
		$langs = $params['langs'] ?? $this->context->languages;
		foreach ( $this->getMessageNames() as $name ) {
			foreach ( $langs as $lang ) {
				$value = $this->getRawMessage( $name, $lang );
				if ( $value !== false ) {
					$s .= Xml::element(
							'message',
							[
								'name' => $name,
								'lang' => $lang
							],
							$value
						) . "\n";
				}
			}
		}

		return $s;
	}

	/**
	 * Get property names which aren't included in an XML dump.
	 * Overloaded by Election.
	 * @param array $params
	 * @return array
	 */
	public function getPropertyDumpExclusion( $params = [] ) {
		return [];
	}

}
