<?php
namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use ImportElectionConfiguration;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Database
 * @covers ImportElectionConfiguration
 */
class ImportTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return ImportElectionConfiguration::class;
	}

	public function testCanImportXmlDataWithoutOwner(): void {
		$this->maintenance->loadWithArgv( [ __DIR__ . '/../data/3way-test.xml' ] );
		$this->maintenance->execute();

		$context = new Context();
		$election = $context->getElectionByTitle( 'Radio range test 1' );

		$this->assertInstanceOf( Election::class, $election );
		$this->assertSame( 'Radio range test 1', $election->title );
		$this->assertSame( '0', $election->owner );
	}
}
