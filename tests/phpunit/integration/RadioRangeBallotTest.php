<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use FauxRequest;
use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Ballots\RadioRangeBallot;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\RadioRangeBallot
 */
class RadioRangeBallotTest extends MediaWikiIntegrationTestCase {
	/** @var Question */
	private $question;

	/** @var Context */
	private $context;

	/** @var BallotStatus */
	private $status;

	/** @var Election */
	private $election;

	/** @var Ballot */
	private $ballot;

	protected function setUp(): void {
		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 1, 2, 3 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );
		$this->question->method( 'getProperty' )->willReturnMap( [
			[ 'min-score', false, -1 ],
			[ 'max-score', false, 1 ]
		] );

		$this->context = new Context;

		$this->status = new BallotStatus( $this->context );

		$this->election = $this->createMock( Election::class );
		$this->election->method( 'getProperty' )->willReturnMap( [
			[ 'must-answer-all', false, true ],
		] );

		$this->ballot = Ballot::factory(
			$this->context,
			'radio-range',
			$this->election
		);
	}

	public function testFactory() {
		$this->assertInstanceOf( RadioRangeBallot::class, $this->ballot );
	}

	public static function votesFromRequestContext() {
		return [
			'Valid inputs' => [
				[
					'securepoll_q101_opt1' => -1,
					'securepoll_q101_opt2' => 0,
					'securepoll_q101_opt3' => 1,
				],
				'Q00000065' .
				'-A00000001-S-0000000001--Q00000065-A00000002-S+0000000000--Q00000065-A00000003-S+0000000001--',
			],
			'Score out of bounds' => [
				[
					'securepoll_q101_opt1' => -1000,
					'securepoll_q101_opt2' => 0,
					'securepoll_q101_opt3' => 1,
				],
				[
					[
						'securepoll-invalid-score',
						'−1',
						'1',
					]
				],
			],
			'Unanswered question' => [
				[
					'securepoll_q101_opt1' => -1,
					'securepoll_q101_opt3' => 1,
				],
				[
					[
						'securepoll-unanswered-options',
					]
				],
			],
			'Non-numeric score' => [
				[
					'securepoll_q101_opt1' => 'NaN',
					'securepoll_q101_opt2' => 0,
					'securepoll_q101_opt3' => 1,
				],
				[
					[
						'securepoll-invalid-score',
						'−1',
						'1',
					]
				],
			],
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
}
