<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\HtmlForm\HTMLFormRadioRangeColumnLabels;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Request\WebRequest;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\HtmlForm\HTMLFormRadioRangeColumnLabels
 */
class HTMLFormRadioRangeColumnLabelsTest extends MediaWikiUnitTestCase {

	private function newField( array $extraParams = [] ): HTMLFormRadioRangeColumnLabels {
		return new HTMLFormRadioRangeColumnLabels( [
			'fieldname' => 'col-msgs',
			'name' => 'col-msgs',
			'parent' => $this->createMock( HTMLForm::class ),
		] + $extraParams );
	}

	private function newRequest( ?array $values ): WebRequest {
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getArray' )
			->with( 'col-msgs' )
			->willReturn( $values );
		return $request;
	}

	/**
	 * @dataProvider provideLoadDataFromRequest
	 */
	public function testLoadDataFromRequest( array $values, array $expected ): void {
		$field = $this->newField();
		$request = $this->newRequest( $values );

		$this->assertSame( $expected, $field->loadDataFromRequest( $request ) );
	}

	public static function provideLoadDataFromRequest(): array {
		return [
			'int keys with a negative get a +-prefix on positives' => [
				'values' => [ -1 => 'foo', 0 => 'bar', 1 => 'baz' ],
				'expected' => [ 'column-1' => 'foo', 'column0' => 'bar', 'column+1' => 'baz' ],
			],
			'non-negative-only keys keep no +-prefix' => [
				'values' => [ 0 => 'a', 1 => 'b' ],
				'expected' => [ 'column0' => 'a', 'column1' => 'b' ],
			],
			'non-numeric keys are ignored' => [
				'values' => [ 'text' => 'descr', 0 => 'a' ],
				'expected' => [ 'column0' => 'a' ],
			],
		];
	}

	public function testLoadDataFromRequestReturnsDefaultWhenMissing(): void {
		$field = $this->newField( [ 'default' => [ 'columnDefault' => 'x' ] ] );
		$request = $this->newRequest( null );

		$this->assertSame( [ 'columnDefault' => 'x' ], $field->loadDataFromRequest( $request ) );
	}
}
