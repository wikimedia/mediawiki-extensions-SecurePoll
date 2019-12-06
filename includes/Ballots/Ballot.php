<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\Entities\Entity;
use MediaWiki\Extensions\SecurePoll\Entities\Question;
use MWException;
use Status;
use Xml;

/**
 * Parent class for ballot forms. This is the UI component of a voting method.
 */
abstract class Ballot {
	public $election, $context, $currentVote;
	public $prevErrorIds, $usedErrorIds;
	/** @var BallotStatus */
	public $prevStatus;

	public static $ballotTypes = [
		'approval' => ApprovalBallot::class,
		'preferential' => PreferentialBallot::class,
		'choose' => ChooseBallot::class,
		'radio-range' => RadioRangeBallot::class,
		'radio-range-comment' => RadioRangeCommentBallot::class,
	];

	/**
	 * Get a list of names of tallying methods, which may be used to produce a
	 * result from this ballot type.
	 * @return array
	 */
	public static function getTallyTypes() {
		throw new MWException( "Subclass must override ::getTallyTypes()" );
	}

	/**
	 * Return descriptors for any properties this type requires for poll
	 * creation, for the election, questions, and options.
	 *
	 * The returned array should have three keys, "election", "question", and
	 * "option", each mapping to an array of HTMLForm descriptors.
	 *
	 * The descriptors should have an additional key, "SecurePoll_type", with
	 * the value being "property" or "message".
	 *
	 * @return array
	 */
	public static function getCreateDescriptors() {
		return [
			'election' => [
				'shuffle-questions' => [
					'label-message' => 'securepoll-create-label-shuffle_questions',
					'type' => 'check',
					'hidelabel' => true,
					'SecurePoll_type' => 'property',
				],
				'shuffle-options' => [
					'label-message' => 'securepoll-create-label-shuffle_options',
					'type' => 'check',
					'hidelabel' => true,
					'SecurePoll_type' => 'property',
				],
			],
			'question' => [],
			'option' => [],
		];
	}

	/**
	 * Get the HTML form segment for a single question
	 * @param Question $question
	 * @param array $options Array of options, in the order they should be displayed
	 * @return string
	 */
	abstract public function getQuestionForm( $question, $options );

	/**
	 * Get any extra messages that this ballot type uses to render questions.
	 * Used to get the list of translatable messages for TranslatePage.
	 * @param Entity|null $entity
	 * @return array
	 * @see Election::getMessageNames()
	 */
	public function getMessageNames( Entity $entity = null ) {
		return [];
	}

	/**
	 * Called when the form is submitted. This returns a Status object which,
	 * when successful, contains a voting record in the value member. To
	 * preserve voter privacy, voting records should be the same length
	 * regardless of voter choices.
	 * @return Status
	 */
	public function submitForm() {
		$questions = $this->election->getQuestions();
		$record = '';
		$status = new BallotStatus( $this->context );

		foreach ( $questions as $question ) {
			$record .= $this->submitQuestion( $question, $status );
		}
		if ( $status->isOK() ) {
			$status->value = $record . "\n";
		}

		return $status;
	}

	/**
	 * Construct a string record for a given question, during form submission.
	 *
	 * If there is a problem with the form data, the function should set a
	 * fatal error in the $status object and return null.
	 *
	 * @param Question $question
	 * @param BallotStatus $status
	 * @return string|null
	 */
	abstract public function submitQuestion( $question, $status );

	/**
	 * Unpack a string record into an array format suitable for the tally type
	 * @param string $record
	 * @return array|bool
	 */
	abstract public function unpackRecord( $record );

	/**
	 * Convert a record to a string of some kind
	 * @param string $record
	 * @param array $options
	 * @return array
	 */
	public function convertRecord( $record, $options = [] ) {
		$scores = $this->unpackRecord( $record );

		return $this->convertScores( $scores );
	}

	/**
	 * Convert a score array to a string of some kind
	 * @param array $scores
	 * @param array $options
	 * @return string|array
	 */
	abstract public function convertScores( $scores, $options = [] );

