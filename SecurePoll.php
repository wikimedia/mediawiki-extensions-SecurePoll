<?php
/**
 * Wikimedia Foundation Board of Trustees Election
 *
 * @file
 * @ingroup Extensions
 * @author Tim Starling <tstarling@wikimedia.org>
 * @author Kwan Ting Chan
 * @link http://www.mediawiki.org/wiki/Extension:SecurePoll Documentation
 */

# Not a valid entry point, skip unless MEDIAWIKI is defined
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "Not a valid entry point\n" );
}

# Extension credits
$wgExtensionCredits['other'][] = array(
	'name' => 'SecurePoll',
	'author' => array( 'Tim Starling', 'Kwan Ting Chan', 'others' ),
	'url' => 'http://www.mediawiki.org/wiki/Extension:SecurePoll',
	'svn-date' => '$LastChangedDate$',
	'svn-revision' => '$LastChangedRevision$',
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



# Vote admins
$wgAvailableRights[] = 'securepoll';
$wgGroupPermissions['securepoll']['securepoll'] = true;

// Set up the new special page
$dir = dirname( __FILE__ );
$wgExtensionMessagesFiles['SecurePoll'] = "$dir/SecurePoll.i18n.php";
$wgExtensionAliasesFiles['SecurePoll'] = "$dir/SecurePoll.alias.php";

$wgAutoloadClasses['SecurePollPage'] = "$dir/SecurePoll_body.php";
$wgSpecialPages['SecurePoll'] = 'SecurePollPage';
$wgExtensionFunctions[] = 'wfSetupSecurePoll';

$wgAutoloadClasses = $wgAutoloadClasses + array(
	'SecurePoll_Auth' => "$dir/includes/Auth.php",
	'SecurePoll_LocalAuth' => "$dir/includes/Auth.php",
	'SecurePoll_RemoteMWAuth' => "$dir/includes/Auth.php",
	'SecurePoll_Ballot' => "$dir/includes/Ballot.php",
	'SecurePoll_ChooseBallot' => "$dir/includes/Ballot.php",
	'SecurePoll_PreferentialBallot' => "$dir/includes/Ballot.php",
	'SecurePoll_Crypt' => "$dir/includes/Crypt.php",
	'SecurePoll_GpgCrypt' => "$dir/includes/Crypt.php",
	'SecurePoll_DetailsPage' => "$dir/includes/DetailsPage.php",
	'SecurePoll_DumpPage' => "$dir/includes/DumpPage.php",
	'SecurePoll_Election' => "$dir/includes/Election.php",
	'SecurePoll_Entity' => "$dir/includes/Entity.php",
	'SecurePoll_EntryPage' => "$dir/includes/EntryPage.php",
	'SecurePoll_ListPage' => "$dir/includes/ListPage.php",
	'SecurePoll_Option' => "$dir/includes/Option.php",
	'SecurePoll_Page' => "$dir/includes/Page.php",
	'SecurePoll_Question' => "$dir/includes/Question.php",
	'SecurePoll_TranslatePage' => "$dir/includes/TranslatePage.php",
	'SecurePoll_User' => "$dir/includes/User.php",
	'SecurePoll_VotePage' => "$dir/includes/VotePage.php",
);

$wgAjaxExportList[] = 'wfSecurePollStrike';

function wfSetupSecurePoll() {
	wfSetupSession();
	if ( isset( $_SESSION['bvLang'] ) && !isset( $_REQUEST['uselang'] ) ) {
		wfDebug( __METHOD__ . ": Setting user language to {$_SESSION['bvLang']}\n" );
		$_REQUEST['uselang'] = $_SESSION['bvLang'];
		global $wgLang;
		$wgLang = Language::factory( $_SESSION['bvLang'] );
	}
}

function wfSecurePollStrike( $action, $id, $reason ) {
	return SecurePoll_ListPage::ajaxStrike( $action, $id, $reason );
}

