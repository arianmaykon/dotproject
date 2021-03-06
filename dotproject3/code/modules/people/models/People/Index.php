<?php
class People_Index extends DP_List_Dynamic {
	
	public function __construct() {
		parent::__construct();	
		
		$db = DP_Config::getDB();
		$select = $db->select();
		
		$select->from('people');
		$this->query = $select;
		
		$cq = $db->select();
		$cq->from('people',Array('COUNT(*)'));
		$this->cq = $cq;
	}
}
?>