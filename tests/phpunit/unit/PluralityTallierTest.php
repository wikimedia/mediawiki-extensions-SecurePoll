<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\PluralityTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PluralityTallier
 */
class PluralityTallierTest extends MediaWikiUnitTestCase {
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
					[
						101 => 1
					],
					[
						101 => 1
					],
					[
						102 => 1
					]
				],
				[
					101 => 2,
					102 => 1
				]
			],
			// Tie
			[
				[
					[
						101 => 1
					],
					[
						102 => 1
					]
				],
				[
					101 => 1,
					102 => 1
				]
			],
		];
	}

	public function testFactory() {
		$this->assertInstanceOf( PluralityTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider resultsFromTally
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PluralityTallier::finishTally
	 */
	public function testPluralityTally( $electionResults, $expected ) {
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
