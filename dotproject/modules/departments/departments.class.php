<?php /* DEPARTMENTS $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

/** 
 * CDepartment Class
 */
class CDepartment extends CDpObject {
	var $dept_id = NULL;
	var $dept_parent = NULL;
	var $dept_company = NULL;
	var $dept_name = NULL;
	var $dept_phone = NULL;
	var $dept_fax = NULL;
	var $dept_address1 = NULL;
	var $dept_address2 = NULL;
	var $dept_city = NULL;
	var $dept_state = NULL;
	var $dept_zip = NULL;
	var $dept_url = NULL;
	var $dept_desc = NULL;
	var $dept_owner = NULL;

	function CDepartment() {
		$this->CDpObject( 'departments', 'dept_id' );
		$this->search_fields = array ("dept_name","dept_address1","dept_address2","dept_city","dept_state","dept_zip","dept_url","dept_desc");
	}

	function load( $oid ) {
		$q  = new DBQuery;
		$q->addTable('departments','dep');
		$q->addQuery('dep.*');
		$q->addWhere('dep.dept_id = '.$oid);
		$sql = $q->prepare();
		$q->clear();
		return db_loadObject( $sql, $this );
	}

	function bind( $hash ) {
		if (!is_array( $hash )) {
			return get_class( $this )."::bind failed";
		} else {
			bindHashToObject( $hash, $this );
			return NULL;
		}
	}

	function check() {
		if ($this->dept_id === NULL) {
			return 'department id is NULL';
		}
		// TODO MORE
		if ($this->dept_id && $this->dept_id == $this->dept_parent) {
		 	return "cannot make myself my own parent (" . $this->dept_id . "=" . $this->dept_parent . ")";
		}
		return NULL; // object is ok
	}

	function store() {
		$msg = $this->check();
		if( $msg ) {
			return get_class( $this )."::store-check failed - $msg";
		}
		if( $this->dept_id ) {
			$ret = db_updateObject( 'departments', $this, 'dept_id', false );
		} else {
			$ret = db_insertObject( 'departments', $this, 'dept_id' );
		}
		if( !$ret ) {
			return get_class( $this )."::store failed <br />" . db_error();
		} else {
			return NULL;
		}
	}

	function delete() {
		$q  = new DBQuery;
		$q->addTable('departments','dep');
		$q->addQuery('dep.*');
		$q->addWhere('dep.dept_parent = '.$this->dept_id);
		$res = $q->exec();

		if (db_num_rows( $res )) {
			$q->clear();
			return "deptWithSub";
		}
		$q->clear();
		$q->addTable('projects','p');
		$q->addQuery('p.*');
		$q->addWhere('p.project_department = '.$this->dept_id);
		$res = $q->exec();

		if (db_num_rows( $res )) {
			$q->clear();
			return "deptWithProject";
		}
		$q->clear();
		$q->addQuery('*');
		$q->setDelete('departments');
		$q->addWhere('dept_id = '.$this->dept_id);
		if (!$q->exec()) {
			$result = db_error();
		} else {
			$result = NULL;
		}
		$q->clear();
		return $result;
	}
}
?>