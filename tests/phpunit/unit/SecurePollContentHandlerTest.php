<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\SecurePollContentHandler;
use MediaWikiUnitTestCase;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\SecurePollContentHandler
 */
class SecurePollContentHandlerTest extends MediaWikiUnitTestCase {

	public function provideNormalizeData() {
		return [
			'one layer' =>
				[
					[ 'b' => 2, 'a' => 1 ],
					[ 'a' => '1', 'b' => '2' ],
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
							'a' => '1',
							'c' => '3'
						],
						'b' => '2'
					],
				],
			'three layers' =>
				[
					[
						'b' => 2,
						'c' => [],
						'a' => [
							'c' => 3,
							'a' => [
								'e' => 5,
								'd' => 4,
								'f' => [],
							]
						]
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
			'key with empty array' =>
				[
					[ 'b' => 2, 'a' => [] ],
					[ 'b' => '2' ],
				],
			'boolean values' =>
				[
					[ 'b' => true, 'a' => false ],
					[ 'a' => '0', 'b' => '1' ],
				],
			'falsy numbers' =>
				[
					[ 'a' => 0, 'b' => 1 ],
					[ 'a' => '0', 'b' => '1' ],
				],
			'falsy strings' =>
				[
					[ 'a' => '', 'b' => 1 ],
					[ 'a' => '', 'b' => '1' ],
				],
			'no keys' =>
				[
					[ '1', '2' ],
					[ '1', '2' ],
				],
		];
	}

	/**
	 * @dataProvider provideNormalizeData
	 */
	public function testNormalizeData( $input, $expected ) {
		$this->assertSame( $expected, SecurePollContentHandler::normalizeDataForPage( $input, true ) );
	}

	public function testNormalizeDataNoOuterSort() {
		$this->assertSame(
			[
				'b' => '2',
				'a' => [
					'c' => '1',
					'd' => 'e'
				]
			],
			SecurePollContentHandler::normalizeDataForPage(
				[
					'b' => 2,
					'a' => [
						'd' => 'e',
						'c' => 1
					]
				]
			)
		);
	}
}
