<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Xml\Xml;

class HistogramRangeTallier extends Tallier {
	/** @var array */
	public $histogram = [];
	/** @var array */
	public $sums = [];
	/** @var array */
	public $counts = [];
	/** @var array|null */
	public $averages;
	/** @var int */
	public $minScore;
	/** @var int */
	public $maxScore;

	public function __construct( $context, $electionTallier, $question ) {
		parent::__construct( $context, $electionTallier, $question );
		$this->minScore = intval( $question->getProperty( 'min-score' ) );
		$this->maxScore = intval( $question->getProperty( 'max-score' ) );
		if ( $this->minScore >= $this->maxScore ) {
			throw new InvalidDataException( __METHOD__ . ': min-score/max-score configured incorrectly' );
		}

		foreach ( $question->getOptions() as $option ) {
			$this->histogram[$option->getId()] = array_fill(
				$this->minScore,
				$this->maxScore - $this->minScore + 1,
				0
			);
			$this->sums[$option->getId()] = 0;
			$this->counts[$option->getId()] = 0;
		}
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function addVote( $scores ) {
		foreach ( $scores as $oid => $score ) {
			$this->histogram[$oid][$score]++;
			$this->sums[$oid] += $score;
			$this->counts[$oid]++;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function finishTally() {
		$this->averages = [];
		foreach ( $this->sums as $oid => $sum ) {
			if ( $this->counts[$oid] === 0 ) {
				$this->averages[$oid] = 'N/A';
				break;
			}
			$this->averages[$oid] = $sum / $this->counts[$oid];
		}
		arsort( $this->averages );
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function loadJSONResult( $data ) {
		$this->averages = $data['averages'];
		$this->histogram = $data['histogram'];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getJSONResult() {
		return [
			'averages' => $this->averages,
			'histogram' => $this->histogram,
		];
	}

	/**
	 * @inheritDoc
	 * @throws InvalidDataException
	 */
	public function getHtmlResult() {
		$ballot = $this->election->getBallot();
		if ( !method_exists( $ballot, 'getColumnLabels' ) ) {
			throw new InvalidDataException( __METHOD__ . ': ballot type not supported by this tallier' );
		}
		$optionLabels = [];
		foreach ( $this->question->getOptions() as $option ) {
			$optionLabels[$option->getId()] = $option->parseMessageInline( 'text' );
		}

		// @phan-suppress-next-line PhanUndeclaredMethod Checked by is_callable
		$labels = $ballot->getColumnLabels( $this->question );
		$s = "<table class=\"securepoll-table\">\n" . "<tr>\n" . "<th>&#160;</th>\n";
		foreach ( $labels as $label ) {
			$s .= Xml::element( 'th', [], $label ) . "\n";
		}
		$s .= Xml::element( 'th', [], wfMessage( 'securepoll-average-score' )->text() );
		$s .= "</tr>\n";

		foreach ( $this->averages as $oid => $average ) {
			$s .= "<tr>\n" . Xml::tags(
					'td',
					[ 'class' => 'securepoll-results-row-heading' ],
					$optionLabels[$oid]
				) . "\n";
			foreach ( $labels as $score => $label ) {
				$s .= Xml::element( 'td', [], $this->histogram[$oid][$score] ) . "\n";
			}
			$s .= Xml::element( 'td', [], $average ) . "\n";
			$s .= "</tr>\n";
		}
		$s .= "</table>\n";

		return $s;
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function getTextResult() {
		return $this->getHtmlResult();
	}
}
