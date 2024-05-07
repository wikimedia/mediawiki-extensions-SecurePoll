<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\TranslationRepo;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\LoadBalancer;
use Wikimedia\Rdbms\ReplaceQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;

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
		$rqb = $this->createMock( ReplaceQueryBuilder::class );
		$rqb->method( $this->logicalOr( 'replaceInto', 'uniqueIndexFields', 'rows', 'caller' ) )->willReturnSelf();
		$sqb = $this->createMock( SelectQueryBuilder::class );
		$sqb->method( $this->logicalOr( 'select', 'from', 'where', 'caller' ) )->willReturnSelf();
		$sqb->method( 'fetchField' )->willReturn( 7894 );
		$mockDB = $this->createMock( IDatabase::class );
		$mockDB->method( 'newSelectQueryBuilder' )->willReturn( $sqb );
		$mockDB->expects( $this->exactly( $expectedReplaceCalls ) )
			->method( 'newReplaceQueryBuilder' )
			->willReturn( $rqb );

		$translationRepo = new TranslationRepo(
			$this->getMockLBFactory( $mockDB ),
			$services->getWikiPageFactory(),
			$services->getMainConfig()
		);

		$mockUser = $this->createMock( User::class );

		$election = $this->createMock( Election::class );
		$election->method( 'getDescendants' )->willReturnCallback( static function () {
			return [];
		} );

		$election->method( 'getProperty' )->willReturn( $wikis );

		$election->method( 'getMessageNames' )->willReturnCallback( static function () {
			return [
				'intro',
				'title'
			];
		} );
		$election->method( 'getId' )->willReturnCallback( static function () {
			return 7894;
		} );

		$translationRepo->setTranslation( $election, $data, $language, $mockUser, $comment );
	}

	public static function provideSetTranslationsTestData() {
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

	private function getMockLBFactory( IDatabase $mockDB ): LBFactory {
		$loadBalancer = $this->createMock( LoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $mockDB );

		$mock = $this->createMock( LBFactory::class );
		$mock->method( 'getMainLB' )
			->willReturn( $loadBalancer );
		return $mock;
	}
}
