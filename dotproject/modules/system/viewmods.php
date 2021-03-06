<?php /* SYSTEM $Id$*/
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

$AppUI->savePlace();

$canEdit = !getDenyEdit( $m );
$canRead = !getDenyRead( $m );
if (!$canRead)
	$AppUI->redirect('m=public&a=access_denied');

// Keep a list of modules that are never installed or removed
$hidden_modules = array(
	'public',
	'install'
);

// Keep this global - used by smarty's dpUnsetArrayValue
global $modFiles;
// get the modules actually installed on the file system
$modFiles = $AppUI->readDirs('modules');

$q = new DBQuery;
$q->addQuery('*');
$q->addTable('modules');
foreach ($hidden_modules as $no_show) {
	$q->addWhere('mod_directory != \'' . $no_show . '\'');
	if (isset($modFiles[$no_show])) {
		unset($modFiles[$no_show]);
	}
}
$q->addOrder('mod_ui_order');
$modules = $q->loadList();

$titleBlock = new CTitleBlock('Modules', 'power-management.png', $m, "$m.$a");
$titleBlock->addCrumb('?m=system', 'System Admin');
$titleBlock->show();

$tpl->assign('canEdit', $canEdit);
$tpl->assign('dPconfig', $dPconfig);
$tpl->assign('m', $m);
$tpl->assign('modFiles', $modFiles);
$tpl->assign('modules', $modules);

$tpl->displayFile('viewmods', 'system');
?>
