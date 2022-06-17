<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// These are too spammy for now. TODO enable
$cfg['scalar_implicit_cast'] = true;

$cfg['file_list'][] = 'auth-api.php';
$cfg['file_list'][] = 'SecurePoll.constants.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'cli',
		'../../extensions/CentralAuth',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/CentralAuth',
	]
);

return $cfg;
