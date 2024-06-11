<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Ballots\BallotStatus;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Ballots\Ballot
 */
class BallotTest extends MediaWikiUnitTestCase {
	private function getAbstractBallot( $election = null ) {
		$election = $election ?? $this->createMock( Election::class );

		return $this->getMockForAbstractClass(
			Ballot::class,
			[
				$this->createMock( RequestContext::class ),
				$election,
			]
		);
	}

	public function testGetForm() {
		$prevStatus = $this->createMock( BallotStatus::class );
		$prevStatus->method( 'spGetHTML' )->willReturn( '' );

		$option = $this->createMock( Option::class );

		$question = $this->createMock( Question::class );
		$question->method( 'getOptions' )->willReturn( [ $option ] );
		$question->method( 'parseMessage' )->willReturn( '' );

		$election = $this->createMock( Election::class );
		$election->method( 'getQuestions' )->willReturn( [ $question ] );

		$ballot = $this->getAbstractBallot( $election );
		$ballot->method( 'getQuestionForm' )
			->willReturn( $this->createMock( \OOUI\FieldsetLayout::class ) );

		$form = $ballot->getForm();
		$this->assertIsArray( $form );
		$this->assertIsArray( $ballot->getForm( false ) );
		$this->assertIsArray( $ballot->getForm( $prevStatus ) );
		$this->assertCount( 1, $form );
		$this->assertInstanceOf( \OOUI\Element::class, $form[0] );
	}
}
