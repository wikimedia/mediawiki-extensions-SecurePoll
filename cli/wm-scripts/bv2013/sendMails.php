<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../../..';
}
require_once "$IP/maintenance/commandLine.inc";

ini_set( 'display_errors', 1 );
$err = fopen( 'php://stderr', 'w' );
$in = fopen( 'php://stdin', 'r' );

$sender = new MailAddress(
	'board-elections@lists.wikimedia.org', 'Wikimedia Foundation Election Committee'
);

// Pull templates
// TODO: Get a list of all language codes from MediaWiki
$langs = [
	'ast', 'bn', 'ca', 'da', 'de', 'eml', 'en', 'es', 'fa', 'fi', 'fr', 'gl', 'he', 'hr', 'hu',
	'id', 'it', 'ja', 'ka', 'ms', 'mt', 'nb', 'nl', 'pl', 'ps', 'pt-br', 'ru', 'sa', 'sv', 'ta',
	'th', 'tr', 'uk', 'yi', 'zh'
];

$transTemplates = [];

foreach ( $langs as $lang ) {
	$file = "/a/common/elections-2013-spam/email-translations/$lang";
	if ( !file_exists( $file ) ) {
		continue;
	}
	$transTemplates[$lang] = file_get_contents( $file );
}

// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures
while ( ( $line = fgets( $in ) ) !== null ) {
	if ( !$line ) {
		continue;
	}
	list( $address, $lang, $site, $name ) = explode( "\t", trim( $line ) );

	if ( !( $name && $lang && $address && $site ) ) {
		print "invalid line $line $name $lang $address $site\n";
		continue;
	}

	if ( isset( $transTemplates[$lang] ) ) {
		$content = $transTemplates[$lang];
	} else {
		$content = $transTemplates['en'];
		$lang = 'en';
	}

	$content = strtr( $content,
		[
			'$USERNAME' => $name,
			'$ACTIVEPROJECT' => $wgLang->ucfirst( $site ),
		]
	);

	$address = new MailAddress( $address, $name );

	$subject = 'Wikimedia Foundation Elections 2013';

	UserMailer::send( $address, $sender, $subject, $content );
	print "Sent to $name <$address> in $lang\n";

	usleep( 100000 );
}
