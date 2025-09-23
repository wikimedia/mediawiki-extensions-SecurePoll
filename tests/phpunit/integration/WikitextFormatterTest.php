<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class WikitextFormatterTest extends MediaWikiIntegrationTestCase {
	private WikitextFormatter $wikitextFormatter;
	private array $elected;
	private array $eliminated;

	protected function setUp(): void {
		$resultsLog = [
			'elected' => [ "101" ],
			'eliminated' => [],
			'rounds' => [
				[
					'round' => 1,
					'surplus' => 0,
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
					'surplus' => 0,
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
		$rankedVotes = [
			[
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
			100 => 'User3',
			103 => 'User4',
			104 => 'User5'
		];
		$this->wikitextFormatter = new WikitextFormatter( $resultsLog, $rankedVotes, 3, $candidates, [], '' );
		$this->elected = [
			1 => '102',
			2 => '101',
		];
		$this->eliminated = [
			1 => "100",
			2 => "103",
			3 => "104",
		];
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatPreamble
	 */
	public function testFormatPreamble() {
		$actualPreamble = $this->wikitextFormatter->formatPreamble( $this->elected, $this->eliminated, [] );

		$expectedPreamble = "==Elected==
Election for 3 seats with 5 candidates. Total 2 votes.
* ''This seat could not be filled because no candidates fulfill the criteria. "
		. "The last eliminated candidates were: User3, User4, User5''
* User1
* User2
==Eliminated/Not elected==
* User3
* User4
* User5";
		$this->assertEquals( $expectedPreamble, $actualPreamble );
		$this->assertStringNotContainsString( 'manually excluded from the tally', $actualPreamble );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatPreamble
	 */
	public function testFormatPreambleWithManuallyExcludedCandidate() {
		$excludedCandidate = [
			101 => true
		];
		$wikitextFormatter = new WikitextFormatter( [ 'rounds' => [] ], [], 1, [ 101 => 'Foo' ], $excludedCandidate, '' );
		$result = $wikitextFormatter->formatPreamble( [], [], $excludedCandidate );
		$expectedElimination = "==Eliminated/Not elected==
* Foo";
		$this->assertStringContainsString( $expectedElimination, $result );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatRoundsPreamble
	 */
	public function testFormatRoundsPreamble() {
		$actualPreamble = $this->wikitextFormatter->formatRoundsPreamble();
		$expectedPreamble = <<<HERE

==Rounds table==
{| class="wikitable"
!Round Number
!Tally
!Result
HERE;
		$this->assertEquals( $expectedPreamble, $actualPreamble );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatRound
	 */
	public function testFormatRound() {
		$actualPreamble = $this->wikitextFormatter->formatRound();
		$expectedPreamble = <<<HERE

|-
|1
|
*<s>User3: 0</s>
*User2: 0
*User1: 0
|Quota: 2
Elected: User1, User2
Eliminated: User3
|-
|2
|
*User2: 1 + 1 = 2
*<s>User1: 1 (keep factor: 3)</s>
|Quota: 1
Elected: User2
Eliminated: User1
Transferring votes
HERE;
		$this->assertEquals( $expectedPreamble, $actualPreamble );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatBlt
	 */
	public function testFormatBlt() {
		$wikitextFormatter = new WikitextFormatter( [ 'rounds' => [] ], [], 1, [ 101 => 'Foo' ], [], 'BLT' );
		$actualBlt = $wikitextFormatter->formatBlt();
		$expectedBlt = <<<HERE
==BLT==
<pre>
BLT
</pre>
HERE;
		$this->assertEquals( $expectedBlt, $actualBlt );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVFormatter\WikitextFormatter::formatBlt
	 */
	public function testFormatBltNoBltPresent() {
		$actualBlt = $this->wikitextFormatter->formatBlt();
		$expectedBlt = '';
		$this->assertEquals( $expectedBlt, $actualBlt );
	}
}
