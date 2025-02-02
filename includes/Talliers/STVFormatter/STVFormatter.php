<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

interface STVFormatter {

	/**
	 * @param array $elected
	 * @param array $eliminated
	 * @return string
	 */
	public function formatPreamble( array $elected, array $eliminated );

	public function formatRoundsPreamble();

	public function formatRound();

}
