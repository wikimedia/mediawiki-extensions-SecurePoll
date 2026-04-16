<?php

/**
 * Rules: https://w.wiki/LJWb
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 24 April 2026 across Wikimedia wikis;
 *   - and have made at least 20 edits between 24 October 2025 and 24 April 2026.
 */

// @codeCoverageIgnoreStart
require_once dirname( __DIR__ ) . '/PopulateEditCount.php';
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class Ucoc2026PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the securepoll_ucoc2026_edits table for the UCoC annual review and U4C elections' );
		$this->table = 'securepoll_ucoc2026_edits';
		$this->beforeTime = '20260424000000';
		$this->beforeEditsToCount = 300;
		$this->betweenStart = '20251024000000';
		$this->betweenEnd = '20260424000000';
		$this->betweenEditsToCount = 20;
	}
}

// @codeCoverageIgnoreStart
$maintClass = Ucoc2026PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
