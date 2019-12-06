<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( strval( $IP ) === '' ) {
	$IP = __DIR__ . '/../..';
}
if ( !file_exists( "$IP/includes/WebStart.php" ) ) {
	$IP .= '/core';
}
chdir( $IP );

require "$IP/includes/WebStart.php";

use MediaWiki\Extensions\SecurePoll\Context;
use MediaWiki\Extensions\SecurePoll\User\RemoteMWAuth;

/**
 * @param string $val To echo
 * @suppress SecurityCheck-XSS
 */
function out( $val ) {
	echo $val;
}

if ( !class_exists( RemoteMWAuth::class ) ) {
	header( 'HTTP/1.1 500 Internal Server Error' );
	echo "SecurePoll is disabled.\n";
	exit( 1 );
}

header( 'Content-Type: application/vnd.php.serialized; charset=utf-8' );

$token = $wgRequest->getVal( 'token' );
$id = $wgRequest->getInt( 'id' );
if ( $token === null || !$id ) {
	out( serialize( Status::newFatal( 'securepoll-api-invalid-params' ) ) );
	exit;
}

$user = User::newFromId( $id );
if ( !$user ) {
	out( serialize( Status::newFatal( 'securepoll-api-no-user' ) ) );
	exit;
}
$token2 = RemoteMWAuth::encodeToken( $user->getToken() );
if ( $token2 !== $token ) {
	out( serialize( Status::newFatal( 'securepoll-api-token-mismatch' ) ) );
	exit;
}
$context = new Context;
$auth = $context->newAuth( 'local' );
$status = Status::newGood( $auth->getUserParams( $user ) );
out( serialize( $status ) );
