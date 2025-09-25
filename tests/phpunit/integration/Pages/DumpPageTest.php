<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Pages;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\Store\XMLStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Tests\MockDatabase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SpecialPageTestBase;

/**
 * @group SecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Pages\DumpPage
 */
class DumpPageTest extends SpecialPageTestBase {
	use MockAuthorityTrait;

	private const ADMIN_PERMISSIONS = [
		'editinterface',
		'securepoll-create-poll',
		'securepoll-edit-poll',
		'securepoll-view-voter-pii',
	];

	/** @var MockObject&Context */
	private $context;

	/**
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();

		$this->context = $this->getMockContext();
		$this->importXmlFile( dirname( __DIR__ ) . '/../data/stv-test.xml' );
	}

	public function testXmlDump(): void {
		// Create an election and inject it into the special page's context.
		$electionId = 70;
		$election = $this->getMockElection( $electionId, '5_3_100.blt.json' );
		$this->context->method( 'getElection' )->willReturn( $election );

		// Return a valid tally time as that's how the special page determines
		// if an election has been tallied.
		$election->method( 'isTallied' )->willReturn( true );

		// Generate an XML dump using the dump subpage.
		[ $output ] = $this->executeSpecialPage(
			'dump/' . $electionId,
			new FauxRequest( [ 'uselang' => 'en', 'format' => 'xml' ] ),
			'en',
			$this->mockRegisteredNullAuthority()
		);

		$expected = <<<EOD
		<SecurePoll>
		<election>
		<configuration>
		<title>STV test</title>
		<ballot>stv</ballot>
		<tally>droop-quota</tally>
		<primaryLang>en</primaryLang>
		<startDate>2009-07-19T00:00:00Z</startDate>
		<endDate>2019-08-10T00:00:00Z</endDate>
		<id>70</id>
		<property name="admins">Tim</property>
		<message name="title" lang="en">STV test</message>
		<message name="intro" lang="en">STV test intro</message>
		<message name="jump-text" lang="en">STV test jump text</message>
		<message name="return-text" lang="en">STV test return text</message>
		<auth>local</auth>
		<question>
		<id>71</id>
		<message name="text" lang="en">STV test question</message>
		<option>
		<id>72</id>
		<message name="text" lang="en">1</message>
		</option>
		<option>
		<id>73</id>
		<message name="text" lang="en">2</message>
		</option>
		<option>
		<id>77</id>
		<message name="text" lang="en">3</message>
		</option>
		<option>
		<id>75</id>
		<message name="text" lang="en">5</message>
		</option>
		<option>
		<id>75</id>
		<message name="text" lang="en">5</message>
		</option>
		</question>
		</configuration>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000002-R00000001--Q00000047-C00000001-R00000002--
		</vote>
		<vote>
		Q00000047-C00000003-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000002-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000001-R00000001--Q00000047-C00000002-R00000002--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000003-R00000000--
		</vote>
		<vote>
		Q00000047-C00000001-R00000000--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--
		</vote>
		<vote>
		Q00000047-C00000002-R00000000--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--
		</vote>
		</election>
		</SecurePoll>

		EOD;
		$this->assertEquals( $expected, $output );
	}

	public function testDumpIsBlockedBeforeTally(): void {
		// Create an election and inject it into the special page's context.
		$electionId = 70;
		$election = $this->getMockElection( $electionId, '5_3_100.blt.json' );
		$this->context->method( 'getElection' )->willReturn( $election );

		// Don't return a tally time from the database.
		$election->method( 'isTallied' )->willReturn( false );

		// Generate a ballot dump using the dump subpage.
		[ $output ] = $this->executeSpecialPage(
			'dump/' . $electionId,
			new FauxRequest( [ 'uselang' => 'en' ] ),
			'en',
			$this->mockRegisteredNullAuthority()
		);

		$expected = <<<EOD
		<p>Election records are only available after the results have been tallied.
		</p>
		EOD;

		$this->assertEquals( $expected, $output );
	}

	public function testDumpAllowsAdminsBeforeTally(): void {
		// Create an election and inject it into the special page's context.
		$electionId = 70;
		$election = $this->getMockElection( $electionId, '5_3_100.blt.json' );
		$this->context->method( 'getElection' )->willReturn( $election );

		// Don't return a tally time from the database.
		$election->method( 'isTallied' )->willReturn( false );

		// Generate a dump using the dump subpage.
		[ $output ] = $this->executeSpecialPage(
			'dump/' . $electionId,
			new FauxRequest( [ 'uselang' => 'en' ] ),
			'en',
			$this->mockElectionAuthority()
		);

		$expected = <<<EOD
		<SecurePoll>
		<election>
		<configuration>
		<title>STV test</title>
		<ballot>stv</ballot>
		<tally>droop-quota</tally>
		<primaryLang>en</primaryLang>
		<startDate>2009-07-19T00:00:00Z</startDate>
		<endDate>2019-08-10T00:00:00Z</endDate>
		<id>70</id>
		<property name="admins">Tim</property>
		<message name="title" lang="en">STV test</message>
		<message name="intro" lang="en">STV test intro</message>
		<message name="jump-text" lang="en">STV test jump text</message>
		<message name="return-text" lang="en">STV test return text</message>
		<auth>local</auth>
		<question>
		<id>71</id>
		<message name="text" lang="en">STV test question</message>
		<option>
		<id>72</id>
		<message name="text" lang="en">1</message>
		</option>
		<option>
		<id>73</id>
		<message name="text" lang="en">2</message>
		</option>
		<option>
		<id>77</id>
		<message name="text" lang="en">3</message>
		</option>
		<option>
		<id>75</id>
		<message name="text" lang="en">5</message>
		</option>
		<option>
		<id>75</id>
		<message name="text" lang="en">5</message>
		</option>
		</question>
		</configuration>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000002-R00000001--Q00000047-C00000001-R00000002--
		</vote>
		<vote>
		Q00000047-C00000003-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000002-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000001-R00000001--Q00000047-C00000002-R00000002--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000002-R00000001--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--Q00000047-C00000001-R00000001--
		</vote>
		<vote>
		Q00000047-C00000003-R00000000--
		</vote>
		<vote>
		Q00000047-C00000001-R00000000--
		</vote>
		<vote>
		Q00000047-C00000005-R00000000--
		</vote>
		<vote>
		Q00000047-C00000002-R00000000--
		</vote>
		<vote>
		Q00000047-C00000004-R00000000--
		</vote>
		</election>
		</SecurePoll>

		EOD;

		$this->assertEquals( $expected, $output );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialPage {
		$factory = MediaWikiServices::getInstance()->getService( 'SecurePoll.ActionPageFactory' );
		$page = new SpecialSecurePoll( $factory );
		$page->sp_context = $this->context;
		return $page;
	}

	/**
	 * Returns a mocked context with mocked database methods.
	 *
	 * @return MockObject&Context
	 */
	private function getMockContext() {
		$context = $this->getMockBuilder( Context::class )
			->onlyMethods( [ 'getDB', 'getElection' ] )
			->getMock();

		$context->method( 'getDB' )->willReturn( new MockDatabase() );

		return $context;
	}

