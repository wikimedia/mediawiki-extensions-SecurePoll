<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extension\SecurePoll\Ballots\STVBallot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use OOUI\FieldsetLayout;
use RequestContext;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Ballots\STVBallot
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

	/** @var array */
	private $options;

	protected function setUp(): void {
		$this->options = array_map( function ( $id ) {
			$option = $this->createMock( Option::class );
			$option->method( 'getId' )
				->willReturn( $id );
			return $option;
		}, [ 1, 2, 3, 4 ] );
		$this->question = $this->createMock( Question::class );
		$this->question->method( 'getId' )->willReturn( 101 );
		$this->question->method( 'getOptions' )->willReturn( $this->options );

		$this->context = new Context;

		$this->status = new BallotStatus();

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

	/**
	 * @dataProvider packedRecords
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot::unpackRecord
	 */
	public function testUnpackRecord( $record, $expected ) {
		$this->assertEquals( $expected, $this->ballot->unpackRecord( $record ) );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\STVBallot::getQuestionForm
	 * @dataProvider votesFromRequestContext
	 */
	public function testGetQuestionForm( $votes ): void {
		$stvBallot = new STVBallot( $this->context, $this->createMock( Election::class ) );
		$stvBallot->initRequest(
			new FauxRequest( $votes ),
			new RequestContext,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);
		$questionForm = $stvBallot->getQuestionForm( $this->question, $this->options );

		// Make sure that $questionForm returns a 'fieldset' as the tag name
		// since it's a FieldSetLayout which overrides Element::$tagName.
		$this->assertSame( 'fieldset', $questionForm->getTagName() );
		$this->assertInstanceOf( FieldsetLayout::class, $questionForm );

		// TODO: Test the structure of the form below.
		//       The main part to assert is the limit seats feature.
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\STVBallot::getCreateDescriptors
	 */
	public function testGetCreateDescriptorsWithLimitSeat(): void {
		$descriptors = STVBallot::getCreateDescriptors();

		// Assert that the limit-seats question is added to the STVBallot form.
		$this->assertArrayHasKey( 'limit-seats', $descriptors['question'] );
	}
}
