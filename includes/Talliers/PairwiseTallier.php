<?php

namespace MediaWiki\Extension\SecurePoll\Talliers;

use MediaWiki\Html\Html;

/**
 * Generic functionality for Condorcet-style pairwise methods.
 * Tested via SchulzeTallier.
 */
abstract class PairwiseTallier extends Tallier {
	/** @var array */
	public $optionIds = [];
	/** @var array */
	public $victories = [];
	/** @var array|null */
	public $abbrevs;
	/** @var array */
	public $rowLabels = [];

	/** @inheritDoc */
	public function __construct( $context, $electionTallier, $question ) {
		parent::__construct( $context, $electionTallier, $question );
		$this->optionIds = [];
		foreach ( $question->getOptions() as $option ) {
			$this->optionIds[] = $option->getId();
		}

		$this->victories = [];
		foreach ( $this->optionIds as $i ) {
			foreach ( $this->optionIds as $j ) {
				$this->victories[$i][$j] = 0;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function addVote( $ranks ) {
		foreach ( $this->optionIds as $oid1 ) {
			if ( !isset( $ranks[$oid1] ) ) {
				wfDebug( "Invalid vote record, missing option $oid1\n" );

				return false;
			}
			foreach ( $this->optionIds as $oid2 ) {
				# Lower = better
				if ( $ranks[$oid1] < $ranks[$oid2] ) {
					$this->victories[$oid1][$oid2]++;
				}
			}
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function getOptionAbbreviations() {
		if ( $this->abbrevs === null ) {
			$abbrevs = [];
			foreach ( $this->question->getOptions() as $option ) {
				$text = $option->getMessage( 'text' );
				$parts = explode( ' ', $text );
				$initials = '';
				foreach ( $parts as $part ) {
					$firstLetter = mb_substr( $part, 0, 1 );
					if ( $part === '' || ctype_punct( $firstLetter ) ) {
						continue;
					}
					$initials .= $firstLetter;
				}
				if ( isset( $abbrevs[$initials] ) ) {
					$index = 2;
					while ( isset( $abbrevs[$initials . $index] ) ) {
						$index++;
					}
					$initials .= $index;
				}
				$abbrevs[$initials] = $option->getId();
			}
			$this->abbrevs = array_flip( $abbrevs );
		}

		return $this->abbrevs;
	}

	/**
	 * @param string $format
	 * @return string[]
	 */
	public function getRowLabels( $format = 'html' ) {
		if ( !isset( $this->rowLabels[$format] ) ) {
			$rowLabels = [];
			$abbrevs = $this->getOptionAbbreviations();
			foreach ( $this->question->getOptions() as $option ) {
				if ( $format == 'html' ) {
					$label = $option->parseMessage( 'text' );
				} else {
					$label = $option->getMessage( 'text' );
				}
				if ( $label !== $abbrevs[$option->getId()] ) {
					$label .= ' (' . $abbrevs[$option->getId()] . ')';
				}
				$rowLabels[$option->getId()] = $label;
			}
			$this->rowLabels[$format] = $rowLabels;
		}

		return $this->rowLabels[$format];
	}

	/**
	 * @param array $matrix
	 * @param int[] $rankedIds
	 * @return string
	 */
	public function convertMatrixToHtml( $matrix, $rankedIds ) {
		$abbrevs = $this->getOptionAbbreviations();
		$rowLabels = $this->getRowLabels( 'html' );

		$s = "<table class=\"securepoll-results\">";

		# Corner box
		$s .= "<tr>\n<th>&#160;</th>\n";

		# Header row
		foreach ( $rankedIds as $oid ) {
			$s .= Html::rawElement( 'th', [], $abbrevs[$oid] ) . "\n";
		}
		$s .= "</tr>\n";

		foreach ( $rankedIds as $oid1 ) {
			# Header column
			$s .= "<tr>\n";
			$s .= Html::rawElement(
				'td',
				[ 'class' => 'securepoll-results-row-heading' ],
				$rowLabels[$oid1]
			);
			# Rest of the matrix
			foreach ( $rankedIds as $oid2 ) {
				$value = $matrix[$oid1][$oid2] ?? '';
				if ( is_array( $value ) ) {
					$value = '(' . implode( ', ', $value ) . ')';
				}
				$s .= Html::element( 'td', [], $value ) . "\n";
			}
			$s .= "</tr>\n";
		}
		$s .= "</table>";

		return $s;
	}

	/**
	 * @param array $matrix
	 * @param int[] $rankedIds
	 * @return string
	 */
	public function convertMatrixToText( $matrix, $rankedIds ) {
		$abbrevs = $this->getOptionAbbreviations();
		$minWidth = 15;
		$rowLabels = $this->getRowLabels( 'text' );

		# Calculate column widths
		$colWidths = [];
		foreach ( $abbrevs as $id => $abbrev ) {
			if ( strlen( $abbrev ) < $minWidth ) {
				$colWidths[$id] = $minWidth;
			} else {
				$colWidths[$id] = strlen( $abbrev );
			}
		}
		$headerColumnWidth = $minWidth;
		foreach ( $rowLabels as $label ) {
			$headerColumnWidth = max( $headerColumnWidth, strlen( $label ) );
		}

		# Corner box
		$s = str_repeat( ' ', $headerColumnWidth ) . ' | ';

		# Header row
		foreach ( $rankedIds as $oid ) {
			$s .= str_pad( $abbrevs[$oid], $colWidths[$oid] ) . ' | ';
		}
		$s .= "\n";

		# Divider
		$s .= str_repeat( '-', $headerColumnWidth ) . '-+-';
		foreach ( $rankedIds as $oid ) {
			$s .= str_repeat( '-', $colWidths[$oid] ) . '-+-';
		}
		$s .= "\n";

		foreach ( $rankedIds as $oid1 ) {
			# Header column
			$s .= str_pad( $rowLabels[$oid1], $headerColumnWidth ) . ' | ';

			# Rest of the matrix
			foreach ( $rankedIds as $oid2 ) {
				$value = $matrix[$oid1][$oid2] ?? '';
				if ( is_array( $value ) ) {
					$value = '(' . implode( ', ', $value ) . ')';
				}
				$s .= str_pad( $value, $colWidths[$oid2] ) . ' | ';
			}
			$s .= "\n";
		}

		return $s;
	}
}
