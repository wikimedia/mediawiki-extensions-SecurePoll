<?php

namespace MediaWiki\Extension\SecurePoll\Test\Integration;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\OOUIModule;
use MediaWikiIntegrationTestCase;
use OOUI\Theme;

class VoteSummaryTest extends MediaWikiIntegrationTestCase {

	use OOUIModule;

	/** @var Context */
	private $context;

	/** @var Election */
	private $election;

	protected function setUp(): void {
		$themes = self::getSkinThemeMap();
		$theme = $themes['default'];
		$themeClass = "OOUI\\{$theme}Theme";
		Theme::setSingleton( new $themeClass() );
	}

	/**
	 * @param array $data
	 * @param string $langCode
	 * @param string $tallyType
	 * @param string $expected
	 * @dataProvider provideVotingData
	 * @covers \MediaWiki\Extension\SecurePoll\Pages\VotePage::getSummaryOfVotes
	 */
	public function testSummaryOfVotes( $data, $langCode, $tallyType, $expected ) {
		$this->context = Context::newFromXmlFile( dirname( __DIR__ ) . '/../../test/' . $tallyType . '-test.xml' );
		$store = $this->context->getStore();

		$electionIds = $store->getAllElectionIds();
		$this->election = $this->context->getElection( reset( $electionIds ) );

		$factory = $this->getFactory();
		$specialPage = $this->getSpecialPage();
		$specialPage->sp_context = $this->context;
		$votePage = $factory->getPage( 'vote', $specialPage );
		$votePage->election = $this->election;

		$summary = $votePage->getSummaryOfVotes( $data, $langCode );
		$this->assertEquals( $expected, $summary );
	}

	public function provideVotingData() {
		return [
			'approval-data' => [
				[
					'votes' => [
						41 => [
							42 => 0,
							43 => 1,
							44 => 0,
							45 => 0,
						]
					],
					'comment' => 'My comment'
				],
				'en',
				'approval',
				"<div aria-live='polite' class='oo-ui-widget oo-ui-widget-enabled oo-ui-labelElement " .
				"oo-ui-flaggedElement-success oo-ui-iconElement oo-ui-messageWidget-block oo-ui-messageWidget'>" .
				"<span class='oo-ui-iconElement-icon oo-ui-icon-success oo-ui-image-success'></span>" .
				"<span class='oo-ui-labelElement-label'>Thank you, your vote has been recorded.</span></div>" .
				"<h2 class=\"securepoll-vote-result-heading\">Summary of your vote</h2>" .
				"<div class=\"securepoll-vote-result-question-cnt\">" .
				"<p class=\"securepoll-vote-result-question\">Question: Approval test question</p>" .
				"<ul class=\"securepoll-vote-result-options\">" .
				"<li>B: rated 1</li>\n" .
				"<ul class=\"securepoll-vote-result-no-vote\">" .
				"<li>AAAA: not checked</li>\n" .
				"<li>C: not checked</li>\n" .
				"<li>D: not checked</li></ul></ul></div>" .
				"<div class=\"securepoll-vote-result-comment\">Comment: My comment</div>"
			],
			'schulze-data' => [
				[
					'votes' => [
						12 => [
							13 => 1,
							14 => 100,
							15 => 5,
							16 => 1000,
						]
					],
					'comment' => 'My comment'
				],
				'en',
				'schulze',
				"<div aria-live='polite' class='oo-ui-widget oo-ui-widget-enabled oo-ui-labelElement " .
				"oo-ui-flaggedElement-success oo-ui-iconElement oo-ui-messageWidget-block oo-ui-messageWidget'>" .
				"<span class='oo-ui-iconElement-icon oo-ui-icon-success oo-ui-image-success'></span>" .
				"<span class='oo-ui-labelElement-label'>Thank you, your vote has been recorded.</span></div>" .
				"<h2 class=\"securepoll-vote-result-heading\">Summary of your vote</h2>" .
				"<div class=\"securepoll-vote-result-question-cnt\">" .
				"<p class=\"securepoll-vote-result-question\">Question: Schulze test question</p>" .
				"<ul class=\"securepoll-vote-result-options\">" .
				"<li>A: rated 1</li>\n" .
				"<li>B: rated 100</li>\n" .
				"<li>C: rated 5</li>\n" .
				"<ul class=\"securepoll-vote-result-no-vote\"><li>D: not voted</li></ul></ul>" .
				"</div><div class=\"securepoll-vote-result-comment\">Comment: My comment</div>"
			],
			'3way-data' => [
				[
					'votes' => [
						51 => [
							52 => 1,
							53 => 0,
							54 => 0,
							55 => -1,
						]
					],
					'comment' => 'My comment'
				],
				'en',
				'3way',
				"<div aria-live='polite' class='oo-ui-widget oo-ui-widget-enabled oo-ui-labelElement " .
				"oo-ui-flaggedElement-success oo-ui-iconElement oo-ui-messageWidget-block oo-ui-messageWidget'>" .
				"<span class='oo-ui-iconElement-icon oo-ui-icon-success oo-ui-image-success'></span>" .
				"<span class='oo-ui-labelElement-label'>Thank you, your vote has been recorded.</span></div>" .
				"<h2 class=\"securepoll-vote-result-heading\">Summary of your vote</h2>" .
				"<div class=\"securepoll-vote-result-question-cnt\">" .
				"<p class=\"securepoll-vote-result-question\">Question: RR1 test question</p>" .
				"<ul class=\"securepoll-vote-result-options\"><li>AAA: Support</li>\n" .
				"<li>B: Abstain</li>\n" .
				"<li>C: Abstain</li>\n" .
				"<li>D: Oppose</li></ul></div>" .
				"<div class=\"securepoll-vote-result-comment\">Comment: My comment</div>"
			]
		];
	}

	protected function getFactory() {
		return MediaWikiServices::getInstance()->getService( 'SecurePoll.ActionPageFactory' );
	}

	protected function getSpecialPage() {
		return new SpecialSecurePoll( $this->getFactory() );
	}
}
