<?php

namespace MediaWiki\Extension\SecurePoll\HtmlForm;

use MediaWiki\HTMLForm\HTMLFormField;
use OOUI;

/**
 * A table for the RadioRangeBallot message inputs.
 */
class HTMLFormRadioRangeColumnLabels extends HTMLFormField {
	public function loadDataFromRequest( $request ) {
		$values = $request->getArray( $this->mName );
		if ( $values === null ) {
			return $this->getDefault();
		}

		$neg = false;
		foreach ( $values as $k => $v ) {
			if ( preg_match( '/^-\d+$/', $k ) ) {
				$neg = true;
			}
		}

		$ret = [];
		foreach ( $values as $k => $v ) {
			if ( preg_match( '/^-?\d+$/', $k ) ) {
				$key = ( $neg && $k > 0 ) ? "+$k" : $k;
				$ret["column$key"] = $v;
			}
		}

		return $ret;
	}

	public function validate( $value, $alldata ) {
		// Don't bother to validate the value of HTMLFormCloner template.
		if ( strpos( $this->mName, 'HTMLFormFieldCloner' ) ) {
			return true;
		}

		$p = parent::validate( $value, $alldata );
		if ( $p !== true ) {
			return $p;
		}

		$min = (string)$this->getNearestFieldValue( $alldata, 'min-score' );
		$max = (string)$this->getNearestFieldValue( $alldata, 'max-score' );
		if ( !preg_match( '/^-?\d+$/', $min ) || !preg_match( '/^-?\d+$/', $max ) ) {
			return true;
		}

		for ( $i = $min; $i <= $max; $i++ ) {
			$key = ( $min < 0 && $i > 0 ) ? "+$i" : $i;
			if ( !isset( $value["column$key"] ) ) {
				return $this->msg(
					'securepoll-htmlform-radiorange-missing-message',
					$key
				)->parseAsBlock();
			}
		}

		return true;
	}

	public function getInputHTML( $value ) {
		$inputs = [];

		foreach ( (array)$value as $k => $v ) {
			if ( !preg_match( '/^column([-+]?)(\d+)$/', $k, $m ) ) {
				// Ignore "text"
				continue;
			}
			$signedColNum = $m[1] . $m[2];
			$intColNum = $m[1] === '-' ? $signedColNum : $m[2];

			$inputs[] = ( new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'value' => $v,
					'name' => "{$this->mName}[$intColNum]",
					'disabled' => isset( $this->mParams['disabled'] ) && $this->mParams['disabled'],
				] ),
				[
					'label' => $signedColNum,
					'align' => 'top',
				]
			) )->setAttributes( [ 'data-securepoll-col-num' => $intColNum ] );
		}

		return ( new OOUI\Widget( [
			'content' => new OOUI\HorizontalLayout( [ 'items' => $inputs ] ),
			'classes' => [ 'securepoll-radiorange-messages' ],
		] ) )->setAttributes( [ 'data-securepoll-col-name' => $this->mName, ] );
	}
}
