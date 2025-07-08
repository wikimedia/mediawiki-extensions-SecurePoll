<?php
namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use DOMElement;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\CreatePage
 * @covers \MediaWiki\Extension\SecurePoll\SecurePollContentHandler
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

	public static function provideShowForm(): iterable {
		yield 'creating global elections enabled' => [ true ];
		yield 'creating global elections disabled' => [ false ];
	}

	/**
	 * @dataProvider provideValidParams
	 * @param bool $useNamespace Whether to store election data in read-only log pages
	 * @param array $params Election options
	 */
	public function testCreateElection( bool $useNamespace, array $params ): void {
		$this->overrideConfigValues( [
			'SecurePollUseNamespace' => $useNamespace,
			// Mock whether global elections should be permitted by configuration.
			'SecurePollEditOtherWikis' => false
		] );

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

		$logPage = $this->getServiceContainer()
			->getPageStore()
			->getPageByName( NS_SECUREPOLL, (string)$election->getId() );

		if ( $useNamespace ) {
			$content = $this->getServiceContainer()
				->getRevisionLookup()
				->getKnownCurrentRevision( $logPage, $logPage->getLatest() )
				->getContentOrThrow( SlotRecord::MAIN );

			$parsedContentStatus = $content->getData();
			$parsedContent = $parsedContentStatus->getValue();

			$this->assertInstanceOf( JsonContent::class, $content );
			$this->assertStatusGood( $parsedContentStatus );

			$this->assertSame(
				$election->getId(),
				$parsedContent->id,
				'Election ID in log page should match stored election ID'
			);

			$this->assertCount( 1, $parsedContent->questions );
			$this->assertCount( 1, $parsedContent->questions[0]->options );

			foreach ( $election->getQuestions() as $i => $question ) {
				$this->assertSame(
					$question->getId(),
					$parsedContent->questions[$i]->id,
					'Question IDs in log page should match stored question ID'
				);
				foreach ( $question->getOptions() as $j => $option ) {
					$this->assertSame(
						$option->getId(),
						$parsedContent->questions[$i]->options[$j]->id,
						'Option IDs in log page should match stored option ID'
					);
				}
			}

		} else {
			$this->assertNull(
				$logPage,
				'No log page should be created if $wgSecurePollUseNamespace = false'
			);
		}
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

		yield 'simple election' => [ false, $validParams ];
		yield 'election with ignored wikis param' => [
			false,
			array_merge( $validParams, [
				'wpproperty_wiki' => 'wiki_with_securepoll'
			] )
		];
		yield 'simple election with logging to namespace enabled' => [ true, $validParams ];
	}
}
