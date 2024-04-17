<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Status\Status;
use OOUI\ButtonInputWidget;
use OOUI\MessageWidget;

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
		$s = '<ul>';
		$usedIds = [];
		$text = '';
		foreach ( $this->errors as $error ) {
			if ( !isset( $error['localized'] ) || !$error['localized'] ) {
				$text = wfMessage( $error['message'], $error['params'] )->text();
				if ( isset( $error['securepoll-id'] ) ) {
					$id = $error['securepoll-id'];
					if ( !isset( $usedIds[$id] ) ) {
						$name = explode( '_', urlencode( "$id" ) );
						$usedIds[ $name[0] . '_' . $name[1] ][] = urlencode( "$id" );
					}
				} elseif ( !isset( $error['securepoll-id'] ) ) {
					$s .= new MessageWidget( [
						'type' => 'error',
						'label' => htmlspecialchars( $text )
					] ) . "\n";
				}
			}
		}

		if ( count( $usedIds ) > 0 ) {
			$error = new MessageWidget( [
				'type' => 'error',
				'label' => htmlspecialchars( $text )
			] );
			$buttonWidget = new ButtonInputWidget( [
				'label' => wfMessage( 'securepoll-ballot-show-warnings' )->text(),
				'classes' => [ 'highlight-warnings-button' ],
				'infusable' => true,
				'data' => json_encode( $usedIds ),
				'showErrors' => false
			] );
			$error->appendContent( $buttonWidget );
			$s .= $error . "<br>";
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
