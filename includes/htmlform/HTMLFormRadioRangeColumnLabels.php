<?php

/**
 * A table for the RadioRangeBallot message inputs.
 */
class SecurePoll_HTMLFormRadioRangeColumnLabels extends HTMLFormField {
	public function getSize() {
		return 10;
	}

	public function loadDataFromRequest( $request ) {
		$values = $request->getArray( $this->mName, false );
		if ( $values === false ) {
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
		// @phan-suppress-next-line PhanTypeMismatchReturn
		return $ret;
	}

	public function validate( $value, $alldata ) {
		$p = parent::validate( $value, $alldata );

		if ( $p !== true ) {
			return $p;
		}

		$min = $this->getNearestFieldByName( $alldata, 'min-score' );
		$max = $this->getNearestFieldByName( $alldata, 'max-score' );
		if ( !preg_match( '/^-?\d+$/', $min ) || !preg_match( '/^-?\d+$/', $max ) ) {
			return true;
		}

		for ( $i = $min; $i <= $max; $i++ ) {
			$key = ( $min < 0 && $i > 0 ) ? "+$i" : $i;
			if ( !isset( $value["column$key"] ) ) {
				return $this->msg( 'securepoll-htmlform-radiorange-missing-message', $key )
					->parseAsBlock();
			}
		}

		return true;
	}

	public function getInputHTML( $value ) {
		$size = $this->getSize();

		$labels = '';
		$inputs = '';
		foreach ( (array)$value as $k => $v ) {
			$k = str_replace( 'column', '', $k );
			$labels .= Html::element( 'th', [ 'data-securepoll-col-num' => $k ], $k );
			$inputs .= Html::rawElement( 'td', [ 'data-securepoll-col-num' => $k ],
				Html::element( 'input', [
					'type' => 'text',
					'name' => "{$this->mName}[$k]",
					'size' => $size,
					'value' => $v,
				] )
			);
		}

		$class = 'securepoll-radiorange-messages';
		if ( $this->mClass !== '' ) {
			$attribs['class'] .= " $this->mClass";
		}

		$html = Html::rawElement( 'table', [
				'class' => $class,
				'data-securepoll-col-name' => $this->mName,
				'data-securepoll-input-size' => $size,
			],
			Html::rawElement( 'tr', [ 'class' => 'securepoll-label-row' ], $labels ) .
				Html::rawElement( 'tr', [ 'class' => 'securepoll-input-row' ], $inputs )
		);

		return $html;
	}

}