	/**
	 * Create a ballot of the given type
	 * @param Context $context
	 * @param string $type
	 * @param Election $election
	 * @return Ballot
	 * @throws MWException
	 */
	public static function factory( $context, $type, $election ) {
		if ( !isset( self::$ballotTypes[$type] ) ) {
			throw new MWException( "Invalid ballot type: $type" );
		}
		$class = self::$ballotTypes[$type];

		return new $class( $context, $election );
	}

	/**
	 * Constructor.
	 * @param Context $context
	 * @param Election $election
	 */
	public function __construct( $context, $election ) {
		$this->context = $context;
		$this->election = $election;
	}

	/**
	 * Get the HTML for this ballot. <form> tags should not be included,
	 * they will be added by the VotePage.
	 * @param bool|BallotStatus $prevStatus
	 * @return string
	 */
	public function getForm( $prevStatus = false ) {
		$questions = $this->election->getQuestions();
		if ( $this->election->getProperty( 'shuffle-questions' ) ) {
			shuffle( $questions );
		}
		$shuffleOptions = $this->election->getProperty( 'shuffle-options' );
		$this->setErrorStatus( $prevStatus );

		$s = '';
		foreach ( $questions as $question ) {
			$options = $question->getOptions();
			if ( $shuffleOptions ) {
				shuffle( $options );
			}
			$s .= "<hr/>\n" . $question->parseMessage( 'text' ) . $this->getQuestionForm(
					$question,
					$options
				) . "\n";
		}
		if ( $prevStatus ) {
			$s = $this->formatStatus( $prevStatus ) . $s;
		}

		return $s;
	}

	public function setErrorStatus( $status ) {
		if ( $status ) {
			$this->prevErrorIds = $status->sp_getIds();
			$this->prevStatus = $status;
		} else {
			$this->prevErrorIds = [];
		}
		$this->usedErrorIds = [];
	}

	public function errorLocationIndicator( $id ) {
		if ( !isset( $this->prevErrorIds[$id] ) ) {
			return '';
		}
		$this->usedErrorIds[$id] = true;

		return Xml::element(
			'img',
			[
				'src' => $this->context->getResourceUrl( 'warning-22.png' ),
				'width' => 22,
				'height' => 22,
				'id' => "$id-location",
				'class' => 'securepoll-error-location',
				'alt' => '',
				'title' => $this->prevStatus->sp_getMessageText( $id )
			]
		);
	}

	/**
	 * Convert a BallotStatus object to HTML
	 * @param BallotStatus $status
	 * @return string
	 */
	public function formatStatus( $status ) {
		return $status->sp_getHTML( $this->usedErrorIds );
	}

	/**
	 * Get the way the voter cast their vote previously, if we're allowed
	 * to show that information.
	 * @return false on failure or if cast ballots are hidden, or the output
	 *     of unpackRecord().
	 */
	public function getCurrentVote() {
		// FIXME: getOption doesn't exist
		// @phan-suppress-next-line PhanUndeclaredMethod
		if ( !$this->election->getOption( 'show-change' ) ) {
			return false;
		}

		$auth = $this->election->getAuth();

		# Get voter from session
		$voter = $auth->getVoterFromSession( $this->election );
		# If there's no session, try creating one.
		# This will fail if the user is not authorized to vote in the election
		if ( !$voter ) {
			$status = $auth->newAutoSession( $this->election );
			if ( $status->isOK() ) {
				$voter = $status->value;
			} else {
				return false;
			}
		}

		$store = $this->context->getStore();
		$status = $store->callbackValidVotes(
		// FIXME: Where is info property defined?
		// @phan-suppress-next-line PhanUndeclaredProperty
			$this->election->info['id'],
			[
				$this,
				'getCurrentVoteCallback'
			],
			$voter->getId()
		);
		if ( !$status->isOK() ) {
			return false;
		}

		return isset( $this->currentVote ) ? $this->unpackRecord( $this->currentVote ) : false;
	}

	public function getCurrentVoteCallback( $store, $record ) {
		$this->currentVote = $record;

		return Status::newGood();
	}
}
