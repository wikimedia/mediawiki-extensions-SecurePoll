<?php

/**
 * Rules from https://w.wiki/9TyT
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be site-wide blocked in more than one public project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 17 March 2024 across Wikimedia wikis;
 *   - and have made at least 20 edits between 17 March 2023 and 17 March 2024.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class U4C202404PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the u4c202404_edits table for the 2024 U4C membership vote' );

		$this->table = 'u4c202404_edits';

		$this->beforeTime = '20240317000000';
		$this->beforeEditsToCount = 300;

		$this->betweenStart = '20230317000000';
		$this->betweenEnd = '20240317000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = U4C202404PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
