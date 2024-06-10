<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extension\SecurePoll\Ballots\PreferentialBallot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Ballots\PreferentialBallot
 */
class PreferentialBallotTest extends MediaWikiIntegrationTestCase {
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
		}, [ 1, 2 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );

		$this->status = new BallotStatus();

		$election = $this->createMock( Election::class );
		$election->method( 'getProperty' )->willReturnMap( [
			[ 'must-rank-all', false, true ],
		] );

		$this->ballot = Ballot::factory(
			new Context(),
			'preferential',
			$election
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
