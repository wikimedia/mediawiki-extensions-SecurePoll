<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests SQlite compatibility.
 *
 * @covers Nothing
 *
 * @group Database
 * @group Medium
 * @group sqlite
 */
class SqliteTest extends TestCase {
	public function testSqliteCompatility() {
		if ( !Sqlite::isPresent() ) {
			$this->markTestSkipped( 'SQLite is not present, skipping SQLite compatibility test.' );
		}

		$res = Sqlite::checkSqlSyntax( __DIR__ . '/../../SecurePoll.sql' );

		$this->assertTrue( $res, "Not compatible with SQLite. Given error $res" );
	}
}
