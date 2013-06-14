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
		'is it ja lb mr ms nb nl pl pt ro ru si sk sq sv tr uk vi yi yue zh-hans zh-hant' );

$transTemplates = array();

foreach ( $langs as $lang ) {
	$transTemplates[$lang] = file_get_contents( 'email-translations/' . $lang );
}

while ( !is_null( $line = fgets( $in ) ) ) {
	if ( !$line ) {
		continue;
	}
	list( $address, $lang, $site, $name ) = explode( "\t", trim( $line ) );

	if ( !( $name && $lang && $address && $site ) ) {
		print "invalid line $line $name $lang $address $site\n";
		continue;
	}

	$content = $transTemplates[$lang];

	if ( !$content ) {
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

	$subject = 'Wikimedia Foundation Elections 2009';

	UserMailer::send( $address, $sender, $subject, $content );
	print "Sent to $name <$address> in $lang\n";

	sleep( 0.1 );
}
