<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Unit;

use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWiki\Extensions\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\STVTallier;
use MediaWiki\Extensions\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @group SecurePoll
 * @covers MediaWiki\Extensions\SecurePoll\Talliers\STVTallier
 */
class STVTallierTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		// Tallier constructor requires getOptions to return iterable
		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 100, 101, 102 ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'droop-quota',
			$this->createMock( ElectionTallier::class ),
			$question
		);
	}

	public static function resultsFromTally() {
		return [
			'No edge cases' => [
				[
					[ 100, 101 ],
					[ 102 ],
					[ 102 ],
					[ 102 ],
					[ 100, 101, 102 ],
					[ 100, 101, 102 ],
					[ 102, 100, 101 ],
					[ 100, 102, 101 ]
				],
				[
					'rankedVotes' => [
						'100_101' => [
							'count' => 1,
							'rank' => [
								1 => 100,
								2 => 101
							]
						],
						'102' => [
							'count' => 3,
							'rank' => [
								1 => 102
							]
						],
						'100_101_102' => [
							'count' => 2,
							'rank' => [
								1 => 100,
								2 => 101,
								3 => 102
							]
						],
						'102_100_101' => [
							'count' => 1,
							'rank' => [
								1 => 102,
								2 => 100,
								3 => 101
							]
						],
						'100_102_101' => [
							'count' => 1,
							'rank' => [
								1 => 100,
								2 => 102,
								3 => 101
							]
						]
					]
				]
			]
		];
	}

	public function testFactory() {
		$this->assertInstanceOf( STVTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider resultsFromTally
	 * @covers \MediaWiki\Extensions\SecurePoll\Talliers\STVTallier::addVote
	 */
	public function testAddVote( $electionResults, $expected ) {
		foreach ( $electionResults as $record ) {
			$this->tallier->addVote( $record );
		}
		$this->assertArrayEquals(
			$this->tallier->rankedVotes,
			$expected['rankedVotes']
		);
	}

	/**
	 * @covers \MediaWiki\Extensions\SecurePoll\Talliers\STVTallier::calculateDroopQuota
	 */
	public function testCalculateDroopQuota() {
		$actual = TestingAccessWrapper::newFromObject( $this->tallier )->calculateDroopQuota( 57, 2 );
		$this->assertSame( 19.000001000000001, $actual );
	}

}
