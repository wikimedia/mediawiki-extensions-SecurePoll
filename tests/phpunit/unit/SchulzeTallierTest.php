<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Unit;

use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWiki\Extensions\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\SchulzeTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @group SecurePoll
 * @covers MediaWiki\Extensions\SecurePoll\Talliers\SchulzeTallier
 */
class SchulzeTallierTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		// Tallier constructor requires getOptions to return iterable
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )
			->willReturn( [] );

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'schulze',
			$this->createMock( ElectionTallier::class ),
			$question
		);
	}

	public static function resultsFromTally() {
		return [
			'Results contain no ties' => [
				[
					'optionIds' => [ 1, 2 ],
					'victories' => [
						1 => [
							1 => 0,
							2 => 1
						],
						2 => [
							1 => 0,
							2 => 0
						]
					]
				],
				[
					'victories' => [
						1 => [
							1 => 0,
							2 => 1
						],
						2 => [
							1 => 0,
							2 => 0
						]
					],
					'ranks' => [
						1 => 1,
						2 => 2
					],
					'strengths' => [
						1 => [
							2 => [ 1, 0 ]
						],
						2 => [
							1 => [ 0, 0 ]
						]
					]
				]
			],
			'Results contain a tie' => [
				[
					'optionIds' => [ 1, 2 ],
					'victories' => [
						1 => [
							1 => 0,
							2 => 1
						],
						2 => [
							1 => 1,
							2 => 0
						]
					]
				],
				[
					'victories' => [
						1 => [
							1 => 0,
							2 => 1
						],
						2 => [
							1 => 1,
							2 => 0
						]
					],
					'ranks' => [
						1 => 1,
						2 => 1
					],
					'strengths' => [
						1 => [
							2 => [ 0, 0 ]
						],
						2 => [
							1 => [ 0, 0 ]
						]
					]
				]
			]
		];
	}

	public function testFactory() {
		$this->assertInstanceOf( SchulzeTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider resultsFromTally
	 * @covers \MediaWiki\Extensions\SecurePoll\Talliers\SchulzeTallier::finishTally
	 */
	public function testSchulzeTally( $electionResults, $expected ) {
		$this->tallier->optionIds = $electionResults['optionIds'];
		$this->tallier->victories = $electionResults['victories'];
		$this->tallier->finishTally();
		$this->assertArrayEquals(
			$expected,
			$this->tallier->getJSONResult()
		);
	}
}
