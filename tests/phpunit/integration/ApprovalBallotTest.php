<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Integration;

use FauxRequest;
use MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot;
use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot
 */
class ApprovalBallotTest extends MediaWikiIntegrationTestCase {
	/** @var Question */
	private $question;

	/** @var Context */
	private $context;

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
		$this->question->method( 'getOptions' )->willReturn( $options );

		$this->context = new Context;

		$this->ballot = Ballot::factory(
			$this->context,
			'approval',
			$this->createMock( Election::class )
		);
	}

	public function testFactory() {
		$this->assertInstanceOf( ApprovalBallot::class, $this->ballot );
	}

	public static function votesFromRequestContext() {
		return [
			'Request with all valid options' => [
				[
					'securepoll_q_opt1' => 'checked',
					'securepoll_q_opt2' => 'checked',
				],
				'Q00000000-A00000001-y--Q00000000-A00000002-y--'
			],
			'Request with only some options filled' => [
				[
					'securepoll_q_opt2' => 'checked',
				],
				'Q00000000-A00000001-n--Q00000000-A00000002-y--'
			],
			'Request with an invalid option' => [
				[
					'securepoll_q_opt1' => 'checked',
					'securepoll_q_opt2' => 'checked',
					'securepoll_q_optINVALID' => 'checked',
				],
				'Q00000000-A00000001-y--Q00000000-A00000002-y--'
			]
		];
	}

	/**
	 * @dataProvider votesFromRequestContext
	 * @covers \MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot::submitQuestion
	 */
	public function testSubmitQuestion( $votes, $expected ) {
		// Manually set request values
		$this->setRequest( new FauxRequest( $votes ) );

		$this->assertEquals(
			$this->ballot->submitQuestion( $this->question, new BallotStatus( $this->context ) ),
			$expected
		);
	}
}
