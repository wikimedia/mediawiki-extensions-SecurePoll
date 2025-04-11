<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use DirectoryIterator;
use Generator;
use MediaWiki\Context\RequestContext;
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
		}, [ 100, 101, 102 ], [ 100, 101, 102 ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );

		$this->tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'droop-quota',
			$this->createMock( ElectionTallier::class ),
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
		$this->assertSame( '19.0000000010', $actual );
	}

	public static function provideFinishTallyResults(): Generator {
		$fixtures = new DirectoryIterator( __DIR__ . '/fixtures' );
		foreach ( $fixtures as $fixture ) {
			if ( $fixture->isFile() && $fixture->isReadable() && $fixture->getExtension() === 'json' ) {
				$name = $fixture->getBasename();
				$decoded = json_decode( file_get_contents( $fixture->getPathname() ), true );
				yield $name => [ $decoded[ 0 ], $decoded[ 1 ], $name ];
			}
		}
	}

	/**
	 * @dataProvider provideFinishTallyResults
	 */
	public function testFinishTally( array $electionResults, array $expected, string $fixtureName = '' ) {
		$this->wrappedRevStore->__set( 'seats', $electionResults['seats'] );
		$this->wrappedRevStore->__set( 'candidates', $electionResults['candidates'] );
		$this->tallier->rankedVotes = $electionResults['rankedVotes'];
		$this->tallier->finishTally();
		$this->assertSame( $expected['elected'], $this->tallier->resultsLog['elected'] );
		$this->assertSame( $expected['eliminated'], $this->tallier->resultsLog['eliminated'] );
		$this->assertSameSize( $expected['rounds'], $this->tallier->resultsLog['rounds'] );
		$this->assertEqualWithPrecisionTolerance(
			$expected['rounds'],
			$this->tallier->resultsLog['rounds'],
			1e-9
		);
	}

	private function assertEqualWithPrecisionTolerance( $expected, $actual, $delta = 1e-9, $path = '' ) {
		foreach ( $expected as $key => $expectedValue ) {
			$this->assertArrayHasKey( $key, $actual, "Missing key '$key' at path '$path'" );
			$actualValue = $actual[$key];

			if ( is_array( $expectedValue ) && is_array( $actualValue ) ) {
				$this->assertEqualWithPrecisionTolerance( $expectedValue, $actualValue, $delta, "$path/$key" );
			} elseif ( is_numeric( $expectedValue ) || is_numeric( $actualValue ) ) {
				$this->assertEqualsWithDelta(
					(float)$expectedValue,
					(float)$actualValue,
					$delta,
					"Mismatch at path '$path/$key'"
				);
			} else {
				$this->assertSame( $expectedValue, $actualValue, "Mismatch at path '$path/$key'" );
			}
		}
	}
}
