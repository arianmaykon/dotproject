<?php /* PROJECTS $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

global $AppUI, $company_id, $deny, $canRead, $canEdit;
global $allowed_folders_ary, $denied_folders_ary;

require_once( $AppUI->getModuleClass( 'files' ) );
   
$cfObj = new CFileFolder();

$allowed_folders_ary = $cfObj->getAllowedRecords($AppUI->user_id);
$denied_folders_ary  = $cfObj->getDeniedRecords($AppUI->user_id);

if ( count( $allowed_folders_ary ) < $cfObj->countFolders() ) {
	$limited = true;
}

$canEdit = !$limited or array_key_exists($folder, $allowed_folders_ary);

$showProject = false;
require( DP_BASE_DIR . '/modules/files/folders_table.php' );
?>