<?php

namespace MediaWiki\Extension\SecurePoll\Talliers\STVFormatter;

interface STVFormatter {

	public function formatPreamble( array $elected, array $eliminated );

	public function formatRoundsPreamble();

	public function formatRound();

}
