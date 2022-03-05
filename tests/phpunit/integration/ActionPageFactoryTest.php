<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\ActionPageFactory;
use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @group SpecialPage
 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory
 */
class ActionPageFactoryTest extends MediaWikiIntegrationTestCase {
	protected function getFactory() {
		return MediaWikiServices::getInstance()->getService( 'SecurePoll.ActionPageFactory' );
	}

	protected function getSpecialPage() {
		return new SpecialSecurePoll( $this->getFactory() );
	}

	public function testServiceCreation() {
		$factory = $this->getFactory();
		$this->assertInstanceOf( ActionPageFactory::class, $factory );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getNames
	 */
	public function testGetNames() {
		$factory = $this->getFactory();
		$names = $factory->getNames();
		$this->assertIsArray( $names );
		$this->assertContains( 'create', $names );
	}

	/**
	 * @covers \MediaWiki\Extension\SecurePoll\ActionPageFactory::getPage
	 */
	public function testMakingValidPages() {
		$specialPage = $this->getSpecialPage();
		$factory = $this->getFactory();
		$names = $factory->getNames();
		foreach ( $names as $pageName ) {
			$page = $factory->getPage( $pageName, $specialPage );
			$this->assertInstanceOf( ActionPage::class, $page );
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
}
