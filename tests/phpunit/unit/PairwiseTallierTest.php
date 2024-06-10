<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\PairwiseTallier;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PairwiseTallier
 */
class PairwiseTallierTest extends MediaWikiUnitTestCase {
	/** @var Tallier */
	private $tallier;

	protected function setUp(): void {
		$options = array_map( function ( $id, $message ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			$option->method( 'getMessage' )
				->willReturn( $message );
			return $option;
		}, [ 1, 2 ], [ "A", "B" ] );
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( $options );
		$this->tallier = $this->getMockForAbstractClass(
			PairwiseTallier::class,
			[
				$this->createMock( RequestContext::class ),
				$this->createMock( ElectionTallier::class ),
				$question
			]
		);
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PairwiseTallier::factory
	 */
	public function testFactory() {
		$this->assertInstanceOf( PairwiseTallier::class, $this->tallier );
	}

	/**
	 * @dataProvider tallyResults
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PairwiseTallier::convertMatrixToHtml
	 */
	public function testConvertMatrixToHtml( $electionResults, $expected ) {
		$this->tallier->victories = $victories = $electionResults['victories'];
		$this->tallier->optionIds = $rankIds = $electionResults['rankIds'];
		$this->assertSame( $expected['html'], $this->tallier->convertMatrixToHtml( $victories, $rankIds ) );
	}

	/**
	 * @dataProvider tallyResults
	 * @covers \MediaWiki\Extension\SecurePoll\Talliers\PairwiseTallier::convertMatrixToText
	 */
	public function testConvertMatrixToText( $electionResults, $expected ) {
		$this->tallier->victories = $victories = $electionResults['victories'];
		$this->tallier->optionIds = $rankIds = $electionResults['rankIds'];
		$this->assertSame( $expected['text'], $this->tallier->convertMatrixToText( $victories, $rankIds ) );
	}

	public static function tallyResults() {
		return [
			[
				[
					'rankIds' => [
						1 => 1,
						2 => 2
					],
					'victories' => [
						1 => [
							1 => 1,
							2 => 20
						],
						2 => [
							1 => 10,
							2 => 20
						]
					]

				],
				[
					'html' => "<table class=\"securepoll-results\"><tr>\n" .
						"<th>&#160;</th>\n" .
						"<th>A</th>\n" .
						"<th>B</th>\n" .
						"</tr>\n" .
						"<tr>\n" .
						"<td class=\"securepoll-results-row-heading\"> (A)</td><td>1</td>\n" .
						"<td>20</td>\n" .
						"</tr>\n" .
						"<tr>\n" .
						"<td class=\"securepoll-results-row-heading\"> (B)</td><td>10</td>\n" .
						"<td>20</td>\n" .
						"</tr>\n" .
						"</table>",
					'text' => "                | A               | B               | \n" .
						"----------------+-----------------+-----------------+-\n" .
						"A               | 1               | 20              | \n" .
						"B               | 10              | 20              | \n"
				]
			]
		];
	}
}
