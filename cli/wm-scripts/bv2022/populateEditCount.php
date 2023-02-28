<?php

/**
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 5 July 2022 across Wikimedia wikis;
 *   - and have made at least 20 edits between 5 January 2022 and 5 July 2022.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class BV2022PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the bv2022_edits table for the 2022 Board Vote' );

		$this->table = 'bv2022_edits';

		$this->beforeTime = '20220705000000';
		$this->beforeEditsToCount = 300;

		$this->betweenStart = '20220105000000';
		$this->betweenEnd = '20220706000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = BV2022PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
