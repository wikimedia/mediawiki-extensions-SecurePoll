<?php

namespace MediaWiki\Extensions\SecurePoll\Talliers;

/**
 * A STVTallier class,
 * Currently work in progress see T282014.
 */
class STVTallier extends Tallier {
	/**
	 * An array of vote combinations keyed by underscore-delimited
	 * and ranked options. Each vote has a rank array (which allows
	 * index-1 access to each ranked option) and a count
	 * @var array
	 */
	public $rankedVotes = [];

	/**
	 * @inheritDoc
	 */
	public function addVote( $scores ) {
		$id = implode( '_', $scores );
		$rank = [];
		foreach ( $scores as $ranked => $optionId ) {
			$rank[ $ranked + 1 ] = $optionId;
		}

		if ( !isset( $this->rankedVotes[$id] ) ) {
			$this->rankedVotes[$id] = [
				'count' => 1,
				'rank' => $rank,
			];
		} else {
			$this->rankedVotes[$id]['count'] += 1;
		}

		return true;
	}

	public function loadJSONResult( array $data ) {
		// TODO: Implement loadJSONResult() method.
	}

	public function getJSONResult() {
		// TODO: Implement getJSONResult() method.
		return [];
	}

	public function getHtmlResult() {
		// TODO: Implement getHtmlResult() method.
		return '';
	}

	public function getTextResult() {
		// TODO: Implement getTextResult() method.
		return '';
	}

	public function finishTally() {
		// TODO: Implement finishTally() method.
	}
}
