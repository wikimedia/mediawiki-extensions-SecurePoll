<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use DirectoryIterator;
use Generator;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\STVTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVTallier
 */
class STVTallierTest extends MediaWikiUnitTestCase {
	/** @var Tallier */
	private $tallier;
	/** @var Tallier */
	private $wrappedRevStore;

	protected function setUp(): void {
		// Tallier constructor requires getOptions to return iterable
		$options = array_map( function ( $id, $message ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			$option->method( 'getMessage' )
				->willReturn( $message );
			return $option;
		}, [ 100, 101, 102, 103, 104 ], [ 100, 101, 102, 103, 104 ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );

		// 100-102 used in normal results, 103 used to test exclusion
		$election = $this->createMock( Election::class );
		$election->method( 'getProperty' )->willReturn( serialize( [
			'stv-candidate-excluded' => [
				100 => false,
				101 => false,
				102 => false,
				103 => true
			]
		] ) );

		$electionTallier = $this->createMock( ElectionTallier::class );
		$electionTallier->election = $election;

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'droop-quota',
			$electionTallier,
			$question
		);
		$this->wrappedRevStore = TestingAccessWrapper::newFromObject( $this->tallier );
	}

	public static function provideTallyResults() {
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
			],
			'All candidates were manually eliminated' => [
				[
					[ 103 ]
				],
				[
					'rankedVotes' => []
				]
				],
			'Manually eliminated candidate changes winner' => [
				[
					[ 103, 104 ]
				],
				[
					'rankedVotes' => [
						'104' => [
							'count' => 1,
							'rank' => [
								1 => 104
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
	 * @dataProvider provideTallyResults
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVTallier::addVote
	 */
	public function testAddVote( array $electionResults, array $expected ) {
		foreach ( $electionResults as $record ) {
			$this->tallier->addVote( $record );
		}
		$this->assertSame( $expected['rankedVotes'], $this->tallier->rankedVotes );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVTallier::calculateDroopQuota
	 */
	public function testCalculateDroopQuota() {
		$actual = TestingAccessWrapper::newFromObject( $this->tallier )->calculateDroopQuota( 57, 2 );
		$this->assertSame( '19.0000010000', $actual );
	}

	public static function provideFinishTallyResults(): Generator {
		$fixtures = new DirectoryIterator( __DIR__ . '/fixtures' );
		foreach ( $fixtures as $fixture ) {
			if ( $fixture->isFile() && $fixture->isReadable() && $fixture->getExtension() === 'json' ) {
				yield json_decode( file_get_contents( $fixture->getPathname() ), true );
			}
		}
	}

	/**
	 * @dataProvider provideFinishTallyResults
	 */
	public function testFinishTally( array $electionResults, array $expected ) {
		$this->wrappedRevStore->__set( 'seats', $electionResults['seats'] );
		$this->wrappedRevStore->__set( 'candidates', $electionResults['candidates'] );
		$this->tallier->rankedVotes = $electionResults['rankedVotes'];
		$this->tallier->finishTally();
		$this->assertSame( $expected['elected'], $this->tallier->resultsLog['elected'] );
		$this->assertSame( $expected['eliminated'], $this->tallier->resultsLog['eliminated'] );
		$this->assertSameSize( $expected['rounds'], $this->tallier->resultsLog['rounds'] );
		$this->assertEquals( $expected['rounds'], $this->tallier->resultsLog['rounds'] );
	}
}
