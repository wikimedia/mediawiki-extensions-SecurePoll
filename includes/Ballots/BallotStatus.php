<?php

namespace MediaWiki\Extension\SecurePoll\Ballots;

use MediaWiki\Status\Status;
use OOUI\ButtonInputWidget;
use OOUI\MessageWidget;
use Wikimedia\Message\MessageParam;
use Wikimedia\Message\MessageSpecifier;

class BallotStatus extends Status {
	/** @var true[] */
	public $ids = [];

	/**
	 * @param string|MessageSpecifier $message
	 * @param string $id
	 * @param bool $localized
	 * @phpcs:ignore Generic.Files.LineLength
	 * @param MessageParam|MessageSpecifier|string|int|float|list<MessageParam|MessageSpecifier|string|int|float> ...$params
	 */
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

	/**
	 * @return true[]
	 */
	public function getIds() {
		return $this->ids;
	}

	/**
	 * @param array $usedIds
	 * @return string
	 */
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

	/**
	 * @param string $id
	 * @return string
	 */
	public function spGetMessageText( $id ) {
		foreach ( $this->errors as $error ) {
			if ( !isset( $error['securepoll-id'] ) || $error['securepoll-id'] !== $id ) {
				continue;
			}

			return wfMessage( $error['message'], $error['params'] )->text();
		}
	}
}
