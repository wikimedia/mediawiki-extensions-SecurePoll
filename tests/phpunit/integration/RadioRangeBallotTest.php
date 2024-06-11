<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extension\SecurePoll\Ballots\RadioRangeBallot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Ballots\RadioRangeBallot
 */
class RadioRangeBallotTest extends MediaWikiIntegrationTestCase {
	/** @var Question */
	private $question;

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
		}, [ 1, 2, 3 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );
		$this->question->method( 'getProperty' )->willReturnMap( [
			[ 'min-score', false, -1 ],
			[ 'max-score', false, 1 ]
		] );

		$this->status = new BallotStatus();

		$election = $this->createMock( Election::class );
		$election->method( 'getProperty' )->willReturnMap( [
			[ 'must-answer-all', false, true ],
		] );

		$this->ballot = Ballot::factory(
			new Context(),
			'radio-range',
			$election
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
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot::submitQuestion
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
