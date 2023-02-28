<?php

/**
 * Rules from https://meta.wikimedia.org/wiki/Universal_Code_of_Conduct/Revised_enforcement_guidelines/Voter_information#General_rule
 * You may vote from any one registered account you own on a Wikimedia wiki.
 * You may only vote once, regardless of how many accounts you own.
 * To qualify, this one account must:
 *   - not be blocked in more than one project;
 *   - and not be a bot;
 *   - and have made at least 300 edits before 3 January 2023 across Wikimedia wikis;
 *   - and have made at least 20 edits between 3 July 2022 and 3 January 2023.
 */

require_once dirname( __DIR__ ) . '/PopulateEditCount.php';

use MediaWiki\Extension\SecurePoll\PopulateEditCount;

class UCOC2023PopulateEditCount extends PopulateEditCount {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates the ucoc2023_edits table for the 2023 UCoC vote' );

		$this->table = 'ucoc2023_edits';

		$this->beforeTime = '20230104000000';
		$this->beforeEditsToCount = 300;

		$this->betweenStart = '20220703000000';
		$this->betweenEnd = '20230104000000';
		$this->betweenEditsToCount = 20;
	}
}

$maintClass = UCOC2023PopulateEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
