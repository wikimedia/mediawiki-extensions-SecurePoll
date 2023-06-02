<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use InvalidArgumentException;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use MediaWiki\Extension\SecurePoll\Talliers\PluralityTallier;
use MediaWiki\Extension\SecurePoll\Talliers\Tallier;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\Tallier
 */
class TallierTest extends MediaWikiUnitTestCase {
	private function getAbstractTallier() {
		// Tallier constructor requires getOptions to return iterable
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )
			->willReturn( [] );

		return $this->getMockForAbstractClass(
			Tallier::class,
			[
				$this->createMock( RequestContext::class ),
				$this->createMock( ElectionTallier::class ),
				$question,
			]
		);
	}

	public function testFactoryValidType() {
		// Tallier constructor requires getOptions to return iterable
		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )
			->willReturn( [] );

		$tallier = Tallier::factory(
			$this->createMock( RequestContext::class ),
			'plurality',
			$this->createMock( ElectionTallier::class ),
			$question
		);
		$this->assertInstanceOf( PluralityTallier::class, $tallier );
	}

	public function testFactoryInvalidType() {
		$this->expectException( InvalidArgumentException::class );
		Tallier::factory(
			$this->createMock( RequestContext::class ),
			'invalid',
			$this->createMock( ElectionTallier::class ),
			$this->createMock( Question::class )
		);
	}

	public function testConvertRanksToHtml() {
		$ranks = [
			0 => 1,
			1 => 2,
			2 => 3,
			3 => 4,
		];

		// parseMessage is called on each option
		$options = array_map( function ( $value ) {
			$option = $this->createMock( Option::class );
			$option->method( 'parseMessage' )
				->willReturn( $value );
			return $option;
		}, [ 'A', 'C', 'B', 'D' ] );

		$tallier = $this->getAbstractTallier();
		$tallier->optionsById = $options;

		$html = "<table class=\"securepoll-table\"><tr><td>1</td><td>A</td></tr>\n" .
			"<tr><td>2</td><td>C</td></tr>\n" .
			"<tr><td>3</td><td>B</td></tr>\n" .
			"<tr><td>4</td><td>D</td></tr>\n" .
			"</table>";

		$this->assertSame( $html, $tallier->convertRanksToHtml( $ranks ) );
	}

	public function testConvertRanksToText() {
		$ranks = [
			0 => 1,
			1 => 2,
			2 => 3,
			3 => 4,
		];

		// getMessage is called on each option
		$options = array_map( function ( $value ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getMessage' )
				->willReturn( $value );
			return $option;
		}, [ 'A', 'C', 'B', 'D' ] );

		$tallier = $this->getAbstractTallier();
		$tallier->optionsById = $options;

		$text = "1      | A\n" .
			"2      | C\n" .
			"3      | B\n" .
			"4      | D\n";

		$this->assertSame( $text, $tallier->convertRanksToText( $ranks ) );
	}

}
