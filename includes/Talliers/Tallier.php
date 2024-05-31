<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Xml\Xml;

/**
 * Base class for objects which tally individual questions.
 * See ElectionTallier for an object which can tally multiple
 * questions.
 */
abstract class Tallier {
	/** @var Context */
	public $context;
	/** @var Question */
	public $question;
	/** @var ElectionTallier */
	public $electionTallier;
	/** @var Election */
	public $election;
	/** @var array */
	public $optionsById = [];

	/**
	 * Transforms individual ballots into an aggregated dataset that then gets consumed by finishTally()
	 *
	 * @param array $scores
	 * @return bool
	 */
	abstract public function addVote( $scores );

	/**
	 * Restores results from getJSONResult
	 *
	 * @param array $data
	 */
	abstract public function loadJSONResult( array $data );

	/**
	 * Get a simple array structure representing results for this tally. Should
	 * only be called after execute().
	 * This array MUST contain all required information for getHtmlResult or getTextResult to run after it is loaded.
	 * @return array
	 */
	abstract public function getJSONResult();

	/**
	 * Build string result from tallier's stored values
	 * @return string
	 */
	abstract public function getHtmlResult();

	/**
	 * Get text formatted results for this tally. Should only be called after
	 * execute().
	 * @return string
	 */
	abstract public function getTextResult();

	/**
	 * See inherit doc.
	 */
	abstract public function finishTally();

	/** @var string[] */
	public static $tallierTypes = [
		'plurality' => PluralityTallier::class,
		'schulze' => SchulzeTallier::class,
		'histogram-range' => HistogramRangeTallier::class,
		'droop-quota' => STVTallier::class,
	];

	/**
	 * @param Context $context
	 * @param string $type
	 * @param ElectionTallier $electionTallier
	 * @param Question $question
	 * @return Tallier
	 * @throws InvalidArgumentException
	 */
	public static function factory( $context, $type, $electionTallier, $question ) {
		if ( !isset( self::$tallierTypes[$type] ) ) {
			throw new InvalidArgumentException( "Invalid tallier type: $type" );
		}
		$class = self::$tallierTypes[$type];

		return new $class( $context, $electionTallier, $question );
	}

	/**
	 * Return descriptors for any properties this type requires for poll
	 * creation, for the election, questions, and options.
	 *
	 * The returned array should have three keys, "election", "question", and
	 * "option", each mapping to an array of HTMLForm descriptors.
	 *
	 * The descriptors should have an additional key, "SecurePoll_type", with
	 * the value being "property" or "message".
	 *
	 * @return array
	 */
	public static function getCreateDescriptors() {
		return [
			'election' => [],
			'question' => [],
			'option' => [],
		];
	}

	/**
	 * @param Context $context
	 * @param ElectionTallier $electionTallier
	 * @param Question $question
	 */
	public function __construct( $context, $electionTallier, $question ) {
		$this->context = $context;
		$this->question = $question;
		$this->electionTallier = $electionTallier;
		$this->election = $electionTallier->election;
		foreach ( $this->question->getOptions() as $option ) {
			$this->optionsById[$option->getId()] = $option;
		}
	}

	/**
	 * Build the result into HTML to display
	 * @param array $ranks
	 * @return string
	 */
	public function convertRanksToHtml( $ranks ) {
		$s = "<table class=\"securepoll-table\">";
		$ids = array_keys( $ranks );
		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i - 1] ) ? $ranks[$ids[$i - 1]] : false;
			$nextRank = isset( $ids[$i + 1] ) ? $ranks[$ids[$i + 1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= "<tr>" . Xml::element( 'td', [], $rank ) . Xml::openElement(
					'td',
					[]
				) . $option->parseMessage( 'text', false ) . Xml::closeElement( 'td' ) . "</tr>\n";
		}
		$s .= "</table>";

		return $s;
	}

	/**
	 * Build the result into HTML to display
	 * @param array $ranks
	 * @return string
	 */
	public function convertRanksToText( $ranks ) {
		$s = '';
		$ids = array_keys( $ranks );
		$colWidth = 6;
		foreach ( $this->optionsById as $option ) {
			$colWidth = max( $colWidth, $option->getMessage( 'text' ) );
		}

		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i - 1] ) ? $ranks[$ids[$i - 1]] : false;
			$nextRank = isset( $ids[$i + 1] ) ? $ranks[$ids[$i + 1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= str_pad( $rank, 6 ) . ' | ' . $option->getMessage( 'text' ) . "\n";
		}

		return $s;
	}
}
