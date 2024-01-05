<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Status\Status;
use OOUI\ButtonWidget;
use OOUI\HtmlSnippet;
use OOUI\Tag;

class BallotStatus extends Status {
	/** @var true[] */
	public $ids = [];

	public function spFatal( $message, $id, $localized, ...$params ) {
		$this->errors[] = [
			'type' => 'error',
			'securepoll-id' => $id,
			'message' => $message,
			'params' => $params,
			'localized' => $localized
		];
		$this->ids[$id] = true;
		$this->ok = false;
	}

	public function getIds() {
		return $this->ids;
	}

	public function spGetHTML( $usedIds ) {
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
						$error = new Tag( 'li' );
						$error->appendContent(
							new HtmlSnippet( htmlspecialchars( $text ) )
						);
						$error->appendContent(
							new ButtonWidget( [
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

	public function spGetMessageText( $id ) {
		foreach ( $this->errors as $error ) {
			if ( !isset( $error['securepoll-id'] ) || $error['securepoll-id'] !== $id ) {
				continue;
			}

			return wfMessage( $error['message'], $error['params'] )->text();
		}
	}
}
