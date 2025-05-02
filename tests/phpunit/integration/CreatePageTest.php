<?php
namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use DOMElement;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\CreatePage
 */
class CreatePageTest extends SpecialPageTestBase {
	protected function setUp(): void {
		parent::setUp();

		// Create a mock wiki farm where an additional wiki has SecurePoll enabled.
		$testWikis = [ 'wiki_with_securepoll', 'wiki_without_securepoll' ];
		$testSites = [];

		foreach ( $testWikis as $i => $dbName ) {
			$site = new MediaWikiSite();
			$site->setGlobalId( $dbName );
			$site->setPath( MediaWikiSite::PATH_PAGE, "https://{$i}.example.com/$1" );
			$testSites[] = $site;
		}

		$this->setService( 'SiteLookup', new HashSiteStore( $testSites ) );

		$siteConf = $this->createMock( SiteConfiguration::class );
		$siteConf->method( 'getLocalDatabases' )
			->willReturn( $testWikis );

		$this->setMwGlobals( 'wgConf', $siteConf );
		$this->overrideConfigValue( 'SecurePollExcludedWikis', [ 'wiki_without_securepoll' ] );

		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	/**
	 * @dataProvider provideShowForm
	 */
	public function testShowForm( bool $mayEditOtherWikis ): void {
		// Mock whether global elections should be permitted by configuration.
		$this->overrideConfigValue( 'SecurePollEditOtherWikis', $mayEditOtherWikis );

		[ $html ] = $this->executeSpecialPage(
			'create', null, null, $this->getTestSysop()->getAuthority()
		);

		$doc = DOMUtils::parseHTML( $html );

		if ( $mayEditOtherWikis ) {
			$localAndAllOpts = array_map(
				static fn ( DOMElement $element ) => $element->getAttribute( 'value' ),
				(array)DOMCompat::querySelectorAll(
					$doc,
					'select[name=wpproperty_wiki] > option'
				)
			);
			$otherWikiOpts = array_map(
				static fn ( DOMElement $element ) => $element->getAttribute( 'value' ),
				(array)DOMCompat::querySelectorAll(
					$doc,
					'select[name=wpproperty_wiki] > optgroup[label="(securepoll-create-option-wiki-other_wiki)"]' .
					' > option'
				)
			);

			$this->assertSame(
				[ WikiMap::getCurrentWikiId(), '*' ],
				$localAndAllOpts,
				'Should show options to create an election on the local wiki' .
				' or all wikis if global elections are enabled'
			);
			$this->assertSame(
				[ 'wiki_with_securepoll' ],
				$otherWikiOpts,
				'Should show options to create an election on specific foreign wikis if global elections are enabled'
			);
		} else {
			$this->assertNull(
				DOMCompat::querySelector( $doc, 'select[name=wpproperty_wiki]' ),
				'Wiki selector should not be shown if global elections are disabled'
			);
		}

		$this->assertNotNull(
			DOMCompat::querySelector( $doc, 'input[name=wpelection_title]' ),
			'Should show election title input field'
		);
	}

	public function provideShowForm(): iterable {
		yield 'creating global elections enabled' => [ true ];
		yield 'creating global elections disabled' => [ false ];
	}

	/**
	 * @dataProvider provideValidParams
	 */
	public function testCreateElection( array $params ): void {
		// Mock whether global elections should be permitted by configuration.
		$this->overrideConfigValue( 'SecurePollEditOtherWikis', false );

		$performer = $this->getTestSysop()->getUser();

		$request = new FauxRequest( $params, true, );

		$request->setVal( 'wpproperty_admins', $performer->getName() );

		[ $html ] = $this->executeSpecialPage(
			'create', $request, null, $this->getTestSysop()->getAuthority()
		);

		$election = ( new Context() )->getElectionByTitle( $params['wpelection_title'] );
		$questions = $election->getQuestions();

		$this->assertStringContainsString( '(securepoll-create-created-text)', $html );
		$this->assertSame( $params['wpelection_title'], $election->title );
		$this->assertCount( 1, $questions );
	}

	public static function provideValidParams(): iterable {
		$now = wfTimestamp();
		$tomorrow = wfTimestamp( TS_ISO_8601, $now + 86400 );
		$twoDaysLater = wfTimestamp( TS_ISO_8601, $now + 2 * 86400 );

		$validParams = [
			'wpelection_title' => 'Test Election',
			'wpquestions' => [
				[ 'text' => [ 'Question 1' ], 'options' => [ [ 'text' => 'Option 1' ] ] ]
			],
			'wpelection_startdate' => $tomorrow,
			'wpelection_enddate' => $twoDaysLater,
			'wpelection_type' => 'approval+plurality',
			'wpelection_crypt' => 'none',
			'wpreturn-url' => '',
		];

		yield 'simple election' => [ $validParams ];
		yield 'election with ignored wikis param' => [
			array_merge( $validParams, [
				'wpproperty_wiki' => 'wiki_with_securepoll'
			] )
		];
	}
}
