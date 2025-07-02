<?php

/**
 * Rules: https://w.wiki/AVcm
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be site-wide blocked in more than one public project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 4 August 2024 across Wikimedia wikis;
 *   - and have made at least 20 edits between 6 September 2022 and 4 August 2024.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class Bv2024PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the bv2024_edits table for the 2024 bot election vote' );
		$this->table = 'bv2024_edits';
		$this->beforeTime = '20240804000000';
		$this->beforeEditsToCount = 300;
		$this->betweenStart = '20220906000000';
		$this->betweenEnd = '20240804000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = Bv2024PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
