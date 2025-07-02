<?php

/**
 * Rules from U4C directly.
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 6 March 2025 across Wikimedia wikis;
 *   - and have made at least 20 edits between 6 September 2024 and 6 March 2025.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class UcocAR2025PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the securepoll_ucocar2025_edits table for the UCoC annual review' );
		$this->table = 'securepoll_ucocar2025_edits';
		$this->beforeTime = '20250306000000';
		$this->beforeEditsToCount = 300;
		$this->betweenStart = '20240906000000';
		$this->betweenEnd = '20250306000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = UcocAR2025PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
