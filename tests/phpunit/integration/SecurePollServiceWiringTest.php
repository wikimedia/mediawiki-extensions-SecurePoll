<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php
 * used to test the ServiceWiring.php file.
 */

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWikiIntegrationTestCase;

/**
 * Cannot cover anything in phpunit as phpunit does not
 * support covering files. ServiceWiring.php does not have
 * a class or function to cover.
 *
 * @coversNothing
 */
class SecurePollServiceWiringTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
