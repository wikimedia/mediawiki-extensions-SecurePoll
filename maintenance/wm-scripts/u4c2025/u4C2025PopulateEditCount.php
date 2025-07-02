<?php

/**
 * Rules from https://w.wiki/EJij
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 * - not be site-wide blocked in more than one public project;
 * - and not be a bot;
 * - and have made at least 300 edits before 13 May 2025 across Wikimedia wikis;
 * - and have made at least 20 edits between 13 May 2024 and 13 May 2025.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class U4C2025PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the securepoll_u4c2025_edits table for the U4C election' );
		$this->table = 'securepoll_u4c2025_edits';
		$this->beforeTime = '20250513000000';
		$this->beforeEditsToCount = 300;
		$this->betweenStart = '20240513000000';
		$this->betweenEnd = '20250513000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = U4C2025PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
