<?php

/**
 * Base class for objects which tally individual questions.
 * See SecurePoll_ElectionTallier for an object which can tally multiple
 * questions.
 */
abstract class SecurePoll_Tallier {
	public $context, $question, $electionTallier, $election, $optionsById;

	abstract function addVote( $scores );
	abstract function getHtmlResult();
	abstract function getTextResult();

	abstract function finishTally();

	public static $tallierTypes = array(
		'plurality' => 'SecurePoll_PluralityTallier',
		'schulze' => 'SecurePoll_SchulzeTallier',
		'histogram-range' => 'SecurePoll_HistogramRangeTallier',
		'alternative-vote' => 'SecurePoll_AlternativeVoteTallier',
	);

	/**
	 * @param $context
	 * @param $type
	 * @param $electionTallier
	 * @param $question
	 * @return SecurePoll_Tallier
	 * @throws MWException
	 */
	static function factory( $context, $type, $electionTallier, $question ) {
		if ( !isset( self::$tallierTypes[$type] ) ) {
			throw new MWException( "Invalid tallier type: $type" );
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
	static function getCreateDescriptors() {
		return array(
			'election' => array(),
			'question' => array(),
			'option' => array(),
		);
	}

	/**
	 * @param  $context SecurePoll_Context
	 * @param  $electionTallier SecurePoll_ElectionTallier
	 * @param  $question SecurePoll_Question
	 */
	function __construct( $context, $electionTallier, $question ) {
		$this->context = $context;
		$this->question = $question;
		$this->electionTallier = $electionTallier;
		$this->election = $electionTallier->election;
		foreach ( $this->question->getOptions() as $option ) {
			$this->optionsById[$option->getId()] = $option;
		}
	}

	/**
	 * @param $ranks
	 * @return string
	 */
	function convertRanksToHtml( $ranks ) {
		$s = "<table class=\"securepoll-table\">";
		$ids = array_keys( $ranks );
		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i-1] ) ? $ranks[$ids[$i-1]] : false;
			$nextRank = isset( $ids[$i+1] ) ? $ranks[$ids[$i+1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= "<tr>" .
				Xml::element( 'td', array(), $rank ) .
				Xml::element( 'td', array(), $option->parseMessage( 'text' ) ) .
				"</tr>\n";
		}
		$s .= "</table>";
		return $s;
	}

	/**
	 * @param $ranks
	 * @return string
	 */
	function convertRanksToText( $ranks ) {
		$s = '';
		$ids = array_keys( $ranks );
		$colWidth = 6;
		foreach ( $this->optionsById as $option ) {
			$colWidth = max( $colWidth, $option->getMessage( 'text' ) );
		}

		foreach ( $ids as $i => $oid ) {
			$rank = $ranks[$oid];
			$prevRank = isset( $ids[$i-1] ) ? $ranks[$ids[$i-1]] : false;
			$nextRank = isset( $ids[$i+1] ) ? $ranks[$ids[$i+1]] : false;
			if ( $rank === $prevRank || $rank === $nextRank ) {
				$rank .= '*';
			}

			$option = $this->optionsById[$oid];
			$s .= str_pad( $rank, 6 ) . ' | ' .
				$option->getMessage( 'text' ) . "\n";
		}
		return $s;
	}
}



