<?php

namespace MediaWiki\Extensions\SecurePoll\Test\Unit;

use MediaWiki\Extensions\SecurePoll\Ballots\Ballot;
use MediaWiki\Extensions\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extensions\SecurePoll\Ballots\ChooseBallot;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Option;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @covers MediaWiki\Extensions\SecurePoll\Ballots\ChooseBallot
 */
class ChooseBallotTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		$options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 1 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $options );

		// Request values will get stubbed in tests
		$this->context = new RequestContext;

		$this->status = new BallotStatus( $this->context );

		$this->ballot = Ballot::factory(
			$this->context,
			'choose',
			$this->createMock( Election::class )
		);
	}

	public function testFactory() {
		$this->assertInstanceOf( ChooseBallot::class, $this->ballot );
	}

	public static function votesFromRequestContext() {
		return [
			'Request with valid answer' => [
				[
					'securepoll_q101' => '1',
				],
				'Q00000065A00000001'
			],
			'Request with both valid and invalid question' => [
				[
					'securepoll_q101' => 1,
					'securepoll_q102' => 1,
				],
				'Q00000065A00000001'
			],
			'Request with invalid answer' => [
				[
					'securepoll_q101' => 'no',
				],
				[
					[
						'securepoll-unanswered-questions'
					]
				]
			],
			'Request with unanswered question' => [
				[],
				[
					[
						'securepoll-unanswered-questions'
					]
				]
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

		// submitQuestion returns the record if successful or otherwise writes to the status
		$result = $this->ballot->submitQuestion( $this->question, $this->status );
		if ( count( $this->status->getErrorsArray() ) ) {
			$result = $this->status->getErrorsArray();
		}
		$this->assertEquals( $result, $expected );

		// Unset values when done
		foreach ( $votes as $option => $answer ) {
			$this->context::getMain()->getRequest()->unsetVal( $option );
		}
	}
}
