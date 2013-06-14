<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../../../..';
}
require_once( "$IP/maintenance/commandLine.inc" );

ini_set( 'display_errors', 1 );
$err = fopen( 'php://stderr', 'w' );
$in = fopen( 'php://stdin', 'r' );

$sender = new MailAddress( 'board-elections@lists.wikimedia.org', 'Wikimedia Board Elections Committee' );

// Pull templates
$langs = explode( ' ', 'bar be-tarask bg bn bs ca cy da de diq el en eo es fa fi fr gl he hi hy id ' .
	'is it ja lb mr ms nb nl pl pt ro ru si sk sq sv tr uk vi yi yue zh-hans zh-hant'
);

$transTemplates = array();

foreach ( $langs as $lang ) {
	$file = "/a/common/elections-2013-spam/email-translations/$lang";
	if ( !file_exists( $file ) ) {
		continue;
	}
	$transTemplates[$lang] = file_get_contents( $file );
}

while ( !is_null( $line = fgets( $in ) ) ) {
	if ( !$line ) {
		continue;
	}
	list( $site, $name, $address, $lang ) = explode( "\t", trim( $line ) );

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
		array(
			'$username' => $name,
			'$activeproject' => $wgLang->ucfirst( $site ),
		)
	);

	$address = new MailAddress( $address, $name );

	$subject = 'Wikimedia Foundation Elections 2013';

	UserMailer::send( $address, $sender, $subject, $content );
	print "Sent to $name <$address> in $lang\n";

	sleep( 0.1 );
}
