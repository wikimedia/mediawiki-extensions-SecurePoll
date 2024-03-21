<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use ExtensionRegistry;
use HTMLForm;
use MediaWiki\Extension\SecurePoll\Ballots\Ballot;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\Hooks\HookRunner;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\Extension\SecurePoll\User\RemoteMWAuth;
use MediaWiki\Extension\SecurePoll\User\Voter;
use MediaWiki\Extension\SecurePoll\VoteRecord;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use OOUI\MessageWidget;
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
	/** @var User|null */
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
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		ILoadBalancer $loadBalancer,
		HookContainer $hookContainer
	) {
		parent::__construct( $specialPage );
		$this->loadBalancer = $loadBalancer;
		$this->hookRunner = new HookRunner( $hookContainer );
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();
		$out->addJsConfigVars( 'SecurePollSubPage', 'vote' );
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
				] )
			),
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

		$dbw = $this->loadBalancer->getConnection( ILoadBalancer::DB_PRIMARY );
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

		$votingData = $this->getVoteDataFromRecord( $record );
		$languageCode = $this->specialPage->getContext()->getLanguage()->getCode();
		$summary = $this->getSummaryOfVotes( $votingData, $languageCode );
		$out->addHtml( $summary );

		if ( $crypt ) {
			$receipt = sprintf( "SPID: %10d\n%s", $voteId, $encrypted );
			$out->addWikiMsg( 'securepoll-gpg-receipt', $receipt );
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
	 * Get summary of voting in user readable version
	 *
	 * @param array $votingData
	 * @param string $languageCode
	 * @return string
	 */
	public function getSummaryOfVotes( $votingData, $languageCode ) {
		$data = $votingData['votes'];
		$comment = $votingData['comment'];

		/**
		 * if record cannot be unpacked correctly, show error
		 */
		if ( !$data ) {
			return new MessageWidget( [
				'type' => 'error',
				'label' => $this->msg( 'securepoll-vote-result-error-label' )
			] );
		}

		$summary = new MessageWidget( [
			'type' => 'success',
			'label' => $this->msg( 'securepoll-thanks' )
		] );

		$summary .= Html::element( 'h2', [ 'class' => 'securepoll-vote-result-heading' ],
			$this->msg( 'securepoll-vote-result-intro-label' ) );

		foreach ( $data as $questionIndex => $votes ) {
			$questionMsg = $this->getQuestionMessage( $languageCode, $questionIndex );
			$optionsMsgs = $this->getOptionMessages( $languageCode, $votes );
			if ( !isset( $questionMsg[$questionIndex]['text'] ) ) {
				continue;
			}
			$questionText = $questionMsg[$questionIndex]['text'];
			$html = Html::openElement( 'div', [ 'class' => 'securepoll-vote-result-question-cnt' ] );
			$html .= Html::element(
				'p', [ 'class' => 'securepoll-vote-result-question' ],
				$this->msg( 'securepoll-vote-result-question-label', $questionText )
			);

			$listType = 'ul';
			$votedItems = [];
			if ( $this->election->getTallyType() === 'droop-quota' ) {
				$listType = 'ol';
				foreach ( $optionsMsgs as $option ) {
					$votedItems[] = Html::rawElement( 'li', [], $option['text'] );
				}
			} else {
				$notVotedItems = [];
				foreach ( $optionsMsgs as $optionIndex => $option ) {
					$optionText = $optionsMsgs[$optionIndex]['text'];
					$count = $optionIndex;
					if ( isset( $votes[ $optionIndex ] ) ) {
						$count = $votes[ $optionIndex ];
					}

					if ( $this->election->getTallyType() === 'plurality' ||
						$this->election->getTallyType() === 'histogram-range' ) {
						if ( isset( $questionMsg[$questionIndex]['column' . $count ] ) ) {
							$columnLabel = $questionMsg[$questionIndex]['column' . $count ];
							$votedItems[] = Html::element( 'li', [],
								$this->msg( 'securepoll-vote-result-voted-option-label', $optionText, $columnLabel )
							);
							continue;
						}
						if ( is_int( $count ) && $count > 0 ) {
							$positiveCount = '+' . $count;
							if ( isset( $questionMsg[$questionIndex]['column' . $positiveCount ] ) ) {
								$columnLabel = $questionMsg[$questionIndex]['column' . $positiveCount ];
								$votedItems[] = Html::element( 'li', [],
									$this->msg( 'securepoll-vote-result-voted-option-label', $optionText, $columnLabel )
								);
								continue;
							}
						}
					}

					if ( $this->election->getTallyType() === 'schulze' && $count === 1000 ) {
						$notVotedItems[] = Html::element( 'li', [],
							$this->msg( 'securepoll-vote-result-not-voted-option-label', $optionText )
						);
						continue;
					}

					if ( $count === 0 ) {
						$notVotedItems[] = Html::element( 'li', [],
							$this->msg( 'securepoll-vote-result-not-checked-option-label', $optionText )
						);
						continue;
					}
					if ( $this->election->getTallyType() === 'plurality' ) {
						$votedItems[] = Html::element( 'li', [],
							$this->msg( 'securepoll-vote-result-checked-option-label', $optionText )
						);
						continue;
					}
					$votedItems[] = Html::element( 'li', [],
						$this->msg( 'securepoll-vote-result-rated-option-label', $optionText, $count )
					);
				}

				if ( $notVotedItems !== [] ) {
					$votedItems[] = Html::rawElement( 'ul', [ 'class' => 'securepoll-vote-result-no-vote' ],
						implode( "\n", $notVotedItems )
					);
				}
			}

			$html .= Html::rawElement( $listType, [ 'class' => 'securepoll-vote-result-options' ],
				implode( "\n", $votedItems )
			);
			$html .= Html::closeElement( 'div' );
			$summary .= $html;
		}

		if ( $comment !== '' ) {
			$summary .= Html::element( 'div', [ 'class' => 'securepoll-vote-result-comment' ],
				$this->msg( 'securepoll-vote-result-comment', $comment )->plain()
			);
		}
		return $summary;
	}

	/**
	 * @param string $record
	 * @return array
	 */
	public function getVoteDataFromRecord( $record ) {
		$blob = VoteRecord::readBlob( $record );
		$ballotData = $blob->value->getBallotData();
		$data = [];
		$data['votes'] = $this->getBallot()->unpackRecord( $ballotData );
		$data['comment'] = $blob->value->getComment();
		return $data;
	}

	/**
	 * @param string $languageCode
	 * @param int $questionIndex
	 * @return string[][]
	 */
	private function getQuestionMessage( $languageCode, $questionIndex ) {
		$questionMsg = $this->context->getMessages( $languageCode, [ $questionIndex ] );
		if ( !$questionMsg ) {
			$fallbackLangCode = $this->election->getLanguage();
			$questionMsg = $this->context->getMessages( $fallbackLangCode, [ $questionIndex ] );
		}
		return $questionMsg;
	}

	/**
	 * @param string $languageCode
	 * @param array $votes
	 * @return string[][]
	 */
	private function getOptionMessages( $languageCode, $votes ) {
		$optionsMsgs = $this->context->getMessages( $languageCode, $votes );
		if ( !$optionsMsgs || count( $votes ) !== count( $optionsMsgs ) ) {
			$languageCode = $this->election->getLanguage();
			$optionsMsgs = $this->context->getMessages( $languageCode, $votes );
		}
		if ( !$optionsMsgs || count( $votes ) !== count( $optionsMsgs ) ) {
			$msgsKeys = [];
			foreach ( $votes as $questionKey => $item ) {
				$msgsKeys[] = $questionKey;
			}
			$optionsMsgs = $this->context->getMessages( $languageCode, $msgsKeys );
		}
		return $optionsMsgs;
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
			throw new InvalidDataException( 'Configuration error: no jump-url' );
		}

		$id = $this->election->getProperty( 'jump-id' );
		if ( !$id ) {
			throw new InvalidDataException( 'Configuration error: no jump-id' );
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
