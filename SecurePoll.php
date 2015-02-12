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
	'author' => array( 'Tim Starling', '...' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:SecurePoll',
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

/**
 * Relative URL path to auth-api.php
 */
$wgSecurePollScript = 'extensions/SecurePoll/auth-api.php';

/**
 * Time (in days) to keep IP addresses, XFF, UA of voters
 */
$wgSecurePollKeepPrivateInfoDays = 90;

/**
 * Directory holding dblist files for $wgSecurePollCreateWikiGroups.
 * The file format is one dbname per line.
 */
$wgSecurePollCreateWikiGroupDir = $IP . '/../';

/**
 * List of dblist files to read from $wgSecurePollCreateWikiGroupDir.
 * Keys are file names without the ".dblist" extension. Values are message
 * names.
 */
$wgSecurePollCreateWikiGroups = array();

/**
 * Value for remote-mw-script-path when creating a multi-wiki poll.
 */
$wgSecurePollCreateRemoteScriptPath = 'https:$wgServer/w';

/**
 * Whether to register and log to the SecurePoll namespace
 */
$wgSecurePollUseNamespace = false;

/**
 * If set, SecurePoll_GpgCrypt will use this instead of prompting the user for
 * a signing key.
 */
$wgSecurePollGpgSignKey = null;

### END CONFIGURATON ###


// Set up the new special page
$dir = __DIR__;
$wgMessagesDirs['SecurePoll'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SecurePoll'] = "$dir/SecurePoll.i18n.php";
$wgExtensionMessagesFiles['SecurePollAlias'] = "$dir/SecurePoll.alias.php";
$wgExtensionMessagesFiles['SecurePollNamespaces'] = $dir . '/SecurePoll.namespaces.php';

$wgSpecialPages['SecurePoll'] = 'SecurePoll_BasePage';

$wgAutoloadClasses = $wgAutoloadClasses + array(
	# ballots
	'SecurePoll_ApprovalBallot' => "$dir/includes/ballots/ApprovalBallot.php",
	'SecurePoll_Ballot' => "$dir/includes/ballots/Ballot.php",
	'SecurePoll_BallotStatus' => "$dir/includes/ballots/Ballot.php",
	'SecurePoll_ChooseBallot' => "$dir/includes/ballots/ChooseBallot.php",
	'SecurePoll_PreferentialBallot' => "$dir/includes/ballots/PreferentialBallot.php",
	'SecurePoll_RadioRangeBallot' => "$dir/includes/ballots/RadioRangeBallot.php",
	'SecurePoll_RadioRangeCommentBallot' => "$dir/includes/ballots/RadioRangeCommentBallot.php",

	# crypt
	'SecurePoll_Crypt' => "$dir/includes/crypt/Crypt.php",
	'SecurePoll_GpgCrypt' => "$dir/includes/crypt/Crypt.php",
	'SecurePoll_Random' => "$dir/includes/crypt/Random.php",

	# entities
	'SecurePoll_Election' => "$dir/includes/entities/Election.php",
	'SecurePoll_Entity' => "$dir/includes/entities/Entity.php",
	'SecurePoll_Option' => "$dir/includes/entities/Option.php",
	'SecurePoll_Question' => "$dir/includes/entities/Question.php",

	# main
	'SecurePoll_BasePage' => "$dir/includes/main/Base.php",
	'SecurePoll_Context' => "$dir/includes/main/Context.php",
	'SecurePoll_DBStore' => "$dir/includes/main/Store.php",
	'SecurePoll_MemoryStore' => "$dir/includes/main/Store.php",
	'SecurePoll_Store' => "$dir/includes/main/Store.php",
	'SecurePoll_XMLStore' => "$dir/includes/main/Store.php",

	# pages
	'SecurePoll_CreatePage' => "$dir/includes/pages/CreatePage.php",
	'SecurePoll_FormStore' => "$dir/includes/pages/CreatePage.php",
	'SecurePoll_StatusException' => "$dir/includes/pages/CreatePage.php",
	'SecurePoll_DetailsPage' => "$dir/includes/pages/DetailsPage.php",
	'SecurePoll_StrikePager' => "$dir/includes/pages/DetailsPage.php",
	'SecurePoll_DumpPage' => "$dir/includes/pages/DumpPage.php",
	'SecurePoll_EntryPage' => "$dir/includes/pages/EntryPage.php",
	'SecurePoll_ElectionPager' => "$dir/includes/pages/EntryPage.php",
	'SecurePoll_ListPage' => "$dir/includes/pages/ListPage.php",
	'SecurePoll_ListPager' => "$dir/includes/pages/ListPage.php",
	'SecurePoll_LoginPage' => "$dir/includes/pages/LoginPage.php",
	'SecurePoll_MessageDumpPage' => "$dir/includes/pages/MessageDumpPage.php",
	'SecurePoll_Page' => "$dir/includes/pages/Page.php",
	'SecurePoll_TallyPage' => "$dir/includes/pages/TallyPage.php",
	'SecurePoll_TranslatePage' => "$dir/includes/pages/TranslatePage.php",
	'SecurePoll_VotePage' => "$dir/includes/pages/VotePage.php",
	'SecurePoll_Voter' => "$dir/includes/user/Voter.php",
	'SecurePoll_VoterEligibilityPage' => "$dir/includes/pages/VoterEligibilityPage.php",

	# talliers
	'SecurePoll_ElectionTallier' => "$dir/includes/talliers/ElectionTallier.php",
	'SecurePoll_HistogramRangeTallier' => "$dir/includes/talliers/HistogramRangeTallier.php",
	'SecurePoll_PairwiseTallier' => "$dir/includes/talliers/PairwiseTallier.php",
	'SecurePoll_PluralityTallier' => "$dir/includes/talliers/PluralityTallier.php",
	'SecurePoll_SchulzeTallier' => "$dir/includes/talliers/SchulzeTallier.php",
	'SecurePoll_AlternativeVoteTallier' => "$dir/includes/talliers/AlternativeVoteTallier.php",
	'SecurePoll_Tallier' => "$dir/includes/talliers/Tallier.php",
	'SecurePoll_CommentDumper' => "$dir/includes/talliers/CommentDumper.php",

	# user
	'SecurePoll_Auth' => "$dir/includes/user/Auth.php",
	'SecurePoll_LocalAuth' => "$dir/includes/user/Auth.php",
	'SecurePoll_RemoteMWAuth' => "$dir/includes/user/Auth.php",

	# Jobs
	'SecurePoll_PopulateVoterListJob' => "$dir/includes/jobs/PopulateVoterListJob.php",

	# ContentHandler
	'SecurePollContentHandler' => $dir.'/includes/main/SecurePollContentHandler.php',
	'SecurePollContent' => $dir.'/includes/main/SecurePollContent.php',

	# HTMLForm additions
	'SecurePoll_HTMLDateField' => "$dir/includes/htmlform/HTMLDateField.php",
	'SecurePoll_HTMLDateRangeField' => "$dir/includes/htmlform/HTMLDateRangeField.php",
	'SecurePoll_HTMLFormRadioRangeColumnLabels' => "$dir/includes/htmlform/HTMLFormRadioRangeColumnLabels.php",
);

$wgResourceModules['ext.securepoll.htmlform'] = array(
	'localBasePath' => dirname( __FILE__ ) . '/modules',
	'remoteExtPath' => 'SecurePoll/modules',
	'scripts' => 'ext.securepoll.htmlform.js',
);
$wgResourceModules['ext.securepoll'] = array(
	'localBasePath' => dirname( __FILE__ ) . '/modules',
	'remoteExtPath' => 'SecurePoll/modules',
	'styles' => 'ext.securepoll.css',
);

$wgAjaxExportList[] = 'wfSecurePollStrike';
$wgHooks['UserLogout'][] = 'wfSecurePollLogout';

$wgJobClasses['securePollPopulateVoterList'] = 'SecurePoll_PopulateVoterListJob';

$wgContentHandlers['SecurePoll'] = 'SecurePollContentHandler';

$wgAvailableRights[] = 'securepoll-create-poll';

function wfSecurePollStrike( $action, $id, $reason ) {
	return SecurePoll_ListPage::ajaxStrike( $action, $id, $reason );
}
function wfSecurePollLogout( $user ) {
	$_SESSION['securepoll_voter'] = null;
	return true;
}

$wgHooks['LoadExtensionSchemaUpdates'][] = 'efSecurePollSchemaUpdates';

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efSecurePollSchemaUpdates( $updater ) {
	$base = dirname( __FILE__ );
	switch ( $updater->getDB()->getType() ) {
		case 'mysql':
			$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.sql" );
			$updater->modifyField( 'securepoll_votes', 'vote_ip',
				"$base/patches/patch-vote_ip-extend.sql", true );
			$updater->addExtensionIndex( 'securepoll_options', 'spop_election',
				"$base/patches/patch-op_election-index.sql"
			);
			break;
		case 'postgres':
			$updater->addExtensionTable( 'securepoll_entity', "$base/SecurePoll.pg.sql" );
			break;
	}
	return true;
}

define( 'NS_SECUREPOLL', 830 );
define( 'NS_SECUREPOLL_TALK', 831 );
$wgNamespacesWithSubpages[NS_SECUREPOLL] = true;
$wgNamespacesWithSubpages[NS_SECUREPOLL_TALK] = true;

$wgHooks['CanonicalNamespaces'][] = function ( &$namespaces ) {
	global $wgSecurePollUseNamespace;
	if ( $wgSecurePollUseNamespace ) {
		$namespaces[NS_SECUREPOLL] = 'SecurePoll';
		$namespaces[NS_SECUREPOLL_TALK] = 'SecurePoll_talk';
	}
};

$wgHooks['TitleQuickPermissions'][] = function ( $title, $user, $action, &$errors, $doExpensiveQueries, $short ) {
	global $wgSecurePollUseNamespace;
	if ( $wgSecurePollUseNamespace && $title->getNamespace() === NS_SECUREPOLL &&
		$action !== 'read'
	) {
		$errors[] = array( 'securepoll-ns-readonly' );
		return false;
	}

	return true;
};

$wgHooks['ContentHandlerDefaultModelFor'][] = function ( $title, &$model ) {
	global $wgSecurePollUseNamespace;
	if( $wgSecurePollUseNamespace && $title->getNamespace() == NS_SECUREPOLL ) {
		$model = 'SecurePoll';
		return false;
	}
	return true;
};
