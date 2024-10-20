<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use InvalidArgumentException;
use LogicException;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Entities\Entity;
use MediaWiki\Extension\SecurePoll\Entities\Question;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Request\WebRequest;
use MediaWiki\Status\Status;
use MessageLocalizer;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\IconWidget;

/**
 * Parent class for ballot forms. This is the UI component of a voting method.
 */
abstract class Ballot {
	/** @var Election */
	public $election;
	/** @var Context */
	public $context;
	/** @var string|null */
	public $currentVote;
	/** @var true[]|null */
	public $prevErrorIds;
	/** @var true[]|null */
	public $usedErrorIds;
	/** @var BallotStatus|null */
	public $prevStatus;
	/** @var WebRequest|null */
	private $request;
	/** @var MessageLocalizer|null */
	private $messageLocalizer;
	/** @var Language|null */
	private $userLang;

	/** @var string[] */
	public const BALLOT_TYPES = [
		'approval' => ApprovalBallot::class,
		'preferential' => PreferentialBallot::class,
		'choose' => ChooseBallot::class,
		'radio-range' => RadioRangeBallot::class,
		'radio-range-comment' => RadioRangeCommentBallot::class,
		'stv' => STVBallot::class,
	];

	/**
	 * Get a list of names of tallying methods, which may be used to produce a
	 * result from this ballot type.
	 * @return array
	 */
	public static function getTallyTypes() {
		throw new LogicException( "Subclass must override ::getTallyTypes()" );
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
	 * @return FieldsetLayout
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
	 * Get the request if it has been set, otherwise throw an exception.
	 *
	 * @return WebRequest
	 */
	protected function getRequest(): WebRequest {
		if ( !$this->request ) {
			throw new LogicException(
				'Ballot::initRequest() must be called before Ballot::getRequest()' );
		}
		return $this->request;
	}

	/**
	 * Get the MessageLocalizer if it has been set, otherwise throw an exception
	 *
	 * @return MessageLocalizer
	 */
	private function getMessageLocalizer(): MessageLocalizer {
		if ( !$this->messageLocalizer ) {
			throw new LogicException(
				'Ballot::initRequest() must be called before Ballot::getMessageLocalizer()' );
		}
		return $this->messageLocalizer;
	}

	/**
	 * Get a MediaWiki message. setMessageLocalizer() must have been called.
	 *
	 * This can be used instead of SecurePoll's native message system if the
	 * message does not vary depending on the election, and if there are no
	 * security concerns with allowing people who are not admins of the election
	 * to set the text.
	 *
	 * @param string $key
	 * @param mixed ...$params
	 * @return Message
	 */
	protected function msg( $key, ...$params ) {
		return $this->getMessageLocalizer()->msg( $key, ...$params );
	}

	/**
	 * Get the user language, or throw an exception if it has not been set.
	 * @return Language
	 */
	protected function getUserLang(): Language {
		return $this->userLang;
	}

	/**
	 * Set request dependencies
	 *
	 * @param WebRequest $request
	 * @param MessageLocalizer $localizer
	 * @param Language $userLang
	 */
	public function initRequest(
		WebRequest $request,
		MessageLocalizer $localizer,
		Language $userLang
	) {
		$this->request = $request;
		$this->messageLocalizer = $localizer;
		$this->userLang = $userLang;
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
		$status = new BallotStatus();

		foreach ( $questions as $question ) {
			$record .= $this->submitQuestion( $question, $status );
		}
		if ( $status->isOK() ) {
			$status->value = $record;
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
	 * @return string[]|false
	 */
	public function convertRecord( $record, $options = [] ) {
		$scores = $this->unpackRecord( $record );

		return $this->convertScores( $scores );
	}

	/**
	 * Convert a score array to a string of some kind
	 * @param array $scores
	 * @param array $options
	 * @return string|string[]|false
	 */
	abstract public function convertScores( $scores, $options = [] );

	/**
	 * Create a ballot of the given type
	 * @param Context $context
	 * @param string $type
	 * @param Election $election
	 * @return Ballot
	 * @throws InvalidArgumentException
	 */
	public static function factory( $context, $type, $election ) {
		if ( !isset( self::BALLOT_TYPES[$type] ) ) {
			throw new InvalidArgumentException( "Invalid ballot type: $type" );
		}
		$class = self::BALLOT_TYPES[$type];

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
	 * @return Element[]
	 */
	public function getForm( $prevStatus = false ) {
		$questions = $this->election->getQuestions();
		if ( $this->election->getProperty( 'shuffle-questions' ) ) {
			shuffle( $questions );
		}
		$shuffleOptions = $this->election->getProperty( 'shuffle-options' );
		$this->setErrorStatus( $prevStatus );

		$itemArray = [];
		foreach ( $questions as $question ) {
			$options = $question->getOptions();
			if ( $shuffleOptions ) {
				shuffle( $options );
			}

			$questionForm = $this->getQuestionForm(
					$question,
					$options
			);
			$questionForm->setLabel(
				new HtmlSnippet( $question->parseMessage( 'text' ) )
			);
			$itemArray[] = $questionForm;
		}
		if ( $prevStatus ) {
			$formStatus = new Element( [
				'content' => new HTMLSnippet(
					$this->formatStatus( $prevStatus )
				),
			] );
			array_unshift( $itemArray, $formStatus );
		}

		return $itemArray;
	}

	/**
	 * @param bool|BallotStatus $status
	 */
	public function setErrorStatus( $status ) {
		if ( $status ) {
			$this->prevErrorIds = $status->getIds();
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

		return new IconWidget( [
			'icon' => 'error',
			'title' => $this->prevStatus->spGetMessageText( $id ),
			'id' => "$id-location",
			'classes' => [ 'securepoll-error-location' ],
			'flags' => 'error',
		 ] );
	}

	/**
	 * Convert a BallotStatus object to HTML
	 * @param BallotStatus $status
	 * @return string
	 */
	public function formatStatus( $status ) {
		return $status->spGetHTML( $this->usedErrorIds );
	}
}
