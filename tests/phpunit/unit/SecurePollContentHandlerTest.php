<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\SecurePollContentHandler;
use MediaWikiUnitTestCase;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\SecurePollContentHandler
 */
class SecurePollContentHandlerTest extends MediaWikiUnitTestCase {

	public function provideAlphabetizeKeys() {
		return [
			'one layer' =>
				[
					[ 'b' => 2, 'a' => 1 ],
					[ 'a' => 1, 'b' => 2 ],
				],
			'two layers' =>
				[
					[
						'b' => 2,
						'a' => [
							'c' => 3,
							'a' => 1
						]
					],
					[
						'a' => [
							'a' => 1,
							'c' => 3
						],
						'b' => 2
					],
				],
			'three layers' =>
				[
					[
						'b' => 2,
						'a' => [
							'c' => 3,
							'a' => [
								'e' => 5,
								'd' => 4,
							]
						]
					],
					[
						'a' => [
							'a' => [
								'd' => 4,
								'e' => 5,
							],
							'c' => 3,
						],
						'b' => 2,
					],
				],
			'empty array' =>
				[
					[],
					[],
				],
			'single key' =>
				[
					[ 'a' => 1 ],
					[ 'a' => 1 ],
				],
			'boolean values' =>
				[
					[ 'b' => true, 'a' => false ],
					[ 'a' => false, 'b' => true ],
				],
			'no keys' =>
				[
					[ '1', '2' ],
					[ '1', '2' ],
				],
		];
	}

	/**
	 * @dataProvider provideAlphabetizeKeys
	 */
	public function testAlphabetizeKeys( $input, $expected ) {
		$this->assertSame( $expected, SecurePollContentHandler::alphabetizeKeys( $input ) );
	}

	public function provideConvertNonStringsToStrings() {
		return [
			'one layer' =>
				[
					[ 'a' => 1, 'b' => 2 ],
					[ 'a' => '1', 'b' => '2' ],
				],
			'two layers' =>
				[
					[
						'a' => [
							'a' => 1,
							'c' => 3
						],
						'b' => 2
					],
					[
						'a' => [
							'a' => '1',
							'c' => '3'
						],
						'b' => '2'
					],
				],
			'three layers' =>
				[
					[
						'a' => [
							'a' => [
								'd' => 4,
								'e' => 5,
							],
							'c' => 3,
						],
						'b' => 2,
					],
					[
						'a' => [
							'a' => [
								'd' => '4',
								'e' => '5',
							],
							'c' => '3',
						],
						'b' => '2',
					],
				],
			'empty array' =>
				[
					[],
					[],
				],
			'single key' =>
				[
					[ 'a' => 1 ],
					[ 'a' => '1' ],
				],
			'boolean values' =>
				[
					[ 'a' => false, 'b' => true ],
					[ 'a' => '0', 'b' => '1' ],
				],
			'no keys' =>
				[
					[ 1, 2 ],
					[ '1', '2' ],
				],
		];
	}

	/**
	 * @dataProvider provideConvertNonStringsToStrings
	 */
	public function testConvertNonStringsToStrings( $input, $expected ) {
		$this->assertSame( $expected, SecurePollContentHandler::convertNonStringsToStrings( $input ) );
	}

	public function provideDeleteKeysContainingEmptyArrays() {
		return [
			'one layer' =>
				[
					[ 'a' => 1, 'b' => [] ],
					[ 'a' => 1 ],
				],
			'two layers' =>
				[
					[
						'a' => [
							'a' => [],
							'c' => 3
						],
						'b' => []
					],
					[
						'a' => [
							'c' => 3
						],
					],
				],
			'three layers' =>
				[
					[
						'a' => [
							'a' => [
								'd' => [],
								'e' => 5,
							],
							'c' => [],
						],
						'b' => [],
					],
					[
						'a' => [
							'a' => [
								'e' => 5,
							],
						],
					],
				],
			'empty array' =>
				[
					[],
					[],
				],
			'single key' =>
				[
					[ 'a' => [] ],
					[],
				],
			'ignore falsy booleans' =>
				[
					[ 'a' => false, 'b' => true ],
					[ 'a' => false, 'b' => true ],
				],
			'ignore falsy numbers' =>
				[
					[ 'a' => 0, 'b' => 1 ],
					[ 'a' => 0, 'b' => 1 ],
				],
			'ignore falsy strings' =>
				[
					[ 'a' => '', 'b' => 1 ],
					[ 'a' => '', 'b' => 1 ],
				],
			'no keys' =>
				[
					[ 1, 0 ],
					[ 1, 0 ],
				],
		];
	}

	/**
	 * @dataProvider provideDeleteKeysContainingEmptyArrays
	 */
	public function testDeleteKeysContainingEmptyArrays( $input, $expected ) {
		$this->assertSame( $expected, SecurePollContentHandler::deleteKeysContainingEmptyArrays( $input ) );
	}
}
