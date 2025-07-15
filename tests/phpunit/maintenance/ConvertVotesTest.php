<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use DOMDocument;
use MediaWiki\Extension\SecurePoll\Maintenance\ConvertVotes;
use MediaWiki\Extension\SecurePoll\Maintenance\ImportElectionConfiguration;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Maintenance\ConvertVotes
 */
class ConvertVotesTest extends MaintenanceBaseTestCase {
	private const THREE_WAY_WITH_VOTES_TEST_ID = 50;

	protected function getMaintenanceClass() {
		return ConvertVotes::class;
	}

	/**
	 * @dataProvider provideOutputOptions
	 */
	public function testShouldOutputElectionData( bool $fromXml ): void {
		$xmlFile = __DIR__ . '/../data/3way-with-votes-test.xml';

		if ( $fromXml ) {
			$this->maintenance->loadWithArgv( [ $xmlFile ] );
		} else {
			$import = $this->maintenance->createChild( ImportElectionConfiguration::class );
			$import->loadWithArgv( [ $xmlFile ] );

			// Ignore output from import.php.
			ob_start();
			$import->execute();
			ob_end_clean();

			$this->maintenance->loadParamsAndArgs( null, [ 'name' => 'Radio range test 1' ] );
			$this->importVotesForTest( $xmlFile );
		}

		$this->maintenance->execute();

		$this->expectOutputString(
			<<<OUTPUT
RR1 test question
1. AAA
2. B
3. C
4. D
-1, 0, 1, 0

OUTPUT
		);
	}

	/**
	 * Convenience function to import votes from an XML dump file into the database.
	 * @param string $xmlFile
	 * @return void
	 */
	private function importVotesForTest( string $xmlFile ): void {
		$testUserId = 4;
		$voteRecords = [];
		$now = wfTimestampNow();

		$doc = new DOMDocument();
		$doc->loadXML( file_get_contents( $xmlFile ) );

		$votes = $doc->getElementsByTagName( 'vote' );

		foreach ( $votes as $vote ) {
			$voteRecords[] = [
				'vote_election' => self::THREE_WAY_WITH_VOTES_TEST_ID,
				'vote_voter' => $testUserId++,
				'vote_voter_name' => 'Test Voter',
				'vote_voter_domain' => 'testwiki',
				'vote_record' => $vote->textContent,
				'vote_ip' => '',
				'vote_xff' => '',
				'vote_ua' => '',
				'vote_timestamp' => $now,
				'vote_current' => 1,
				'vote_token_match' => 1,
				'vote_struck' => 0,
				'vote_cookie_dup' => 0,
			];
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'securepoll_votes' )
			->rows( $voteRecords )
			->caller( __METHOD__ )
			->execute();
	}

	public static function provideOutputOptions(): iterable {
		yield 'from an XML file' => [ true ];
		yield 'from a stored election' => [ false ];
	}
}
