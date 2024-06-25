<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Ballots\STVBallot;
use MediaWiki\Extension\SecurePoll\DumpElection;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Option;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Talliers\STVTallier
 */
class DumpTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\DumpElection::createXMLDump
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testXmlDump() {
		$election = $this->createMock( Election::class );
		$election->method( 'dumpVotesToCallback' )->willReturn( Status::newGood() );
		$result = DumpElection::createXMLDump( $election );
		$this->assertEquals(
			"<SecurePoll><election></election></SecurePoll>",
			str_replace( "\n", "", $result )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\DumpElection::createBLTDump
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testBltDump() {
		$election = $this->createMock( Election::class );
		$election->ballotType = 'stv';
		$election->title = 'Test Election';

		$option = $this->createMock( Option::class );
		$option->method( 'getId' )->willReturn( "1" );
		$option->method( 'getMessage' )->willReturn( "Candidate 1" );

		$question = $this->createMock( Question::class );
		$questionId = 1;
		$question->method( 'getId' )->willReturn( $questionId );
		$question->method( 'getOptions' )->willReturn( [ $option ] );
		$question->method( 'getProperty' )->with( 'min-seats' )->willReturn( "1" );

		$ballot = $this->createMock( STVBallot::class );
		$ballot->method( 'unpackRecord' )->willReturn( [ $questionId => [ 1 ] ] );

		$election->method( 'getQuestions' )->willReturn( [ $question ] );
		$election->method( 'getBallot' )->willReturn( $ballot );
		$election->method( 'dumpVotesToCallback' )->willReturnCallback(
			static function ( $callback ) use ( $questionId ) {
				$callback( null, (object)[ 'vote_record' => $questionId ] );
			}
		);

		$result = DumpElection::createBltDump( $election );

		$this->assertEquals(
			'1 11 1 00"Candidate 1""Test Election"',
			str_replace( "\n", "", $result )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\DumpElection::createBLTDump
	 *
	 * @return void
	 */
	public function testBltDumpWrongType() {
		$election = $this->createMock( Election::class );
		$election->ballotType = 'foo';
		$this->expectException( \Exception::class );
		DumpElection::createBltDump( $election );
	}
}
