<?php

class SecurePoll_EntryPage extends SecurePoll_Page {
	function execute() {
		global $wgOut;
		$wgOut->addWikiMsg( 'securepoll_entry' );
	}
}
