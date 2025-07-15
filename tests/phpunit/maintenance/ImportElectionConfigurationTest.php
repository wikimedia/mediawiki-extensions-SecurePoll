<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use MediaWiki\Extension\SecurePoll\Ballots\RadioRangeBallot;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Maintenance\ImportElectionConfiguration;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Maintenance\ImportElectionConfiguration
 */
class ImportElectionConfigurationTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return ImportElectionConfiguration::class;
	}

	public function testCanImportXmlDataWithoutOwner(): void {
		$this->maintenance->loadWithArgv( [ __DIR__ . '/../data/3way-test.xml' ] );
		$this->maintenance->execute();

		$context = new Context();
		$election = $context->getElectionByTitle( 'Radio range test 1' );
		$questions = $election->getQuestions();

		$this->assertInstanceOf( Election::class, $election );
		$this->assertSame( 'Radio range test 1', $election->title );
		$this->assertSame( '0', $election->owner );

		$this->assertInstanceOf( RadioRangeBallot::class, $election->getBallot() );
		$this->assertCount( 1, $questions );

		foreach ( $questions as $question ) {
			$this->assertCount( 4, $question->getOptions() );
		}
	}
}
