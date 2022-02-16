<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use FauxRequest;
use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Ballots\PreferentialBallot;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\PreferentialBallot
 */
class PreferentialBallotTest extends MediaWikiIntegrationTestCase {
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
		}, [ 1, 2 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );

		$this->context = new Context;

		$this->status = new BallotStatus( $this->context );

		$this->election = $this->createMock( Election::class );
		$this->election->method( 'getProperty' )->willReturnMap( [
			[ 'must-rank-all', false, true ],
		] );

		$this->ballot = Ballot::factory(
			$this->context,
			'preferential',
			$this->election
		);
	}

	public function testFactory() {
		$this->assertInstanceOf( PreferentialBallot::class, $this->ballot );
	}

	public static function votesFromRequestContext() {
		return [
			'All valid' => [
				[
					'securepoll_q101_opt1' => 1,
					'securepoll_q101_opt2' => 2,
					'securepoll_q101_opt3' => 3,
				],
				'Q00000065-A00000001-R00000001--Q00000065-A00000002-R00000002--'
			],
			'Out of bounds rank' => [
				[
					'securepoll_q101_opt1' => 1001,
					'securepoll_q101_opt2' => 2,
					'securepoll_q101_opt3' => 3,
				],
				[
					[
						'securepoll-invalid-rank'
					]
				]
			],
			'Unranked required option' => [
				[
					'securepoll_q101_opt1' => 1,
					'securepoll_q101_opt2' => '',
				],
				[
					[
						'securepoll-unranked-options'
					]
				]
			],
			'Number not provided for rank' => [
				[
					'securepoll_q101_opt1' => 'NaN',
					'securepoll_q101_opt2' => 2,
					'securepoll_q101_opt3' => 3,
				],
				[
					[
						'securepoll-invalid-rank'
					]
				]
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
		$this->assertEquals( $result, $expected );
	}
}
