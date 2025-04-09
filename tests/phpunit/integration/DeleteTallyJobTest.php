<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Jobs\DeleteTallyJob;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use TestSelectQueryBuilder;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Jobs\DeleteTallyJob
 */
class DeleteTallyJobTest extends MediaWikiIntegrationTestCase {

	private Context $context;

	protected function setUp(): void {
		$this->context = Context::newFromXmlFile( dirname( __DIR__ ) . '/data/election-tests.xml' );
	}

	public function testExecute() {
		$election = $this->context->getElection( 100 );
		$election->saveTallyResult( $this->getDb(), [ 'fake' => 'result' ] );

		$params = [ 'electionId' => $election->getId(), 'tallyId' => 1 ];
		$title = Title::makeTitle( NS_SPECIAL, 'Blank' );

		$job = new DeleteTallyJob( $title, $params );
		$job->setContext( $this->mockContext( $election ) );
		$result = $job->run();

		$this->assertTrue( $result );
		$this->talliesQuery( $election->getId() )->assertFieldValue( json_encode( [] ) );
	}

	public function testExecuteWithMultipleTallies() {
		$election = $this->context->getElection( 100 );
		$election->saveTallyResult( $this->getDb(), [ 'fake' => 'result 1' ] );
		$election->saveTallyResult( $this->getDb(), [ 'fake' => 'result 2' ] );

		$params = [ 'electionId' => $election->getId(), 'tallyId' => 1 ];
		$title = Title::makeTitle( NS_SPECIAL, 'Blank' );

		$job = new DeleteTallyJob( $title, $params );
		$job->setContext( $this->mockContext( $election ) );
		$result = $job->run();

		$this->assertTrue( $result );

		// Don't use assertFieldValue() here as the JSON contains a timestamp.
		$tallies = json_decode( $this->talliesQuery( $election->getId() )->fetchField(), true );
		$this->assertCount( 1, $tallies );
		$this->assertEquals( 2, $tallies[0]['tallyId'] );
		$this->assertEquals( 'result 2', $tallies[0]['result']['fake'] );
	}

	/**
	 * Returns a query that fetches all tally results for an election.
	 */
	private function talliesQuery( int $electionId ): TestSelectQueryBuilder {
		return $this->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [ 'pr_entity' => $electionId, 'pr_key' => 'tally-result' ] )
			->caller( __METHOD__ );
	}

	/**
	 * The job creates a context with a DB store that's needed to delete
	 * tallies, but we also need to access an election stored in our XML
	 * fixtures for testing purposes. Mock the relevant methods to get the best
	 * of both worlds.
	 *
	 * @param Election $election
	 * @return MockObject&Context
	 */
	private function mockContext( Election $election ) {
		$context = $this->createMock( Context::class );
		$context->method( 'getElection' )->willReturn( $election );
		$context->method( 'getDB' )->willReturn( $this->getDb() );

		return $context;
	}
}
