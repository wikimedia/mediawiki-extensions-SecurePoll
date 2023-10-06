<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\SchulzeTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\SchulzeTallier
 */
class SchulzeTallierTest extends MediaWikiUnitTestCase {
	/** @var Tallier */
	private $tallier;

	protected function setUp(): void {
		// Tallier constructor requires getOptions to return iterable
		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 101, 102 ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );

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
					[
						101 => 1,
						102 => 2,
					],
					[
						101 => 1,
						102 => 2,
					]
				],
				[
					'victories' => [
						101 => [
							101 => 0,
							102 => 2
						],
						102 => [
							101 => 0,
							102 => 0
						]
					],
					'ranks' => [
						101 => 1,
						102 => 2
					],
					'strengths' => [
						101 => [
							102 => [ 2, 0 ]
						],
						102 => [
							101 => [ 0, 0 ]
						]
					]
				]
			],
			'Results contain a tie' => [
				[
					[
						101 => 1,
						102 => 2,
					],
					[
						101 => 2,
						102 => 1,
					]
				],
				[
					'victories' => [
						101 => [
							101 => 0,
							102 => 1
						],
						102 => [
							101 => 1,
							102 => 0
						]
					],
					'ranks' => [
						101 => 1,
						102 => 1
					],
					'strengths' => [
						101 => [
							102 => [ 0, 0 ]
						],
						102 => [
							101 => [ 0, 0 ]
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
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\SchulzeTallier::finishTally
	 */
	public function testSchulzeTally( $electionResults, $expected ) {
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
