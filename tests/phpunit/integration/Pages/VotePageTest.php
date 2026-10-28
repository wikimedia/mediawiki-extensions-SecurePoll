<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\SecurePoll\Test\Integration\Pages;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\SecurePoll\Pages\CreatePage;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Pages\VotePage
 */
class VotePageTest extends SpecialPageTestBase {
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

	/**
	 * Create a running election with one option.
	 *
	 * @param bool $requestComment
	 * @return array [ election ID, question ID, option ID ]
	 */
	private function createElection( bool $requestComment ): array {
		$now = wfTimestamp();
		$testAdmin = $this->getTestSysop()->getAuthority();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $testAdmin );

		$page = $this->newSpecialPage();
		$page->setContext( $context );

		/** @var CreatePage $createPage */
		$createPage = $page->getSubpage( 'create' );

		$status = $createPage->processInput( [
			'election_id' => -1,
			'election_title' => 'Comment test election',
			'questions' => [
				[
					'id' => '-1',
					'text' => 'Question 1',
					'options' => [ [ 'id' => '-1', 'text' => 'Option 1' ] ],
				],
			],
			'election_startdate' => wfTimestamp( TimestampFormat::ISO_8601, $now - 7 * 24 * 60 * 60 ),
			'election_enddate' => wfTimestamp( TimestampFormat::ISO_8601, $now + 7 * 24 * 60 * 60 ),
			'election_type' => 'approval+plurality',
			'election_crypt' => 'none',
			'election_primaryLang' => 'en',
			'property_admins' => $testAdmin->getUser()->getName(),
			'disallow-change' => false,
			'voter-privacy' => false,
			'request-comment' => $requestComment,
			'prompt-active-wiki' => false,
			'comment-prompt' => false,
			'shuffle-questions' => false,
			'shuffle-options' => false,
			'return-url' => '',
		], null );
		$this->assertStatusGood( $status, 'Failed to set up election' );

		$db = $this->getDb();
		$electionId = (int)$db->newSelectQueryBuilder()
			->select( 'el_entity' )
			->from( 'securepoll_elections' )
			->where( [ 'el_title' => 'Comment test election' ] )
			->caller( __METHOD__ )
			->fetchField();
		$questionId = (int)$db->newSelectQueryBuilder()
			->select( 'qu_entity' )
			->from( 'securepoll_questions' )
			->where( [ 'qu_election' => $electionId ] )
			->caller( __METHOD__ )
			->fetchField();
		$optionId = (int)$db->newSelectQueryBuilder()
			->select( 'op_entity' )
			->from( 'securepoll_options' )
			->where( [ 'op_election' => $electionId ] )
			->caller( __METHOD__ )
			->fetchField();

		return [ $electionId, $questionId, $optionId ];
	}

	/**
	 * Submit a crafted vote form that includes a comment.
	 *
	 * @param bool $requestComment
	 * @param string $comment
	 * @return string The HTML of the page
	 */
	private function submitVoteWithComment( bool $requestComment, string $comment ): string {
		[ $electionId, $questionId, $optionId ] = $this->createElection( $requestComment );

		// VotePage rejects a submission without a valid CSRF token. Pass the session
		// object, so that the token matches when VotePage gets the session again.
		$session = RequestContext::getMain()->getRequest()->getSession();
		$request = new FauxRequest(
			[
				"securepoll_q{$questionId}_opt{$optionId}" => '1',
				'securepoll_comment' => $comment,
				'edit_token' => $session->getToken()->toString(),
			],
			true,
			$session
		);

		// logVote() reads the user agent straight from $_SERVER.
		$_SERVER['HTTP_USER_AGENT'] ??= 'PHPUnit';

		$voter = $this->getTestUser()->getUser();
		// LocalAuth reads the voter from the main request context.
		RequestContext::getMain()->setUser( $voter );

		[ $html ] = $this->executeSpecialPage( "vote/$electionId", $request, 'en', $voter );

		return $html;
	}

	private function getStoredVoteRecords(): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'vote_record' )
			->from( 'securepoll_votes' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	public function testCommentIsDroppedWhenElectionDoesNotAcceptComments(): void {
		$this->submitVoteWithComment( false, 'This comment should be discarded' );

		$records = $this->getStoredVoteRecords();
		$this->assertCount( 1, $records, 'The vote should still be recorded' );
		$this->assertStringNotContainsString( 'comment', $records[0] );
		$this->assertStringNotContainsString( 'This comment should be discarded', $records[0] );
	}

	public function testCommentTooLong(): void {
		$html = $this->submitVoteWithComment( true, str_repeat( 'a', 6000 ) );

		$expected = wfMessage( 'securepoll-comment-too-long', 5000 )->inLanguage( 'en' )->text();
		$this->assertStringContainsString( $expected, $html );
		$this->assertCount( 0, $this->getStoredVoteRecords(), 'No vote should be recorded' );
	}
}
