<?php

use MediaWiki\Session\SessionManager;

/**
 * The subpage for casting votes.
 */
class SecurePoll_VotePage extends SecurePoll_ActionPage {
	public $languages;
	public $election, $auth, $user;
	public $voter;

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$language = $this->specialPage->getLanguage();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}

		$this->auth = $this->election->getAuth();

		# Get voter from session
		$this->voter = $this->auth->getVoterFromSession( $this->election );

		# If there's no session, try creating one.
		# This will fail if the user is not authorized to vote in the election
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

		$out->setPageTitle( $this->election->getMessage( 'title' ) );

		if ( !$this->election->isStarted() ) {
			$out->addWikiMsg( 'securepoll-not-started',
				$language->timeanddate( $this->election->getStartDate() ),
				$language->date( $this->election->getStartDate() ),
				$language->time( $this->election->getStartDate() ) );
			return;
		}

		if ( $this->election->isFinished() ) {
			$out->addWikiMsg( 'securepoll-finished',
				$language->timeanddate( $this->election->getEndDate() ),
				$language->date( $this->election->getEndDate() ),
				$language->time( $this->election->getEndDate() ) );
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
		$thisTitle = $this->getTitle();
		$encAction = htmlspecialchars( $thisTitle->getLocalURL( "action=vote" ) );
		$encOK = $this->msg( 'securepoll-submit' )->escaped();
		$encToken = htmlspecialchars( SessionManager::getGlobalSession()->getToken()->toString() );

		$out->addHTML(
			"<form name=\"securepoll\" id=\"securepoll\" method=\"post\" action=\"$encAction\">\n" .
			$this->election->getBallot()->getForm( $status ) .
			"<br />\n" .
			"<input name=\"submit\" type=\"submit\" value=\"$encOK\">\n" .
			"<input type='hidden' name='edit_token' value=\"{$encToken}\" /></td>\n" .
			"</form>"
		);
	}

	/**
	 * Submit the voting form. If successful, adds a record to the database.
	 * Shows an error message on failure.
	 */
	public function doSubmit() {
		$ballot = $this->election->getBallot();
		$status = $ballot->submitForm();
		if ( !$status->isOK() ) {
			$this->showForm( $status );
		} else {
			$this->logVote( $status->value );
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

		$dbw = $this->context->getDB();
		$dbw->startAtomic( __METHOD__ );

		# Mark previous votes as old
		$dbw->update( 'securepoll_votes',
			[ 'vote_current' => 0 ], # SET
			[ # WHERE
				'vote_election' => $this->election->getId(),
				'vote_voter' => $this->voter->getId(),
			],
			__METHOD__
		);

		# Add vote to log
		$xff = '';
		if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		$token = SessionManager::getGlobalSession()->getToken();
		$tokenMatch = $token->match( $request->getVal( 'edit_token' ) );

		$voteId = $dbw->nextSequenceValue( 'securepoll_votes_vote_id' );
		$dbw->insert( 'securepoll_votes',
			[
				'vote_id' => $voteId,
				'vote_election' => $this->election->getId(),
				'vote_voter' => $this->voter->getId(),
				'vote_voter_name' => $this->voter->getName(),
				'vote_voter_domain' => $this->voter->getDomain(),
				'vote_record' => $encrypted,
				'vote_ip' => IP::toHex( $request->getIP() ),
				'vote_xff' => $xff,
				'vote_ua' => $_SERVER['HTTP_USER_AGENT'],
				'vote_timestamp' => $now,
				'vote_current' => 1,
				'vote_token_match' => $tokenMatch ? 1 : 0,
			],
			__METHOD__ );
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
		if ( !$url ) {
			throw new MWException( 'Configuration error: no jump-url' );
		}
		$id = $this->election->getProperty( 'jump-id' );
		if ( !$id ) {
			throw new MWException( 'Configuration error: no jump-id' );
		}
		$url .= "/login/$id";
		Hooks::run( 'SecurePoll_JumpUrl', [ $this, &$url ] );
		$out->addWikiTextAsInterface( $this->election->getMessage( 'jump-text' ) );
		$hiddenFields = [
			'token' => SecurePoll_RemoteMWAuth::encodeToken( $user->getToken() ),
			'id' => $user->getId(),
			'wiki' => wfWikiID(),
		];

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->specialPage->getContext() )
			->setSubmitTextMsg( 'securepoll-jump' )
			->setAction( $url )
			->addHiddenFields( $hiddenFields )
			->prepareForm();
		$out->addHTML( $htmlForm->getHTML( false ) );
	}
}
