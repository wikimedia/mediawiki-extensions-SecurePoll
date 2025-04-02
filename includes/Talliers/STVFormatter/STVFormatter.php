<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

interface STVFormatter {

	/**
	 * @param array $elected
	 * @param array $eliminated
	 * @return string
	 */
	public function formatPreamble( array $elected, array $eliminated );

	/** @return string|\OOUI\PanelLayout */
	public function formatRoundsPreamble();

	/** @return string|\OOUI\Tag */
	public function formatRound();

}
