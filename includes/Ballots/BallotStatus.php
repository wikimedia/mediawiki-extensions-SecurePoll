<?php

namespace MediaWiki\Extensions\SecurePoll\Ballots;

use MediaWiki\Extensions\SecurePoll\Context;
use Status;
use Xml;

class BallotStatus extends Status {
	/** @var Context */
	public $sp_context;
	/** @var true[] */
	public $sp_ids = [];

	public function __construct( $context ) {
		$this->sp_context = $context;
	}

	public function sp_fatal( $message, $id, ...$params ) {
		$this->errors[] = [
			'type' => 'error',
			'securepoll-id' => $id,
			'message' => $message,
			'params' => $params
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
			$text = wfMessage( $error['message'], $error['params'] )->text();
			if ( isset( $error['securepoll-id'] ) ) {
				$id = $error['securepoll-id'];
				if ( isset( $usedIds[$id] ) ) {
					$s .= '<li>' . Xml::openElement(
							'a',
							[
								'href' => '#' . urlencode( "$id-location" ),
								'class' => 'securepoll-error-jump'
							]
						) . Xml::element(
							'img',
							[
								'alt' => '',
								'src' => $this->sp_context->getResourceUrl( 'down-16.png' ),
							]
						) . '</a>' . htmlspecialchars( $text ) . "</li>\n";
					continue;
				}
			}
			$s .= '<li>' . htmlspecialchars( $text ) . "</li>\n";
		}
		$s .= "</ul>\n";

		return $s;
	}

	public function sp_getMessageText( $id ) {
		foreach ( $this->errors as $error ) {
			if ( $error['securepoll-id'] !== $id ) {
				continue;
			}

			return wfMessage( $error['message'], $error['params'] )->text();
		}
	}
}
