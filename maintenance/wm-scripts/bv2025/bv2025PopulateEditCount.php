<?php

/**
 * Rules: https://w.wiki/EHkR
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not currently be blocked globally;
 *   - not be site-wide blocked in more than one public project;
 *   - not currently be locked;
 *   - not be a bot;
 *   - have made at least 300 edits before July 28, 2025 across Wikimedia wikis; and
 *   - have made at least 20 edits between August 28, 2024 and July 28, 2025.
 */

// @codeCoverageIgnoreStart
require_once dirname( __DIR__ ) . '/PopulateEditCount.php';
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class Bv2025PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the securepoll_bv2025_edits table for the 2025 BoT election vote' );
		$this->table = 'securepoll_bv2025_edits';
		$this->beforeTime = '20250728000000';
		$this->beforeEditsToCount = 300;
		$this->betweenStart = '20240828000000';
		$this->betweenEnd = '20250728000000';
		$this->betweenEditsToCount = 20;
	}
}

// @codeCoverageIgnoreStart
$maintClass = Bv2025PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
