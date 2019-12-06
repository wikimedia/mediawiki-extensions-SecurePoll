<?php

/**
 * Class representing a question, which the voter will answer. There may be
 * more than one question in an election.
 */
class SecurePoll_Question extends SecurePoll_Entity {
	/** @var SecurePoll_Option[] */
	public $options;
	public $electionId;

	/**
	 * Constructor
	 * @param SecurePoll_Context $context
	 * @param array $info Associative array of entity info
	 */
	public function __construct( $context, $info ) {
		parent::__construct( $context, 'question', $info );
		$this->options = [];
		foreach ( $info['options'] as $optionInfo ) {
			$this->options[] = new SecurePoll_Option( $context, $optionInfo );
		}
	}

	/**
	 * Get a list of localisable message names.
	 * @return array
	 */
	public function getMessageNames() {
		$ballot = $this->getElection()->getBallot();

		return array_merge( $ballot->getMessageNames( $this ), [ 'text' ] );
	}

	/**
	 * Get the child entity objects.
	 * @return array
	 */
	public function getChildren() {
		return $this->options;
	}

	public function getOptions() {
		return $this->options;
	}

	public function getConfXml( $params = [] ) {
		$s = "<question>\n" . $this->getConfXmlEntityStuff( $params );
		foreach ( $this->getOptions() as $option ) {
			$s .= $option->getConfXml( $params );
		}
		$s .= "</question>\n";

		return $s;
	}
}
