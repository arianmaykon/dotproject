<?php /* CONTACTS $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

$obj = new CContact();
$msg = '';

if (!$obj->bind( $_POST )) {
	$AppUI->setMsg( $obj->getError(), UI_MSG_ERROR );
	$AppUI->redirect();
}

$del = dPgetParam( $_POST, 'del', 0 );

require_once(DP_BASE_DIR . '/classes/CustomFields.class.php');

// prepare (and translate) the module name ready for the suffix
$AppUI->setMsg( 'Contact' );
if ($del) {
	if (($msg = $obj->delete())) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
		$AppUI->redirect();
	} else {
		$AppUI->setMsg( "deleted", UI_MSG_ALERT, true );
		$AppUI->redirect( "m=contacts" );
	}
} else {
	$isNotNew = @$_POST['contact_id'];

	if (($msg = $obj->store())) {
		$AppUI->setMsg( $msg, UI_MSG_ERROR );
	} else {
                $custom_fields = New CustomFields( $m, 'addedit', $obj->contact_id, "edit" );
                $custom_fields->bind( $_POST );
                $sql = $custom_fields->store( $obj->contact_id ); // Store Custom Fields
		$AppUI->setMsg( $isNotNew ? 'updated' : 'added', UI_MSG_OK, true );
	}
	$AppUI->redirect();
}
?>