<?php
namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\SpecialSecurePoll
 * @covers \MediaWiki\Extension\SecurePoll\Pages\MainElectionsPager
 * @covers \MediaWiki\Extension\SecurePoll\Pages\ElectionPager
 */
class EntryPageTest extends SpecialPageTestBase {
	protected function setUp(): void {
		parent::setUp();

		$this->setGroupPermissions( [
			'sysop' => [
				'securepoll-create-poll' => true,
				'securepoll-edit-poll' => true,
			],
		] );
	}

	protected function newSpecialPage(): SpecialSecurePoll {
		return $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'SecurePoll' );
	}

	public function testShouldShowListOfElections(): void {
		$now = wfTimestamp();

		$lastMonth = wfTimestamp( TS_ISO_8601, $now - 30 * 24 * 60 * 60 );
		$lastWeek = wfTimestamp( TS_ISO_8601, $now - 7 * 24 * 60 * 60 );
		$nextWeek = wfTimestamp( TS_ISO_8601, $now + 7 * 24 * 60 * 60 );
		$nextMonth = wfTimestamp( TS_ISO_8601, $now + 30 * 24 * 60 * 60 );

		// Setup a future, currently running and expired election.
		$elections = [
			[ $nextWeek, $nextMonth, '(securepoll-status-not-started)', 4 ],
			[ $lastWeek, $nextWeek, '(securepoll-status-in-progress)', 5 ],
			[ $lastMonth, $lastWeek, '(securepoll-status-completed)', 6 ],
		];

		$testAdmin = $this->getTestSysop()->getAuthority();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $testAdmin );

		$page = $this->newSpecialPage();
		$page->setContext( $context );

		/** @var CreatePage $createPage */
		$createPage = $page->getSubpage( 'create' );

		// Save all three elections.
		foreach ( $elections as $i => [ $startDate, $endDate ] ) {
			$index = $i + 1;
			$params = [
				'election_id' => -1,
				'election_title' => "Test Election #$index",
				'questions' => [
					[
						'id' => '-1',
						'text' => 'Question 1',
						'options' => [
							[
								'id' => '-1',
								'text' => 'Option 1'
							]
						]
					]
				],
				'election_startdate' => $startDate,
				'election_enddate' => $endDate,
				'election_type' => 'approval+plurality',
				'election_crypt' => 'none',
				'election_primaryLang' => 'en',
				'property_admins' => $testAdmin->getUser()->getName(),
				'disallow-change' => false,
				'voter-privacy' => false,
				'request-comment' => false,
				'prompt-active-wiki' => false,
				'comment-prompt' => false,
				'shuffle-questions' => false,
				'shuffle-options' => false,
				'return-url' => '',
			];

			$status = $createPage->processInput( $params, null );

			$this->assertStatusGood( $status, "Failed to setup election #$index" );
		}

		[ $html ] = $this->executeSpecialPage( null, null, null, $testAdmin );

		$doc = DOMUtils::parseHTML( $html );

		$electionRows = DOMCompat::querySelectorAll( $doc, 'tbody > tr' );

		$this->assertCount( 3, $electionRows, 'Expected 3 election rows' );

		foreach ( $electionRows as $i => $row ) {
			[
				$expectedStartDate,
				$expectedEndDate,
				$expectedStatus,
				$expectedLinkCount
			] = $elections[$i];

			$this->assertSame(
				$expectedStartDate,
				DOMCompat::querySelector( $row, '.TablePager_col_el_start_date > time' )
					->getAttribute( 'datetime' ),
				"Mismatched start date for election #$i"
			);
			$this->assertSame(
				$expectedEndDate,
				DOMCompat::querySelector( $row, '.TablePager_col_el_end_date > time' )
					->getAttribute( 'datetime' ),
				"Mismatched end date for election #$i"
			);
			$this->assertSame(
				$expectedStatus,
				DOMCompat::querySelector( $row, '.TablePager_col_status' )
					->textContent,
				"Mismatched status for election #$i"
			);
			$this->assertCount(
				$expectedLinkCount,
				DOMCompat::querySelectorAll( $row, '.TablePager_col_links > a' ),
				"Expected $expectedLinkCount links to be shown for election #$i"
			);
		}
	}
}
