<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

class MessageDumpPage extends ActionPage {
	/**
	 * @param array $params
	 * @suppress SecurityCheck-XSS Mime type is not html so all false positive
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );

			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );

			return;
		}

		$out->disable();
		header( 'Content-Type: application/x-sql; charset=utf-8' );
		$filename = urlencode( "sp-msgs-$electionId-" . wfTimestampNow() . '.sql' );
		header( "Content-Disposition: attachment; filename=$filename" );
		$dbr = $this->context->getDB();

		$entities = array_merge( [ $this->election ], $this->election->getDescendants() );
		$ids = [];
		foreach ( $entities as $entity ) {
			$ids[] = $entity->getId();
		}

		$res = $dbr->select(
			'securepoll_msgs',
			'*',
			[ 'msg_entity' => $ids ],
			__METHOD__
		);

		if ( !$res->numRows() ) {
			return;
		}
		echo "INSERT INTO securepoll_msgs (msg_entity,msg_lang, msg_key, msg_text) VALUES\n";
		$first = true;
		foreach ( $res as $row ) {
			$values = [
				$row->msg_entity,
				$row->msg_lang,
				$row->msg_key,
				$row->msg_text
			];
			if ( $first ) {
				$first = false;
			} else {
				echo ",\n";
			}
			echo '(' . implode(
					', ',
					array_map(
						[
							$dbr,
							'addQuotes'
						],
						$values
					)
				) . ')';
		}
		echo ";\n";
	}
}
