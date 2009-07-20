<?php

/**
 * Class representing a question, which the voter will answer. There may be 
 * more than one question in an election.
 */
class SecurePoll_Question extends SecurePoll_Entity {
	var $options;

	/**
	 * Constructor
	 * @param $id integer
	 * @param $options array Array of SecurePoll_Option children
	 */
	function __construct( $id, $options ) {
		parent::__construct( 'question', $id );
		$this->options = $options;
	}

	/**
	 * Get a list of localisable message names.
	 */
	function getMessageNames() {
		return array( 'text' );
	}

	/**
	 * Get the child entity objects.
	 */
	function getChildren() {
		return $this->options;
	}

	function getOptions() {
		return $this->options;
	}

	function getConfXml() {
		$s = "<question>\n" . $this->getConfXmlEntityStuff();
		foreach ( $this->getOptions() as $option ) {
			$s .= $option->getConfXml();
		}
		$s .= "</question>\n";
		return $s;
	}
}
