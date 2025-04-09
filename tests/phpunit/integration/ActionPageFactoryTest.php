<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\ActionPageFactory;
use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWikiIntegrationTestCase;

/**
 * @group SpecialPage
 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory
 */
class ActionPageFactoryTest extends MediaWikiIntegrationTestCase {
	protected function getFactory() {
		return $this->getServiceContainer()->getService( 'SecurePoll.ActionPageFactory' );
	}

	protected function getSpecialPage() {
		return new SpecialSecurePoll( $this->getFactory() );
	}

	public function testServiceCreation() {
		$factory = $this->getFactory();
		$this->assertInstanceOf( ActionPageFactory::class, $factory );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getPageList
	 */
	public function testGetNames() {
		$factory = $this->getFactory();
		$pages = $factory->getPageList();
		$this->assertIsArray( $pages );
		$this->assertContains( 'create', array_keys( $pages ) );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getPage
	 */
	public function testMakingValidPages() {
		$specialPage = $this->getSpecialPage();
		$factory = $this->getFactory();
		$names = $factory->getPageList();
		foreach ( $names as $pageName => $pageProps ) {
			if ( !isset( $pageProps['pattern'] ) ) {
				$page = $factory->getPage( $pageName, $specialPage );
				$this->assertInstanceOf( ActionPage::class, $page );
			}
		}
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getPage
	 */
	public function testMakingInvalidPages() {
		$specialPage = $this->getSpecialPage();
		$factory = $this->getFactory();
		$subpage = $factory->getPage( 'asdfghjkl', $specialPage );
		$this->assertNull( $subpage, "Invalid page name results in null return" );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getPage
	 */
	public function testMakingValidPagesWithPatterns() {
		$specialPage = $this->getSpecialPage();
		$factory = $this->getFactory();
		$names = $factory->getPageList();
		$page = $factory->getPage( 'tallies/1/result/1', $specialPage );
		$this->assertInstanceOf( ActionPage::class, $page );
	}
}
