<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use FauxRequest;
use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Ballots\STVBallot;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\STVBallot
 */
class STVBallotTest extends MediaWikiIntegrationTestCase {
	/** @var Question */
	private $question;

	/** @var Context */
	private $context;

	/** @var BallotStatus */
	private $status;

	/** @var Ballot */
	private $ballot;

	protected function setUp(): void {
		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 1, 2, 3, 4 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );

		$this->context = new Context;

		$this->status = new BallotStatus( $this->context );

		$this->ballot = Ballot::factory(
			$this->context,
			'stv',
			$this->createMock( Election::class )
		);
	}

	public function testFactory() {
		$this->assertInstanceOf( STVBallot::class, $this->ballot );
	}

	public static function votesFromRequestContext() {
		return [
			'All valid inputs' => [
				[
					'securepoll_q101_opt0' => 2,
					'securepoll_q101_opt1' => 4,
					'securepoll_q101_opt2' => 1,
					'securepoll_q101_opt3' => 3,
				],
				'Q00000065-C00000002-R00000000--Q00000065-C00000004-R00000001--' .
				'Q00000065-C00000001-R00000002--Q00000065-C00000003-R00000003--'
			],
			'No inputs' => [
				[],
				[
					[
						'securepoll-stv-invalid-rank-empty'
					]
				]
			],
			'Not sequentially ranked' => [
				[
					'securepoll_q101_opt0' => 1,
					'securepoll_q101_opt1' => 3,
					'securepoll_q101_opt2' => 0,
					'securepoll_q101_opt3' => 2,
				],
				[
					[
						'securepoll-stv-invalid-input-empty',
					],
					[
						'securepoll-stv-invalid-rank-order',
						'Preference 3'
					]
				]
			],
			'Duplicate ranks' => [
				[
					'securepoll_q101_opt0' => 1,
					'securepoll_q101_opt1' => 1,
					'securepoll_q101_opt2' => 1,
				],
				[
					[
						'securepoll-stv-invalid-input-duplicate'
					],
					[
						'securepoll-stv-invalid-input-duplicate'
					],
					[
						'securepoll-stv-invalid-rank-duplicate',
						'Preference 2, Preference 3'
					]
				]
			]
		];
	}

	public static function packedRecords() {
		return [
			'Valid record, all options ranked' => [
				'Q00000065-C00000002-R00000000--Q00000065-C00000004-R00000001--' .
				'Q00000065-C00000001-R00000002--Q00000065-C00000003-R00000003--',
				[
					101 => [ 2, 4, 1, 3 ]
				]
			],
			'Valid record, some options ranked' => [
				'Q00000065-C00000002-R00000000--Q00000065-C00000004-R00000001--',
				[
					101 => [ 2, 4 ]
				]
			],
			'Invalid record' => [
				'Q00000065-C',
				false
			]
		];
	}

	/**
	 * @dataProvider votesFromRequestContext
	 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot::submitQuestion
	 */
	public function testSubmitQuestion( $votes, $expected ) {
		$this->ballot->initRequest(
			new FauxRequest( $votes ),
			new RequestContext,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);

		// submitQuestion returns the record if successful or otherwise writes to the status
		$result = $this->ballot->submitQuestion( $this->question, $this->status );
		if ( count( $this->status->getErrorsArray() ) ) {
			$result = $this->status->getErrorsArray();
		}
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @dataProvider packedRecords
	 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot::unpackRecord
	 */
	public function testUnpackRecord( $record, $expected ) {
		$this->assertEquals( $expected, $this->ballot->unpackRecord( $record ) );
	}
}
