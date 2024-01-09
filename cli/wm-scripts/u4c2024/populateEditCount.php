<?php

/**
 * Rules from https://w.wiki/8mrm
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 16 December 2023 across Wikimedia wikis;
 *   - and have made at least 20 edits between 16 June 2023 and 16 December 2023.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class U4C2024PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the u4c2024_edits table for the 2024 U4C Charter vote' );

		$this->table = 'u4c2024_edits';

		$this->beforeTime = '20231216000000';
		$this->beforeEditsToCount = 300;

		$this->betweenStart = '20230616000000';
		$this->betweenEnd = '20231216000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = U4C2024PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
