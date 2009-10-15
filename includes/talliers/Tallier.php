<?php

/**
 * Base class for objects which tally individual questions.
 * See SecurePoll_ElectionTallier for an object which can tally multiple 
 * questions.
 */
abstract class SecurePoll_Tallier {
	var $context, $question, $optionsById;

	abstract function addVote( $scores );
	abstract function getHtmlResult();
	abstract function getTextResult();

	abstract function finishTally();

	static function factory( $context, $type, $question ) {
		switch ( $type ) {
		case 'plurality':
			return new SecurePoll_PluralityTallier( $context, $question );
		case 'schulze':
			return new SecurePoll_SchulzeTallier( $context, $question );
		default:
			throw new MWException( "Invalid tallier type: $type" );
		}
	}

	function __construct( $context, $question ) {
		$this->context = $context;
		$this->question = $question;
		foreach ( $this->question->getOptions() as $option ) {
			$this->optionsById[$option->getId()] = $option;
		}
	}

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



