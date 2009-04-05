<?php

class SecurePoll_Page {
	var $parent, $election, $auth, $user;
	
	function __construct( $parent ) {
		$this->parent = $parent;
	}

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
