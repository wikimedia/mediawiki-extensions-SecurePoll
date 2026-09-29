<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\SecurePoll\Entities;

use MediaWiki\Extension\SecurePoll\Context;

/**
 * Class representing a question, which the voter will answer. There may be
 * more than one question in an election.
 */
class Question extends Entity {
	/** @var Option[] */
	public $options;
	/** @var int|null */
	public $electionId;

	/**
	 * Constructor
	 * @param Context $context
	 * @param array $info Associative array of entity info
	 */
	public function __construct( $context, $info ) {
		parent::__construct( $context, 'question', $info );
		$this->options = [];
		foreach ( $info['options'] as $optionInfo ) {
			$this->options[] = new Option( $context, $optionInfo );
		}
	}

	/** @inheritDoc */
	public function getMessageNames() {
		$ballot = $this->getElection()->getBallot();

		return array_merge( $ballot->getMessageNames( $this ), [ 'text' ] );
	}

	/** @inheritDoc */
	public function getChildren() {
		return $this->options;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * @param array $params
	 * @return string
	 */
	public function getConfXml( $params = [] ) {
		$s = "<question>\n" . $this->getConfXmlEntityStuff( $params );
		foreach ( $this->getOptions() as $option ) {
			$s .= $option->getConfXml( $params );
		}
		$s .= "</question>\n";

		return $s;
	}
}
