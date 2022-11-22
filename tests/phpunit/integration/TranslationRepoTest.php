<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\TranslationRepo;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * @covers \MediaWiki\Extension\SecurePoll\TranslationRepo
 */
class TranslationRepoTest extends MediaWikiIntegrationTestCase {

	/**
	 *
	 * @param array $data
	 * @param string $language
	 * @param string $comment
	 * @param array $wikis
	 * @param int $expectedReplaceCalls
	 * @dataProvider provideSetTranslationsTestData
	 * @covers \MediaWiki\Extension\SecurePoll\TranslationRepo::setTranslation
	 */
	public function testSetTranslation( $data, $language, $comment, $wikis, $expectedReplaceCalls ) {
		$services = MediaWikiServices::getInstance();
		$mockDB = $this->getMockDB();
		$mockDB->method( 'selectField' )->will( $this->returnValue( 7894 ) );
		$mockDB->expects( $this->exactly( $expectedReplaceCalls ) )->method( 'replace' );

		$mockLB = $this->getMockLoadBalancer( $mockDB );
		$mockLBFactory = $this->getMockLBFactory( $mockLB );

		$translationRepo = new TranslationRepo(
			$mockLBFactory,
			$services->getWikiPageFactory(),
			$services->getMainConfig()
		);

		$mockUser = $this->createMock( User::class );

		$election = $this->createMock( Election::class );
		$election->method( 'getDescendants' )
		->will( $this->returnCallback( static function () {
			return [];
		} ) );

		$election->method( 'getProperty' )->will( $this->returnValue( $wikis ) );

		$election->method( 'getMessageNames' )->will( $this->returnCallback( static function () {
			return [
				'intro',
				'title'
			];
		} ) );
		$election->method( 'getId' )->will( $this->returnCallback( static function () {
			return 7894;
		} ) );

		$translationRepo->setTranslation( $election, $data, $language, $mockUser, $comment );
	}

	public function provideSetTranslationsTestData() {
		return [
			'default' => [
				// data to be set with translation parser
				[
					'trans_7894_intro' => 'Election Intro Text',
					'trans_7894_title' => 'Election Title'
				],
				// Lang code
				'en',
				// comment for updater
				'',
				// election property wikis
				false,
				// How often do we expect Database::replace to be called
				1
			],
			'multiple-wikis' => [
				[
					'trans_7894_intro' => 'Wahleinführungstext',
					'trans_7894_title' => 'Wahltitel'
				],
				'de',
				'test comment',
				'votewiki
				testwiki
				languagewiki',
				4
			]
			];
	}

	private function getMockDB() {
		$mockDB = $this->createMock( IDatabase::class );
		return $mockDB;
	}

	private function getMockLoadBalancer( $mockDB ) {
		$mockLB = $this->getMockBuilder( LoadBalancer::class )
		->disableOriginalConstructor()
		->getMock();

		$mockLB->method( 'getConnectionRef' )
			->will( $this->returnValue( $mockDB ) );
		$mockLB->method( 'getConnection' )
			->will( $this->returnValue( $mockDB ) );
		return $mockLB;
	}

	private function getMockLBFactory( $loadBalancer ) {
		$mock = $this->getMockBuilder( LBFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getMainLB' )
			->will( $this->returnValue( $loadBalancer ) );
		return $mock;
	}
}
