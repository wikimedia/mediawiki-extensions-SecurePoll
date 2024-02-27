<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\HtmlFormatter;
use MediaWikiIntegrationTestCase;

class HtmlFormatterTest extends MediaWikiIntegrationTestCase {
	private HtmlFormatter $htmlFormatter;
	private array $elected;
	private array $eliminated;

	protected function setUp(): void {
		$resultsLog = [
			'elected' => [ "101" ],
			'eliminated' => [],
			'rounds' => [
				[
					'round' => 1,
					'surplus' => 1,
					'rankings' => [
						"102" => [
							'total' => 0,
							'votes' => 1
						],
						"101" => [
							'total' => 0,
							'votes' => 2
						],
						"100" => [
							'total' => 0,
							'votes' => 0
						]
					],
					'totalVotes' => 3,
					'keepFactors' => [
						"102" => 3,
						"101" => 2 ],
					'quota' => 2,
					'elected' => [ "102", "101" ],
					'eliminated' => [ "100" ]
				],
				[
					'round' => 2,
					'surplus' => 2,
					'rankings' => [
						"102" => [
							'total' => 1,
							'votes' => 1
						],
						"101" => [
							'total' => 2,
							'votes' => 1
						]
					],
					'totalVotes' => 2,
					'keepFactors' => [
						"102" => 3,
						"101" => 2 ],
					'quota' => 1,
					'elected' => [ "101" ],
					'eliminated' => [ "102" ]
				]
			]
		];
		$rankedVotes = [ [
			'count' => 1,
			'rank' => [
				1
			]
			],
			[
				'count' => 1,
				'rank' => [
					1
				]
			],
		];
		$candidates = [
			102 => 'User1',
			101 => 'User2',
			100 => 'User3'
		];
		$this->htmlFormatter = new HtmlFormatter( $resultsLog, $rankedVotes, 3, $candidates );
		$this->elected = [
			1 => '101',
			2 => '102',
		];
		$this->eliminated = [
			1 => "100"
		];
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\HtmlFormatter::formatRoundsPreamble
	 */
	public function testFormatRoundsPreamble() {
		$actualPreamble = $this->htmlFormatter->formatRoundsPreamble();
		$expectedPreamble =
			"<div class='oo-ui-layout oo-ui-panelLayout'><h2>Rounds table</h2><p>The following table describes "
			. "the calculations that happened in order to achieve the result above. In each round of calculation, "
			. "the candidate(s) who achieved more votes than the quota are declared elected. Their surplus votes "
			. "above the quota are redistributed to the remaining candidates. If nobody achieves the quota, the "
			. "lowest ranking candidate is eliminated and their votes are redistributed to the remaining candidates. "
			. "To understand this better, please refer to <a rel=\"nofollow\" class=\"external text\" "
			. "href=\"https://web.archive.org/web/20210225045400/https://prfound.org/resources/reference/"
			. "reference-meek-rule/\">this link</a>.</p></div>";
		$this->assertEquals( $expectedPreamble, $actualPreamble->toString() );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\HtmlFormatter::formatPreamble
	 */
	public function testFormatPreamble() {
		$actualPreamble = $this->htmlFormatter->formatPreamble( $this->elected, $this->eliminated );
		$expectedPreamble =
			"<div class='oo-ui-layout oo-ui-panelLayout'><h2>Elected</h2><p>Election for 3 seats with 3 candidates. "
			. "Total 2 votes.</p><ol class='election-summary-elected-list'><li><i>This seat could not "
			. "be filled because no candidates fulfill the criteria. The last eliminated candidates were: 102"
			. "</i></li><li>User2</li><li>User1</li></ol><h2>Eliminated/Not elected</h2>"
			. "<ul><li>User1</li><li>User3</li></ul></div>";
		$this->assertEquals( $expectedPreamble, $actualPreamble->toString() );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\HtmlFormatter::formatRound
	 */
	public function testFormatRound() {
		$actualPreamble = $this->htmlFormatter->formatRound();
		$expectedPreamble =
			"<table class='wikitable'><thead><tr><th>Round Number</th><th>Tally</th><th>Result"
			. "</th></tr></thead><tbody>"
			. "<tr><td>1</td><td><ol class='round-summary-tally-list'><li><s>"
			. "<span class='round-summary-candidate-name'>User3: </span><span class='round-summary-candidate-votes'"
			. ">0</span></s></li><li class='round-candidate-elected'><span class='round-summary-candidate-name'>User2: "
			. "</span><span class='round-summary-candidate-votes'>0</span></li><li class='round-candidate-elected'>"
			. "<span class='round-summary-candidate-name'>User1: </span><span class='round-summary-candidate-votes'>"
			. "0</span></li></ol></td><td>Quota: 2<br />Elected: User1, User2<br />Eliminated: User3<br /></td>"
			. "</tr><tr><td>2</td><td><ol class='round-summary-tally-list'><li class='round-candidate-elected'>"
			. "<span class='round-summary-candidate-name'>User2: </span>"
			. "<span class='round-summary-candidate-votes'>1 + 1 = 2</span></li>"
			. "<li><s class='previously-elected'><span class='round-summary-candidate-name'>"
			. "User1: </span><span class='round-summary-candidate-votes'>1 (keep factor: 3)</span></s></li>"
			. "</ol></td><td>Quota: 1<br />Elected: User2<br />Eliminated: User1<br />"
			. "Transferring votes<br /></td></tr></tbody></table>";
		$this->assertEquals( $expectedPreamble, $actualPreamble->toString() );
	}
}
