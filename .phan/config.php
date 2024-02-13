<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	class_exists( OpenSSLAsymmetricKey::class ) ? [] : [ '.phan/stubs/OpenSSLAsymmetricKey.php' ],
	[
		'SecurePoll.constants.php'
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'cli',
		'../../extensions/CentralAuth',
		'../../extensions/Flow',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/CentralAuth',
		'../../extensions/Flow',
	]
);

return $cfg;
