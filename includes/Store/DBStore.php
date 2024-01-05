<?php

namespace MediaWiki\Extension\SecurePoll\Store;

use MediaWiki\Status\Status;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Storage class for a DB backend. This is the one that's most often used.
 */
class DBStore implements Store {

	/** @var bool */
	private $forcePrimary = false;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var string|bool */
	private $wiki;

	/**
	 * DBStore constructor.
	 * @param ILoadBalancer $loadBalancer The load balancer used to get connection objects
	 * @param string|bool $wiki The wiki ID or false to use the local wiki
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		$wiki = false
	) {
		$this->loadBalancer = $loadBalancer;
		$this->wiki = $wiki;
	}

	public function getMessages( $lang, $ids ) {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_msgs',
			'*',
			[
				'msg_entity' => $ids,
				'msg_lang' => $lang
			],
			__METHOD__
		);
		$messages = [];
		foreach ( $res as $row ) {
			$messages[$row->msg_entity][$row->msg_key] = $row->msg_text;
		}

		return $messages;
	}

	public function getLangList( $ids ) {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_msgs',
			'DISTINCT msg_lang',
			[
				'msg_entity' => $ids
			],
			__METHOD__
		);
		$langs = [];
		foreach ( $res as $row ) {
			$langs[] = $row->msg_lang;
		}

		return $langs;
	}

	public function getProperties( $ids ) {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_properties',
			'*',
			[ 'pr_entity' => $ids ],
			__METHOD__
		);
		$properties = [];
		foreach ( $res as $row ) {
			$properties[$row->pr_entity][$row->pr_key] = $row->pr_value;
		}

		return $properties;
	}

	public function getElectionInfo( $ids ) {
		$ids = (array)$ids;
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_elections',
			'*',
			[ 'el_entity' => $ids ],
			__METHOD__
		);
		$infos = [];
		foreach ( $res as $row ) {
			$infos[$row->el_entity] = $this->decodeElectionRow( $row );
		}

		return $infos;
	}

	public function getElectionInfoByTitle( $names ) {
		$names = (array)$names;
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			'securepoll_elections',
			'*',
			[ 'el_title' => $names ],
			__METHOD__
		);
		$infos = [];
		foreach ( $res as $row ) {
			$infos[$row->el_title] = $this->decodeElectionRow( $row );
		}

		return $infos;
	}

	public function decodeElectionRow( $row ) {
		static $map = [
			'id' => 'el_entity',
			'title' => 'el_title',
			'ballot' => 'el_ballot',
			'tally' => 'el_tally',
			'primaryLang' => 'el_primary_lang',
			'startDate' => 'el_start_date',
			'endDate' => 'el_end_date',
			'auth' => 'el_auth_type',
			'owner' => 'el_owner'
		];

		$info = [];
		foreach ( $map as $key => $field ) {
			if ( $key == 'startDate' || $key == 'endDate' ) {
				$info[$key] = wfTimestamp( TS_MW, $row->$field );
			} elseif ( isset( $row->$field ) ) {
				$info[$key] = $row->$field;
			}
		}

		return $info;
	}

	public function getDB( $index = DB_PRIMARY ) {
		return $this->loadBalancer->getConnection(
			$this->forcePrimary ? DB_PRIMARY : $index,
			[],
			$this->wiki
		);
	}

	public function setForcePrimary( $forcePrimary ) {
		$this->forcePrimary = $forcePrimary;
	}

	public function getQuestionInfo( $electionId ) {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->select(
			[
				'securepoll_questions',
				'securepoll_options'
			],
			'*',
			[
				'qu_election' => $electionId,
				'op_question=qu_entity'
			],
			__METHOD__,
			[ 'ORDER BY' => 'qu_index, qu_entity' ]
		);

		$questions = [];
		$options = [];
		$questionId = false;
		$electionId = false;
		foreach ( $res as $row ) {
			if ( $questionId === false ) {
			} elseif ( $questionId !== $row->qu_entity ) {
				$questions[] = [
					'id' => $questionId,
					'election' => $electionId,
					'options' => $options
				];
				$options = [];
			}
			$options[] = [
				'id' => $row->op_entity,
				'election' => $row->op_election,
			];
			$questionId = $row->qu_entity;
			$electionId = $row->qu_election;
		}
		if ( $questionId !== false ) {
			$questions[] = [
				'id' => $questionId,
				'election' => $electionId,
				'options' => $options
			];
		}

		return $questions;
	}

	public function callbackValidVotes( $electionId, $callback, $voterId = null ) {
		$dbr = $this->getDB( DB_REPLICA );
		$where = [
			'vote_election' => $electionId,
			'vote_current' => 1,
			'vote_struck' => 0
		];
		if ( $voterId !== null ) {
			$where['vote_voter'] = $voterId;
		}
		$res = $dbr->select(
			'securepoll_votes',
			'*',
			$where,
			__METHOD__
		);

		foreach ( $res as $row ) {
			$status = call_user_func( $callback, $this, $row->vote_record );
			if ( $status instanceof Status && !$status->isOK() ) {
				return $status;
			}
		}

		return Status::newGood();
	}

	public function getEntityType( $id ) {
		$db = $this->getDB( DB_REPLICA );
		$res = $db->selectRow(
			'securepoll_entity',
			'*',
			[ 'en_id' => $id ],
			__METHOD__
		);

		return $res ? $res->en_type : false;
	}
}
