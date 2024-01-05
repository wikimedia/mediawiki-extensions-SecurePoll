<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Pager\TablePager;
use stdClass;

/**
 * Parent class for election pagers:
 * - unarchived elections
 * - archived elections
 */
abstract class ElectionPager extends TablePager {
	/** @var array[] */
	private $subpages = [];
	/** @var bool|null */
	public $isAdmin;
	/** @var Election|null */
	public $election;
	/** @var ArchivedPage|EntryPage */
	public $page;
	/** @var string[] */
	private const FIELDS = [
		'el_title',
		'el_start_date',
		'el_end_date',
		'links'
	];

	public function __construct() {
		parent::__construct();
	}

	protected function isFieldSortable( $field ) {
		return in_array(
			$field,
			[
				'el_title',
				'el_start_date',
				'el_end_date'
			]
		);
	}

	/**
	 * Add classes based on whether the poll is open or closed
	 * @param stdClass $row database object
	 * @return string
	 * @see TablePager::getRowClass()
	 */
	protected function getRowClass( $row ) {
		return $row->el_end_date > wfTimestampNow()
			? 'securepoll-election-open' : 'securepoll-election-closed';
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return string HTML
	 */
	public function formatValue( $name, $value ) {
		switch ( $name ) {
			case 'el_start_date':
			case 'el_end_date':
				return htmlspecialchars( $this->getLanguage()->timeanddate( $value ) );
			case 'links':
				return $this->getLinks();
			default:
				return htmlspecialchars( $value );
		}
	}

	public function formatRow( $row ) {
		$id = $row->el_entity;
		$this->election = $this->page->context->getElection( $id );
		if ( !$this->election ) {
			$this->isAdmin = false;
		} else {
			$this->isAdmin = $this->election->isAdmin( $this->getUser() );
		}

		return parent::formatRow( $row );
	}

	/**
	 * Return html for election-specific links
	 * @return string HTML
	 */
	abstract public function getLinks();

	public function getDefaultSort() {
		return 'el_start_date';
	}

	protected function getFieldNames() {
		$names = [];
		foreach ( self::FIELDS as $field ) {
			if ( $field == 'links' ) {
				$names[$field] = '';
			} else {
				// Give grep a chance to find the usages:
				// securepoll-header-title, securepoll-header-start-date,
				// securepoll-header-end-date
				$msgName = 'securepoll-header-' . strtr(
						$field,
						[
							'el_' => '',
							'_' => '-'
						]
					);
				$names[$field] = $this->msg( $msgName )->text();
			}
		}

		return $names;
	}

	public function getTitle() {
		return $this->page->getTitle();
	}
}
