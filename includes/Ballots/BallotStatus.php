<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use MediaWiki\Extensions\SecurePoll\Context;
use Status;

class BallotStatus extends Status {
	/** @var Context */
	public $sp_context;
	/** @var true[] */
	public $sp_ids = [];

	public function __construct( $context ) {
		$this->sp_context = $context;
	}

	public function sp_fatal( $message, $id, $localized, ...$params ) {
		$this->errors[] = [
			'type' => 'error',
			'securepoll-id' => $id,
			'message' => $message,
			'params' => $params,
			'localized' => $localized
		];
		$this->sp_ids[$id] = true;
		$this->ok = false;
	}

	public function sp_getIds() {
		return $this->sp_ids;
	}

	public function sp_getHTML( $usedIds ) {
		if ( !$this->errors ) {
			return '';
		}
		$s = '<ul class="securepoll-error-box">';
		foreach ( $this->errors as $error ) {
			if ( !isset( $error['localized'] ) || !$error['localized'] ) {
				$text = wfMessage( $error['message'], $error['params'] )->text();
				if ( isset( $error['securepoll-id'] ) ) {
					$id = $error['securepoll-id'];
					if ( isset( $usedIds[$id] ) ) {
						$error = new \OOUI\Tag( 'li' );
						$error->appendContent(
							new \OOUI\HtmlSnippet( htmlspecialchars( $text ) )
						);
						$error->appendContent(
							new \OOUI\ButtonWidget( [
								'icon' => 'downTriangle',
								'label' => wfMessage( 'securepoll-ballot-see-error' )->text(),
								'href' => '#' . urlencode( "$id-location" ),
							] )
						);
						$s .= $error;
						continue;
					}
				}
				$s .= '<li>' . htmlspecialchars( $text ) . "</li>\n";
			}
		}
		$s .= "</ul>\n";

		return $s;
	}

	public function sp_getMessageText( $id ) {
		foreach ( $this->errors as $error ) {
			if ( !isset( $error['securepoll-id'] ) || $error['securepoll-id'] !== $id ) {
				continue;
			}

			return wfMessage( $error['message'], $error['params'] )->text();
		}
	}
}
