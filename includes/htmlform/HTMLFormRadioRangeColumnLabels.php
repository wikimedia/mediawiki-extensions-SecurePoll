<?php

/**
 * A table for the RadioRangeBallot message inputs.
 */
class SecurePoll_HTMLFormRadioRangeColumnLabels extends HTMLFormField {
	function getSize() {
		return 10;
	}

	function loadDataFromRequest( $request ) {
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

		$ret = array();
		foreach ( $values as $k => $v ) {
			if ( preg_match( '/^-?\d+$/', $k ) ) {
				$key = ( $neg && $k > 0 ) ? "+$k" : $k;
				$ret["column$key"] = $v;
			}
		}
		return $ret;
	}

	function validate( $value, $alldata ) {
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

	function getInputHTML( $value ) {
		$size = $this->getSize();

		$labels = '';
		$inputs = '';
		foreach ( (array)$value as $k => $v ) {
			$k = str_replace( 'column', '', $k );
			$labels .= Html::element( 'th', array( 'data-securepoll-col-num' => $k ), $k );
			$inputs .= Html::rawElement( 'td', array( 'data-securepoll-col-num' => $k ),
				Html::element( 'input', array(
					'type' => 'text',
					'name' => "{$this->mName}[$k]",
					'size' => $size,
					'value' => $v,
				) )
			);
		}

		$class = 'securepoll-radiorange-messages';
		if ( $this->mClass !== '' ) {
			$attribs['class'] .= " $this->mClass";
		}

		$html = Html::rawElement( 'table', array(
				'class' => $class,
				'data-securepoll-col-name' => $this->mName,
				'data-securepoll-input-size' => $size,
			),
			Html::rawElement( 'tr', array( 'class' => 'securepoll-label-row' ), $labels ) .
				Html::rawElement( 'tr', array( 'class' => 'securepoll-input-row' ), $inputs )
		);

		return $html;
	}

}
