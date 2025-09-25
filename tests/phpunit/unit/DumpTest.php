<?php

namespace MediaWiki\Extension\SecurePoll\Test\Unit;

use MediaWiki\Extension\SecurePoll\DumpElection;
use MediaWiki\Extension\SecurePoll\Entities\Election;
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
}
