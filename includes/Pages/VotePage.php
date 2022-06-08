<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use ExtensionRegistry;
use HTMLForm;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Hooks\HookRunner;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\Extension\SecurePoll\User\RemoteMWAuth;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\Extension\SecurePoll\VoteRecord;
use MediaWiki\Session\SessionManager;
use MobileContext;
use MWException;
use Status;
use Title;
use WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * The subpage for casting votes.
 */
class VotePage extends ActionPage {
	/** @var Election|null */
	public $election;
	/** @var Auth|null */
	public $auth;
	/** @var \User|null */
	public $user;
	/** @var Voter|null */
	public $voter;
	/** @var ILoadBalancer */
	private $loadBalancer;
	/** @var HookRunner */
	private $hookRunner;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param ILoadBalancer $loadBalancer
	 * @param HookRunner $hookRunner
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		ILoadBalancer $loadBalancer,
		HookRunner $hookRunner
	) {
		parent::__construct( $specialPage );
		$this->loadBalancer = $loadBalancer;
		$this->hookRunner = $hookRunner;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();
		$out->addModules( 'ext.securepoll.htmlform' );
		$out->addModuleStyles( [
			'oojs-ui.styles.icons-alerts',
			'oojs-ui.styles.icons-movement'
		] );

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		if ( preg_match( '/^[0-9]+$/', $params[0] ) ) {
			$electionId = intval( $params[0] );
			$this->election = $this->context->getElection( $electionId );
		} else {
			$electionId = str_replace( '_', ' ', $params[0] );
			$this->election = $this->context->getElectionByTitle( $electionId );
		}

		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}

		$this->auth = $this->election->getAuth();

		// Get voter from session
		$this->voter = $this->auth->getVoterFromSession( $this->election );

		// If there's no session, try creating one.
		// This will fail if the user is not authorized to vote in the election
		if ( !$this->voter ) {
			$status = $this->auth->newAutoSession( $this->election );
			if ( $status->isOK() ) {
				$this->voter = $status->value;
			} else {
				$out->addWikiTextAsInterface( $status->getWikiText() );

				return;
			}
		}

		$this->initLanguage( $this->voter, $this->election );
		$language = $this->getUserLang();
		$this->specialPage->getContext()->setLanguage( $language );

		$out->setPageTitle( $this->election->getMessage( 'title' ) );

		if ( !$this->election->isStarted() ) {
			$out->addWikiMsg(
				'securepoll-not-started',
				$language->timeanddate( $this->election->getStartDate() ),
				$language->date( $this->election->getStartDate() ),
				$language->time( $this->election->getStartDate() )
			);

			return;
		}

		if ( $this->election->isFinished() ) {
			$out->addWikiMsg(
				'securepoll-finished',
				$language->timeanddate( $this->election->getEndDate() ),
				$language->date( $this->election->getEndDate() ),
				$language->time( $this->election->getEndDate() )
			);

			return;
		}

		// Show jump form if necessary
		if ( $this->election->getProperty( 'jump-url' ) ) {
			$this->showJumpForm();

			return;
		}

		// This is when it starts getting all serious; disable JS
		// that might be used to sniff cookies or log voting data.
		$out->disallowUserJs();

		// Show welcome
		if ( $this->voter->isRemote() ) {
			$out->addWikiMsg( 'securepoll-welcome', $this->voter->getName() );
		}

		// Show change notice
		if ( $this->election->hasVoted( $this->voter ) && !$this->election->allowChange() ) {
			$out->addWikiMsg( 'securepoll-change-disallowed' );

			return;
		}

		// Show/submit the form
		if ( $this->specialPage->getRequest()->wasPosted() ) {
			$this->doSubmit();
		} else {
			$this->showForm();
		}
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'vote/' . $this->election->getId() );
	}

	/**
	 * Show the voting form.
	 * @param Status|false $status
	 */
	public function showForm( $status = false ) {
		$out = $this->specialPage->getOutput();

		// Show introduction
		if ( $this->election->hasVoted( $this->voter ) && $this->election->allowChange() ) {
			$out->addWikiMsg( 'securepoll-change-allowed' );
		}
		$out->addWikiTextAsInterface( $this->election->getMessage( 'intro' ) );

		// Show form
		$form = new \OOUI\FormLayout( [
			'action' => $this->getTitle()->getLocalURL( "action=vote" ),
			'method' => 'post',
			'items' => $this->getBallot()->getForm( $status )
		] );

		// Show comments section
		if ( $this->election->getProperty( 'request-comment' ) ) {
			$form->addItems( [
				new \OOUI\FieldsetLayout( [
					'label' => $this->msg( 'securepoll-header-comments' ),
					'items' => [
						new \OOUI\FieldLayout(
							new \OOUI\MultilineTextInputWidget( [
								'name' => 'securepoll_comment',
								'rows' => 3,
								// vote_record is a BLOB, so this can't be infinity
								'maxLength' => 10000,
							] ),
							[
								'label' => new \OOUI\HtmlSnippet(
									$this->election->parseMessage( 'comment-prompt' )
								),
								'align' => 'top'
							]
						)
					]
				] )
			] );
		}

		$form->addItems( [
			new \OOUI\FieldLayout(
				new \OOUI\ButtonInputWidget( [
					'label' => $this->msg( 'securepoll-submit' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'type' => 'submit',
			] ) ),
			new \OOUI\HiddenInputWidget( [
				'name' => 'edit_token',
				'value' => SessionManager::getGlobalSession()->getToken()->toString(),
			] )
		] );

		$out->addHTML( $form );
	}

	/**
	 * Get the Ballot for this election, with injected request dependencies.
	 * @return Ballot
	 */
	private function getBallot() {
		$ballot = $this->election->getBallot();
		$ballot->initRequest(
			$this->specialPage->getRequest(),
			$this->specialPage,
			$this->getUserLang()
		);
		return $ballot;
	}

	/**
	 * Submit the voting form. If successful, adds a record to the database.
	 * Shows an error message on failure.
	 */
	public function doSubmit() {
		$ballot = $this->getBallot();
		$status = $ballot->submitForm();
		if ( !$status->isOK() ) {
			$this->showForm( $status );
		} else {
			$voteRecord = VoteRecord::newFromBallotData(
				$status->value,
				$this->specialPage->getRequest()->getText( 'securepoll_comment' )
			);
			$this->logVote( $voteRecord->getBlob() );
		}
	}

	/**
	 * Add a vote to the database with the given unencrypted answer record.
	 * @param string $record
	 */
	public function logVote( $record ) {
		$out = $this->specialPage->getOutput();
		$request = $this->specialPage->getRequest();

		$now = wfTimestampNow();

		$crypt = $this->election->getCrypt();
		if ( !$crypt ) {
			$encrypted = $record;
		} else {
			$status = $crypt->encrypt( $record );
			if ( !$status->isOK() ) {
				$out->addWikiTextAsInterface( $status->getWikiText( 'securepoll-encrypt-error' ) );

				return;
			}
			$encrypted = $status->value;
		}

		$dbw = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Mark previous votes as old
		$dbw->update(
			'securepoll_votes',
			[ 'vote_current' => 0 ],
			[
				'vote_election' => $this->election->getId(),
				'vote_voter' => $this->voter->getId(),
			],
			__METHOD__
		);

		// Add vote to log
		$xff = '';
		if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		$token = SessionManager::getGlobalSession()->getToken();
		$tokenMatch = $token->match( $request->getVal( 'edit_token' ) );

		$dbw->insert(
			'securepoll_votes',
			[
				'vote_election' => $this->election->getId(),
				'vote_voter' => $this->voter->getId(),
				'vote_voter_name' => $this->voter->getName(),
				'vote_voter_domain' => $this->voter->getDomain(),
				'vote_record' => $encrypted,
				'vote_ip' => IPUtils::toHex( $request->getIP() ),
				'vote_xff' => $xff,
				'vote_ua' => $_SERVER['HTTP_USER_AGENT'],
				'vote_timestamp' => $now,
				'vote_current' => 1,
				'vote_token_match' => $tokenMatch ? 1 : 0,
				'vote_struck' => 0,
				'vote_cookie_dup' => 0,
			],
			__METHOD__
		);
		$voteId = $dbw->insertId();
		$dbw->endAtomic( __METHOD__ );

		if ( $crypt ) {
			$receipt = sprintf( "SPID: %10d\n%s", $voteId, $encrypted );
			$out->addWikiMsg( 'securepoll-gpg-receipt', $receipt );
		} else {
			$out->addWikiMsg( 'securepoll-thanks' );
		}
		$returnUrl = $this->election->getProperty( 'return-url' );
		$returnText = $this->election->getMessage( 'return-text' );
		if ( $returnUrl ) {
			if ( strval( $returnText ) === '' ) {
				$returnText = $returnUrl;
			}
			$link = "[$returnUrl $returnText]";
			$out->addWikiMsg( 'securepoll-return', $link );
		}
	}

	/**
	 * Show a page informing the user that they must go to another wiki to
	 * cast their vote, and a button which takes them there.
	 *
	 * Clicking the button transmits a hash of their auth token, so that the
	 * remote server can authenticate them.
	 */
	public function showJumpForm() {
		$user = $this->specialPage->getUser();
		$out = $this->specialPage->getOutput();

		$url = $this->election->getProperty( 'jump-url' );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {
			$mobileUrl = $this->election->getProperty( 'mobile-jump-url' );
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			$mobileContext = MobileContext::singleton();
			if ( $mobileUrl && $mobileContext->usingMobileDomain() ) {
				$url = $mobileUrl;
			}
		}
		if ( !$url ) {
			throw new MWException( 'Configuration error: no jump-url' );
		}

		$id = $this->election->getProperty( 'jump-id' );
		if ( !$id ) {
			throw new MWException( 'Configuration error: no jump-id' );
		}
		$url .= "/login/$id";

		$this->hookRunner->onSecurePoll_JumpUrl( $this, $url );

		$out->addWikiTextAsInterface( $this->election->getMessage( 'jump-text' ) );
		$hiddenFields = [
			'token' => RemoteMWAuth::encodeToken( $user->getToken() ),
			'id' => $user->getId(),
			'wiki' => WikiMap::getCurrentWikiId(),
		];

		$htmlForm = HTMLForm::factory(
			'ooui',
			[],
			$this->specialPage->getContext()
		)->setSubmitTextMsg( 'securepoll-jump' )->setAction( $url )->addHiddenFields(
				$hiddenFields
			)->prepareForm();

		$out->addHTML( $htmlForm->getHTML( false ) );
	}
}
