<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Unit;

use MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot;
use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @covers MediaWiki\Extensions\SecurePoll\Ballots\ApprovalBallot
 */
class ApprovalBallotTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 1, 2 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getOptions' )->willReturn( $options );

		// Request values will get stubbed in tests
		$this->context = new RequestContext;

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
		foreach ( $votes as $option => $answer ) {
			$this->context::getMain()->getRequest()->setVal( $option, $answer );
		}

		$this->assertEquals( $this->ballot->submitQuestion( $this->question, BallotStatus::class ), $expected );

		// Unset values when done
		foreach ( $votes as $option => $answer ) {
			$this->context::getMain()->getRequest()->unsetVal( $option );
		}
	}
}
