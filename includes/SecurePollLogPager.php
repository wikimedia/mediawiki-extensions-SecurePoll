<?php

namespace MediaWiki\Extensions\SecurePoll;

use HTML;
use Linker;
use MediaWiki\Extensions\SecurePoll\Pages\ActionPage;
use MediaWiki\User\UserFactory;
use ReverseChronologicalPager;

class SecurePollLogPager extends ReverseChronologicalPager {
	/** @var Context */
	private $context;

	/** @var UserFactory */
	private $userFactory;

	/** @var string */
	private $type;

	/** @var string */
	private $performer;

	/** @var string */
	private $target;

	/** @var string */
	private $electionName;

	/**
	 * @param Context $context
	 * @param UserFactory $userFactory
	 * @param string $type
	 * @param string $performer
	 * @param string $target
	 * @param string $electionName
	 */
	public function __construct(
		Context $context,
		UserFactory $userFactory,
		string $type,
		string $performer,
		string $target,
		string $electionName
	) {
		parent::__construct();
		$this->context = $context;
		$this->userFactory = $userFactory;
		$this->type = $type;
		$this->performer = $performer;
		$this->target = $target;
		$this->electionName = $electionName;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return [
			'tables' => [ 'securepoll_log' ],
			'fields' => [
				'spl_id',
				'spl_timestamp',
				'spl_election_id',
				'spl_user',
				'spl_type',
				'spl_target',
			],
			'conds' => $this->getFilterConds(),
		];
	}

	private function getFilterConds() {
		$conds = [];

		switch ( $this->type ) {
			case 'voter':
				$type = ActionPage::LOG_TYPE_VIEWVOTES;
				break;
			case 'admin':
				$type = [
					ActionPage::LOG_TYPE_ADDADMIN,
					ActionPage::LOG_TYPE_REMOVEADMIN,
				];
				break;
			default:
				$type = null;
				break;
		}
		if ( $type ) {
			$conds['spl_type'] = $type;
		}

		if ( $this->performer ) {
			$performer = $this->userFactory->newFromName( $this->performer )->getId();
			$conds['spl_user'] = $performer;
		}

		if ( $this->target ) {
			$target = $this->userFactory->newFromName( $this->target )->getId();
			$conds['spl_target'] = $target;
		}

		if ( $this->electionName ) {
			$electionId = $this->context->getElectionByTitle( $this->electionName )->getId();
			$conds['spl_election_id'] = $electionId;
		}

		return $conds;
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'spl_timestamp', 'spl_id' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$timestamp = $this->getLanguage()->timeanddate(
			wfTimestamp( TS_MW, $row->spl_timestamp ),
			true
		);

		$user = $this->userFactory->newFromId( $row->spl_user );
		$userLink = Linker::userLink( $user->getId(), $user->getName() );

		$election = $this->context->getElection( $row->spl_election_id );
		$electionTitle = htmlspecialchars( $election->title );

		$messageParams = [
			$timestamp,
			$userLink,
			$electionTitle,
		];

		if ( $row->spl_target ) {
			$target = $this->userFactory->newFromId( $row->spl_target );
			$messageParams[] = Linker::userLink( $target->getId(), $target->getName() );
		}

		$message = $this->msg(
			'securepoll-log-action-type-' . $row->spl_type,
			$messageParams
		)->text();

		return HTML::rawElement( 'li', [], $message );
	}

	/**
	 * @inheritDoc
	 */
	public function getStartBody() {
		return $this->getNumRows() ? '<ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEndBody() {
		return $this->getNumRows() ? '</ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEmptyBody() {
		return HTML::rawElement( 'p', [], $this->msg( 'securepoll-log-empty' )->text() );
	}
}
