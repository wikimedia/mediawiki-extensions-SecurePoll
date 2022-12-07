<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use FauxRequest;
use MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot
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
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot::submitQuestion
	 */
	public function testSubmitQuestion( $votes, $expected ) {
		$this->ballot->initRequest(
			new FauxRequest( $votes ),
			new RequestContext,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);

		$this->assertEquals(
			$expected,
			$this->ballot->submitQuestion( $this->question, new BallotStatus( $this->context ) )
		);
	}
}