	/**
	 * Returns an election from the store with mocked database methods.
	 *
	 * @return MockObject&Election
	 */
	private function getMockElection( int $electionId, string $fixture ) {
		$info = $this->context->getStore()->getElectionInfo( [ $electionId ] );

		/** @var MockObject&Election */
		$election = $this->getMockBuilder( Election::class )
			->onlyMethods( [ 'isTallied', 'dumpVotesToCallback' ] )
			->setConstructorArgs( [ $this->context, $info[$electionId] ] )
			->getMock();

		// Get vote data from the fixtures and mock the database function used
		// to retrieve votes from the database to return this data instead.
		$rankedVotes = $this->getFixture( $fixture )[0]['rankedVotes'];
		$votes = $this->createMockVotes(
			$election->getId(),
			$election->getQuestions()[0]->getId(),
			$rankedVotes
		);
		$election->method( 'dumpVotesToCallback' )
			->willReturnCallback( static function ( $callback ) use ( $election, $votes ) {
				foreach ( $votes as $vote ) {
					$callback( $election, $vote );
				}
				return Status::newGood();
			} );

		return $election;
	}

	/**
	 * Read election votes and results from the fixtures directory.
	 */
	private function getFixture( string $filename ): array {
		$path = dirname( __DIR__ ) . '/../unit/fixtures/' . $filename;

		$contents = file_get_contents( $path );
		if ( !$contents ) {
			throw new RuntimeException( "Cannot read fixture file at: {$path}" );
		}

		$fixture = json_decode( $contents, true );
		if ( !$fixture ) {
			throw new RuntimeException( "Cannot parse JSON fixture at: {$path}" );
		}

		return $fixture;
	}

	/**
	 * Imports elections from an XML file.
	 *
	 * The static method Context::newFromXmlFile is not used since using the
	 * mocked context with it is not possible.
	 */
	private function importXmlFile( string $filename ): void {
		$store = new XMLStore( $filename );
		$this->context->setStore( $store );

		$result = $store->readFile();
		if ( !$result ) {
			throw new RuntimeException( "Cannot read XML file at: {$filename}" );
		}
	}

	/**
	 * Mocks vote database rows using fixture data.
	 */
	private function createMockVotes( int $electionId, int $questionId, array $rankedVotes ): array {
		$rows = [];
		$voteId = 1;

		foreach ( array_values( $rankedVotes ) as $rankedVote ) {
			$voteRecord = '';
			foreach ( $rankedVote['rank'] as $rank => $choice ) {
				$voteRecord .= sprintf(
					'Q%08X-C%08X-R%08X--',
					$questionId,
					$choice,
					intval( $rank ) - 1
				);
			}

			$rows[] = (object)[
				'vote_id' => $voteId++,
				'vote_election' => $electionId,
				'vote_voter' => 1,
				'vote_voter_name' => 'Admin',
				'vote_voter_domain' => 'localhost:8081',
				'vote_struck' => 0,
				'vote_record' => $voteRecord,
				'vote_ip' => 'AC120001',
				'vote_xff' => '',
				'vote_ua' => 'Mozilla/5.0 (X11; Linux ppc64le; rv:78.0) Gecko/20100101 Firefox/78.0',
				'vote_timestamp' => date( 'YmdHis' ),
				'vote_current' => 1,
				'vote_token_match' => 1,
				'vote_cookie_dup' => 0,
			];
		}

		return $rows;
	}

	/**
	 * Mock an election admin authority with an identity whose name matches the
	 * admin name used in all of the test data.
	 */
	private function mockElectionAuthority(): Authority {
		return new SimpleAuthority( new UserIdentityValue( 9999, 'Tim' ), self::ADMIN_PERMISSIONS );
	}
}
