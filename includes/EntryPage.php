<?php

/**
 * The entry page for SecurePoll. Shows a list of elections.
 */
class SecurePoll_EntryPage extends SecurePoll_Page {
	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	function execute( $params ) {
		global $wgOut;
		$pager = new SecurePoll_ElectionPager( $this );
		$wgOut->addHTML( 
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * @return Title
	 */
	function getTitle() {
		return $this->parent->getTitle( 'entry' );
	}
}

/**
 * Pager for an election list. See TablePager documentation.
 */
class SecurePoll_ElectionPager extends TablePager {
	var $subpages = array(
		'vote',
		'translate',
		'list',
		'dump',
		'tally'
	);
	var $fields = array(
		'el_title',
		'el_start_date',
		'el_end_date',
		'links'
	);
	var $entryPage;

	function __construct( $parent ) {
		$this->entryPage = $parent;
		parent::__construct();
	}

	function getQueryInfo() {
		return array(
			'tables' => 'securepoll_elections',
			'fields' => '*',
			'conds' => false,
			'options' => array()
		);
	}

	function isFieldSortable( $field ) {
		return in_array( $field, array(
			'el_title', 'el_start_date', 'el_end_date' 
		) );
	}

	function formatValue( $name, $value ) {
		global $wgLang;
		switch ( $name ) {
		case 'el_start_date':
		case 'el_end_date':
			return $wgLang->timeanddate( $value );
		case 'links':
			return $this->getLinks();
		default:
			return htmlspecialchars( $value );
		}
	}

	function getLinks() {
		global $wgUser;
		$id = $this->mCurrentRow->el_entity;
		$s = '';
		$sep = wfMsg( 'pipe-separator' );
		$skin = $wgUser->getSkin();
		foreach ( $this->subpages as $subpage ) {
			$title = $this->entryPage->parent->getTitle( "$subpage/$id" );
			$linkText = wfMsg( "securepoll-subpage-$subpage" );
			if ( $s !== '' ) {
				$s .= $sep;
			}
			$s .= $skin->makeKnownLinkObj( $title, $linkText );
		}
		return $s;
	}

	function getDefaultSort() {
		return 'el_start_date';
	}

	function getFieldNames() {
		$names = array();
		foreach ( $this->fields as $field ) {
			if ( $field == 'links' ) {
				$names[$field] = '';
			} else {
				$msgName = 'securepoll-header-' . 
					strtr( $field, array( 'el_' => '', '_' => '-' ) );
				$names[$field] = wfMsg( $msgName );
			}
		}
		return $names;
	}

	function getTitle() {
		return $this->entryPage->getTitle();
	}
}
	
