<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\HistogramRangeTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\HistogramRangeTallier
 */
class HistogramRangeTallierTest extends MediaWikiUnitTestCase {
	/** @var Tallier */
	private $tallier;

	protected function setUp(): void {
		// Tallier constructor requires getOptions to return iterable
		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 100, 101 ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );
		$question->method( 'getProperty' )->willReturnMap( [
			[ 'min-score', false, -1 ],
			[ 'max-score', false, 1 ]
		] );

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'histogram-range',
			$this->createMock( ElectionTallier::class ),
			$question
		);
	}

	public static function resultsFromTally() {
		return [
			[
				[
					[
						100 => -1,
						101 => -1,
					],
					[
						100 => 1,
						101 => 1,
					],
					[
						100 => 0,
						101 => 1,
					],
					[
						100 => 1,
						101 => 1,
					],
					[
						100 => 1,
						101 => 1,
					]
				],
				[
					'averages' => [
						100 => 0.4,
						101 => 0.6
					],
					'histogram' => [
						100 => [
							-1 => 1,
							0 => 1,
							1 => 3
						],
						101 => [
							-1 => 1,
							0 => 0,
							1 => 4
						],
					]
				]
			],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\HistogramRangeTallier::factory
	 */
	public function testFactory() {
		$this->assertInstanceOf( HistogramRangeTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider resultsFromTally
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\HistogramRangeTallier::finishTally
	 */
	public function testHistogramRangeTally( $electionResults, $expected ) {
		foreach ( $electionResults as $record ) {
			$this->tallier->addVote( $record );
		}
		$this->tallier->finishTally();
		$this->assertArrayEquals(
			$expected,
			$this->tallier->getJSONResult()
		);
	}
}
