<?php /* DEPARTMENTS $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

$dept_id = isset($_GET['dept_id']) ? $_GET['dept_id'] : 0;

// check permissions
$canRead = !getDenyRead( $m, $dept_id );
$canEdit = !getDenyEdit( $m, $dept_id );

if (!$canRead) {
	$AppUI->redirect('m=public&a=access_denied');
}
$AppUI->savePlace();

if (isset( $_GET['tab'] )) {
	$AppUI->setState( 'DeptVwTab', $_GET['tab'] );
}
$tab = $AppUI->getState( 'DeptVwTab' ) !== NULL ? $AppUI->getState( 'DeptVwTab' ) : 0;

// pull data
$q  = new DBQuery;
$q->addTable('companies', 'com');
$q->addTable('departments', 'dep');
$q->addQuery('dep.*, company_name');
$q->addQuery('con.contact_first_name');
$q->addQuery('con.contact_last_name');
$q->addJoin('users', 'u', 'u.user_id = dep.dept_owner');
$q->addJoin('contacts', 'con', 'u.user_contact = con.contact_id');
$q->addWhere('dep.dept_id = '.$dept_id);
$q->addWhere('dep.dept_company = company_id');
$sql = $q->prepare();
$q->clear();

if (!db_loadHash($sql, $dept)) {
	$titleBlock = new CTitleBlock( 'Invalid Department ID', 'users.gif', $m, "$m.$a" );
	$titleBlock->addCrumb( "?m=companies", "companies list" );
	$titleBlock->show();
} else {
	$company_id = $dept['dept_company'];

	// setup the title block
	$titleBlock = new CTitleBlock( 'View Department', 'users.gif', $m, "$m.$a" );
	if ($canEdit) {
		$titleBlock->addCell();
		$titleBlock->addCell(
			'<input type="submit" class="button" value="'.$AppUI->_('new department').'">', '',
			'<form action="?m=departments&a=addedit&company_id='.$company_id.'&dept_parent='.$dept_id.'" method="post">', '</form>'
		);
	}
	$titleBlock->addCrumb( "?m=companies", "company list" );
	$titleBlock->addCrumb( "?m=companies&a=view&company_id=$company_id", "view this company" );
	if ($canEdit) {
		$titleBlock->addCrumb( "?m=departments&a=addedit&dept_id=$dept_id", "edit this department" );

		if ($canDelete) {
			$titleBlock->addCrumbDelete( 'delete department', $canDelete, $msg );
		}
	}
	$titleBlock->show();

	$tpl->assign('dept_id', $dept_id);
	$tpl->assign('dept', $dept);
	$tpl->displayFile('view');

	// tabbed information boxes
	$tabBox = new CTabBox( "?m=departments&a=view&dept_id=$dept_id", DP_BASE_DIR.'/modules/departments/', $tab );
	$tabBox->add("vw_contacts", "Contacts");
	$tabBox->show();
}
?>