<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

interface STVFormatter {

	/**
	 * @param array $elected
	 * @param array $eliminated
	 * @param array $modifiers
	 * @return string
	 */
	public function formatPreamble( array $elected, array $eliminated, array $modifiers );

	/** @return string */
	public function formatRoundsPreamble();

	/** @return string */
	public function formatRound();

	/** @return string */
	public function formatBlt();

}
