<?php
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

	$table_name = dPgetParam($_GET, 'table_name', 'companies');

	switch($table_name){
		case 'companies':
			$id_field          = 'company_id';
			$name_field        = 'company_name';
			$selection_string  = 'Company';
			$filter            = null;
			$additional_get_information = '';
			break;
		case 'departments':
			$id_field          = 'dept_id';
			$name_field        = 'dept_name';
			$selection_string  = 'Department';
			$filter            = 'dept_company = '.$_GET['company_id'];
			$additional_get_information = '&amp;company_id='.$_GET['company_id'];
			break;
	}
	
	$q  = new DBQuery;
	$q->addTable($table_name);
	$q->addQuery("$id_field, $name_field");
	if ($filter != null) { $q->addWhere($filter); }
	$q->addOrder($name_field);
	$company_list = array('0' => '') + $q->loadHashList();

?>

<?php
	if(dPgetParam($_POST, $id_field, 0) != 0){
		$q  = new DBQuery;
		$q->addTable($table_name);
		$q->addQuery('*');
		$q->addWhere("$id_field=".$_POST[$id_field]);
		$sql = $q->prepare();
		$q->clear();
		db_loadHash($sql, $r_data);
		$data_update_script = "";
		$update_address     = isset($_POST["overwrite_address"]);
			
		if($table_name == 'companies'){
			$update_fields = array();
			if($update_address){
				$update_fields = array('company_address1' => 'contact_address1',
				                       'company_address2' => 'contact_address2',
				                       'company_city'     => 'contact_city',
				                       'company_state'    => 'contact_state',
				                       'company_zip'      => 'contact_zip',
				                       'company_phone1'   => 'contact_phone',
				                       'company_phone2'   => 'contact_phone2',
				                       'company_fax'      => 'contact_fax');
			}
			$data_update_script = "opener.setCompany('".$_POST[$id_field]."', '" . db_escape($r_data[$name_field]) . "');\n";
		} else if($table_name == 'departments'){
			$update_fields = array('dept_id'     => 'contact_department');
			if($update_address){
				$update_fields = array('dept_address1' => 'contact_address1',
				                       'dept_address2' => 'contact_address2',
				                       'dept_city'     => 'contact_city',
				                       'dept_state'    => 'contact_state',
				                       'dept_zip'      => 'contact_zip',
				                       'dept_phone'    => 'contact_phone',
				                       'dept_fax'      => 'contact_fax');
			}
			$data_update_script = "opener.setDepartment('" . $_POST[$id_field] . "', '" . db_escape($r_data[$name_field]) . "');\n";
		}
	
		// Let's figure out which fields are going to be updated
		foreach ($update_fields as $record_field => $contact_field){
			$data_update_script .= "opener.document.changecontact.$contact_field.value = '".$r_data[$record_field]."';\n";
		}
		?>
			<script language="javascript" type="text/javascript">
			<!--
				<?php echo $data_update_script; ?>
				self.close();
			-->
			</script>
		<?php
	} else {
		
		$tpl->assign('additional_get_information', $additional_get_information);
		$tpl->assign('company_list', $company_list);
		$tpl->assign('company_id', $company_id);
		$tpl->assign('id_field', $id_field);
		$tpl->assign('selection_string', $selection_string);
		$tpl->assign('table_name', $table_name);
		$tpl->displayFile('_select_contact_company', 'contacts');
	}
?>