<?php /* TASKS $Id$gantt.php,v 1.30 2004/08/06 22:56:54 gregorerhardt Exp $ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

global $company_id, $dept_ids, $department, $min_view, $m, $a, $user_id, $tab, $baseDir;

// reset the department and company filter info
// which is not used here
$company_id = 0;
$department = 0;

require(DP_BASE_DIR . '/modules/projects/viewgantt.php');
?>