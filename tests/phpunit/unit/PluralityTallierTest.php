<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Unit;

use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWiki\Extensions\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\PluralityTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @group SecurePoll
 * @covers MediaWiki\Extensions\SecurePoll\Talliers\PluralityTallier
 */
class PluralityTallierTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		// Tallier constructor requires getOptions to return iterable
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )
			->willReturn( [] );

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'plurality',
			$this->createMock( ElectionTallier::class ),
			$question
		);
	}

	public static function resultsFromTally() {
		return [
			// No tie
			[
				[
					'results' => [
						'Q1' => 1,
						'Q2' => 2,
					]
				],
				[
					'results' => [
						'Q2' => 2,
						'Q1' => 1,
					]
				]
			],
			// Tie
			[
				[
					'results' => [
						'Q1' => 0,
						'Q2' => 0,
					]
				],
				[
					'results' => [
						'Q1' => 0,
						'Q2' => 0,
					]
				]
			]
		];
	}

	public function testFactory() {
		$this->assertInstanceOf( PluralityTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider resultsFromTally
	 * @covers \MediaWiki\Extensions\SecurePoll\Talliers\PluralityTallier::finishTally
	 */
	public function testPluralityTally( $electionResults, $expected ) {
		$this->tallier->tally = $electionResults;
		$this->tallier->finishTally();
		$this->assertArrayEquals(
			$expected,
			$this->tallier->getJSONResult()
		);
	}
}
