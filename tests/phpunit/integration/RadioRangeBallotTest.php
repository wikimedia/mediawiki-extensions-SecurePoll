<?php

declare( strict_types=1 );

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
use OOUI\BlankTheme;
use OOUI\FieldsetLayout;
use OOUI\Theme;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;

/**
 * @group Database
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

	public static function provideVotesFromRequestContext() {
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
	 * @dataProvider provideVotesFromRequestContext
	 * @covers \MediaWiki\Extension\SecurePoll\Ballots\ApprovalBallot::submitQuestion
	 */
	public function testSubmitQuestion( $votes, $expected ) {
		$this->ballot->initRequest(
			new FauxRequest( $votes ),
			new RequestContext(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);

		// submitQuestion returns the record if successful or otherwise writes to the status
		$result = $this->ballot->submitQuestion( $this->question, $this->status );
		if ( count( $this->status->getErrorsArray() ) ) {
			$result = $this->status->getErrorsArray();
		}

		$this->assertEquals( $expected, $result );
	}

	public function testGetQuestionForm(): void {
		Theme::setSingleton( new BlankTheme() );

		$this->ballot->initRequest(
			new FauxRequest( [ 'securepoll_q101_opt123' => 1 ] ),
			new RequestContext(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);

		// submitQuestion returns the record if successful or otherwise writes to the status
		$questionForm = $this->ballot->getQuestionForm(
			$this->question,
			[ new Option( $this->ballot->context, [ 'id' => 123, 'election' => 12 ] ) ]
		);
		$this->assertInstanceOf( FieldsetLayout::class, $questionForm );

		$actualHtml = $questionForm->toString();
		$fieldsetHtml = $this->assertAndGetByElementClass( $actualHtml, 'securepoll_q101' );

		$questionHtml = $this->assertAndGetByElementClass( $fieldsetHtml, 'securepoll_q101_opt123' );
		$questionHtmlDocument = DOMUtils::parseHTML( $questionHtml );

		$checkedOption = DOMCompat::querySelectorAll(
			$questionHtmlDocument,
			'input[checked][name=securepoll_q101_opt123][value=1]'
		);
		$this->assertCount( 1, $checkedOption, 'Could not find pre-checked question' );

		$notCheckedOption = DOMCompat::querySelectorAll(
			$questionHtmlDocument,
			'input[name=securepoll_q101_opt123][value=0]'
		);
		$this->assertCount( 1, $notCheckedOption, 'Could not find not checked question' );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}
}
