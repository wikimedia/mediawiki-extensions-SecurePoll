<?php

namespace MediaWiki\Extension\SecurePoll\Jobs;

use Job;
use MediaWiki\Extension\SecurePoll\Context;

/**
 * Log whenever an admin looks at Special:SecurePoll/list/{id}
 */
class LogAdminActionJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'securePollLogAdminAction', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$context = new Context();
		$dbw = $context->getDB( DB_PRIMARY );
		$fields = $this->params['fields'];
		$fields['spl_timestamp'] = $dbw->timestamp( time() );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_log' )
			->row( $fields )
			->caller( __METHOD__ )
			->execute();
		return true;
	}
}
