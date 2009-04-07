<?php

/**
 * Parent class for Special:SecurePoll subpages.
 */
abstract class SecurePoll_Page {
	var $parent, $election, $auth, $user;

	/**
	 * Constructor.
	 * @param $parent SecurePollPage
	 */
	function __construct( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Execute the subpage.
	 * @param $params array Array of subpage parameters.
	 */
	abstract function execute( $params );

	/**
	 * Internal utility function for initialising the global entity language 
	 * fallback sequence.
	 */
	function initLanguage( $user, $election ) {
		if ( $user instanceof SecurePoll_Voter ) {
			$userLang = $user->getLanguage();
		} else {
			$userLang = $user->getOption( 'language' );
		}
		$languages = array( 
			$userLang, 
			$election->getLanguage(),
			'en'
		);
		$languages = array_unique( $languages );
		SecurePoll_Entity::setLanguages( $languages );
	}
}
