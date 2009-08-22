<?php
/**
 * @file
 * @ingroup Extensions
 * @author Tim Starling <tstarling@wikimedia.org>
 * @link http://www.mediawiki.org/wiki/Extension:SecurePoll Documentation
 */

# Not a valid entry point, skip unless MEDIAWIKI is defined
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "Not a valid entry point\n" );
}

# Extension credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'SecurePoll',
	'author' => array( 'Tim Starling', 'others' ),
	'url' => 'http://www.mediawiki.org/wiki/Extension:SecurePoll',
	'description' => 'Extension for secure elections and surveys',
	'descriptionmsg' => 'securepoll-desc',
);

# Configuration
/**
 * The GPG command to run
 */
$wgSecurePollGPGCommand = 'gpg';

/**
 * The temporary directory to be used for GPG home directories and plaintext files
 */
$wgSecurePollTempDir = '/tmp';

/**
 * Show detail of GPG errors
 */
$wgSecurePollShowErrorDetail = false;

### END CONFIGURATON ###


// Set up the new special page
$dir = dirname( __FILE__ );
$wgExtensionMessagesFiles['SecurePoll'] = "$dir/SecurePoll.i18n.php";
$wgExtensionAliasesFiles['SecurePoll'] = "$dir/SecurePoll.alias.php";

$wgSpecialPages['SecurePoll'] = 'SecurePoll_BasePage';

$wgAutoloadClasses = $wgAutoloadClasses + array(
	'SecurePoll_Auth' => "$dir/includes/Auth.php",
	'SecurePoll_LocalAuth' => "$dir/includes/Auth.php",
	'SecurePoll_RemoteMWAuth' => "$dir/includes/Auth.php",
	'SecurePoll_Ballot' => "$dir/includes/Ballot.php",
	'SecurePoll_BasePage' => "$dir/includes/Base.php",
	'SecurePoll_ChooseBallot' => "$dir/includes/Ballot.php",
	'SecurePoll_PreferentialBallot' => "$dir/includes/Ballot.php",
	'SecurePoll_Context' => "$dir/includes/Context.php",
	'SecurePoll_Crypt' => "$dir/includes/Crypt.php",
	'SecurePoll_GpgCrypt' => "$dir/includes/Crypt.php",
	'SecurePoll_DetailsPage' => "$dir/includes/DetailsPage.php",
	'SecurePoll_DumpPage' => "$dir/includes/DumpPage.php",
	'SecurePoll_Election' => "$dir/includes/Election.php",
	'SecurePoll_ElectionTallier' => "$dir/includes/ElectionTallier.php",
	'SecurePoll_Entity' => "$dir/includes/Entity.php",
	'SecurePoll_EntryPage' => "$dir/includes/EntryPage.php",
	'SecurePoll_ListPage' => "$dir/includes/ListPage.php",
	'SecurePoll_LoginPage' => "$dir/includes/LoginPage.php",
	'SecurePoll_MessageDumpPage' => "$dir/includes/MessageDumpPage.php",
	'SecurePoll_Option' => "$dir/includes/Option.php",
	'SecurePoll_Page' => "$dir/includes/Page.php",
	'SecurePoll_Question' => "$dir/includes/Question.php",
	'SecurePoll_Random' => "$dir/includes/Random.php",
	'SecurePoll_Store' => "$dir/includes/Store.php",
	'SecurePoll_DBStore' => "$dir/includes/Store.php",
	'SecurePoll_MemoryStore' => "$dir/includes/Store.php",
	'SecurePoll_XMLStore' => "$dir/includes/Store.php",
	'SecurePoll_Tallier' => "$dir/includes/Tallier.php",
	'SecurePoll_PluralityTallier' => "$dir/includes/Tallier.php",
	'SecurePoll_SchulzeTallier' => "$dir/includes/Tallier.php",
	'SecurePoll_TallyPage' => "$dir/includes/TallyPage.php",
	'SecurePoll_TranslatePage' => "$dir/includes/TranslatePage.php",
	'SecurePoll_Voter' => "$dir/includes/Voter.php",
	'SecurePoll_VotePage' => "$dir/includes/VotePage.php",
);

$wgAjaxExportList[] = 'wfSecurePollStrike';
$wgHooks['UserLogout'][] = 'wfSecurePollLogout';

function wfSecurePollStrike( $action, $id, $reason ) {
	return SecurePoll_ListPage::ajaxStrike( $action, $id, $reason );
}
function wfSecurePollLogout( $user ) {
	$_SESSION['securepoll_voter'] = null;
	return true;
}
