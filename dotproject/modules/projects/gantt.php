<?php /* TASKS $Id$ */
include ("{$dPconfig['root_dir']}/lib/jpgraph/src/jpgraph.php");
include ("{$dPconfig['root_dir']}/lib/jpgraph/src/jpgraph_gantt.php");

global $company_id, $dept_ids, $department, $locale_char_set, $proFilter, $projectStatus, $showInactive, $showLabels;//, $showAllGantt;

// get the prefered date format
$df = $AppUI->getPref('SHDATEFORMAT');

$filter1 = array();
$projectStatus = dPgetSysVal( 'ProjectStatus' );
$projectStatus = arrayMerge( array( '-2' => $AppUI->_('All w/o in progress')), $projectStatus);
$proFilter = dPgetParam($_REQUEST, 'proFilter', '-1');

if ($proFilter == '-2'){
        $filter1[] = "project_status != 3 ";
} else if ($proFilter != '-1') {
        $filter1[] = "project_status = $proFilter ";
}
if ($company_id != 0) {
        $filter1[] = "project_company = $company_id ";
}
//$filter1 = ($proFilter == '-1') ? '' : " AND project_status = $proFilter ";
if ($showInactive != '1')
	$filter1[] = "project_active <> 0 ";
$pjobj =& new CProject;
$allowed_projects = $pjobj->getAllowedSQL($AppUI->user_id);
$where = array_merge($filter1, $allowed_projects);

$working_hours = $dPconfig['daily_working_hours'];

// pull valid projects and their percent complete information
// GJB: Note that we have to special case duration type 24 and this refers to the hours in a day, NOT 24 hours
$sql = "
SELECT DISTINCT project_id, project_color_identifier, project_name, project_start_date, project_end_date, max(t1.task_end_date) AS project_actual_end_date,
 SUM(task_duration * task_percent_complete * IF(task_duration_type = 24, ".$working_hours.", task_duration_type))/
		SUM(task_duration * IF(task_duration_type = 24, ".$working_hours.", task_duration_type)) AS project_percent_complete,
project_status, project_active
FROM projects
LEFT JOIN tasks t1 ON projects.project_id = t1.task_project
LEFT JOIN companies c1 ON projects.project_company = c1.company_id";
if (count($where))
	$sql .= " WHERE " . implode( " AND ", $where);
$sql .= "
GROUP BY project_id
ORDER BY project_name, task_end_date DESC
";
// echo "<pre>$psql</pre>";
$projects = db_loadList( $sql );

$width      = dPgetParam( $_GET, 'width', 600 );
$start_date = dPgetParam( $_GET, 'start_date', 0 );
$end_date   = dPgetParam( $_GET, 'end_date', 0 );

$showAllGantt = dPgetParam( $_REQUEST, 'showAllGantt', '0' );
//$showTaskGantt = dPgetParam( $_GET, 'showTaskGantt', '0' );

$graph = new GanttGraph($width);
$graph->ShowHeaders(GANTT_HYEAR | GANTT_HMONTH | GANTT_HDAY | GANTT_HWEEK);

$graph->SetFrame(false);
$graph->SetBox(true, array(0,0,0), 2);
$graph->scale->week->SetStyle(WEEKSTYLE_FIRSTDAY);

$jpLocale = dPgetConfig( 'jpLocale' );
if ($jpLocale) {
	$graph->scale->SetDateLocale( $jpLocale );
}

if ($start_date && $end_date) {
	$graph->SetDateRange( $start_date, $end_date );
}

//$graph->scale->actinfo->SetFont(FF_ARIAL);
$graph->scale->actinfo->vgrid->SetColor('gray');
$graph->scale->actinfo->SetColor('darkgray');
$graph->scale->actinfo->SetColTitles(array( $AppUI->_('Project name', UI_OUTPUT_RAW), $AppUI->_('Start Date', UI_OUTPUT_RAW), $AppUI->_('Finish', UI_OUTPUT_RAW), $AppUI->_('Actual End', UI_OUTPUT_RAW)),array(160,10, 70,70));


$tableTitle = ($proFilter == '-1') ? $AppUI->_('All Projects') : $projectStatus[$proFilter];
$graph->scale->tableTitle->Set($tableTitle);

// Use TTF font if it exists
// try commenting out the following two lines if gantt charts do not display
if (is_file( TTF_DIR."arialbd.ttf" ))
	$graph->scale->tableTitle->SetFont(FF_ARIAL,FS_BOLD,12);
$graph->scale->SetTableTitleBackground("#eeeeee");
$graph->scale->tableTitle->Show(true);

//-----------------------------------------
// nice Gantt image
// if diff(end_date,start_date) > 90 days it shows only
//week number
// if diff(end_date,start_date) > 240 days it shows only
//month number
//-----------------------------------------
if ($start_date && $end_date){
        $min_d_start = new CDate($start_date);
        $max_d_end = new CDate($end_date);
        $graph->SetDateRange( $start_date, $end_date );
} else {
        // find out DateRange from gant_arr
        $d_start = new CDate();
        $d_end = new CDate();
        for($i = 0; $i < count(@$projects); $i++ ){
                $start = substr($p["project_start_date"], 0, 10);
                $end = substr($p["project_end_date"], 0, 10);

                $d_start->Date($start);
                $d_end->Date($end);

                if ($i == 0){
                        $min_d_start = $d_start;
                        $max_d_end = $d_end;
                } else {
                        if (Date::compare($min_d_start,$d_start)>0){
                                $min_d_start = $d_start;
                        }
                        if (Date::compare($max_d_end,$d_end)<0){
                                $max_d_end = $d_end;
                        }
                }
        }
}

// check day_diff and modify Headers
$day_diff = $min_d_start->dateDiff($max_d_end);

if ($day_diff > 240){
        //more than 240 days
        $graph->ShowHeaders(GANTT_HYEAR | GANTT_HMONTH);
} else if ($day_diff > 90){
        //more than 90 days and less of 241
        $graph->ShowHeaders(GANTT_HYEAR | GANTT_HMONTH | GANTT_HWEEK );
        $graph->scale->week->SetStyle(WEEKSTYLE_WNBR);
}

$row = 0;

if (!is_array($projects) || sizeof($projects) == 0) {
 $d = new CDate();
 $bar = new GanttBar($row++, array(' '.$AppUI->_('No projects found'),  ' ', ' ', ' '), $d->getDate(), $d->getDate(), ' ', 0.6);
 $bar->title->SetCOlor('red');
 $graph->Add($bar);
}

if (is_array($projects)) {
foreach($projects as $p) {

	if ( $locale_char_set=='utf-8' && function_exists("utf_decode") ) {
		$name = strlen( utf8_decode($p["project_name"]) ) > 25 ? substr( utf8_decode($p["project_name"]), 0, 22 ).'...' : utf8_decode($p["project_name"]) ;
	} else {
		//while using charset different than UTF-8 we need not to use utf8_deocde
		$name = strlen( $p["project_name"] ) > 25 ? substr( $p["project_name"], 0, 22 ).'...' : $p["project_name"] ;
	}

	//using new jpGraph determines using Date object instead of string
	$start = ($p["project_start_date"] > "0000-00-00 00:00:00") ? $p["project_start_date"] : date("Y-m-d H:i:s");
	$end_date   = $p["project_end_date"];
        $actual_end = $p["project_actual_end_date"] ? $p["project_actual_end_date"] : " ";


	$end_date = new CDate($end_date);
//	$end->addDays(0);
	$end = $end_date->getDate();

	$start = new CDate($start);
//	$start->addDays(0);
	$start = $start->getDate();

	$progress = $p['project_percent_complete'];

	$caption = "";
	if(!$start || $start == "0000-00-00"){
		$start = !$end ? date("Y-m-d") : $end;
		$caption .= "(no start date)";
	}

	if(!$end) {
		$end = $start;
		$caption .= " (no end date)";
	} else {
		$cap = "";
	}

        if ($showLabels){
                $caption .= $projectStatus[$p['project_status']].", ";
                $caption .= $p['project_active'] <> 0 ? $AppUI->_('active') : $AppUI->_('inactive');
        }

	$enddate = new CDate($end);
	$startdate = new CDate($start);
	$actual_enddate = new CDate($actual_end);
        $bar = new GanttBar($row++, array($name, $startdate->format($df), $enddate->format($df), $actual_enddate->format($df)), $start, $actual_end, $cap, 0.6);
        $bar->progress->Set($progress/100);

        $bar->title->SetFont(FF_FONT1,FS_NORMAL,10);
        $bar->SetFillColor("#".$p['project_color_identifier']);
        $bar->SetPattern(BAND_SOLID,"#".$p['project_color_identifier']);

	//adding captions
	$bar->caption = new TextProperty($caption);
	$bar->caption->Align("left","center");

        // gray out templates, completes, on ice, on hold
        if ($p['project_status'] != '3' || $p['project_active'] == '0') {
                $bar->caption->SetColor('darkgray');
                $bar->title->SetColor('darkgray');
                $bar->SetColor('darkgray');
                $bar->SetFillColor('gray');
                //$bar->SetPattern(BAND_SOLID,'gray');
                $bar->progress->SetFillColor('darkgray');
                $bar->progress->SetPattern(BAND_SOLID,'darkgray',98);
        }

	$graph->Add($bar);

 	// If showAllGant checkbox is checked 
 	if (true)//$showAllGantt)
 	{
 		// insert tasks into Gantt Chart
 		
 		// select for tasks for each project	
 		$sqlTasks = "	SELECT DISTINCT tasks.task_id, tasks.task_name, tasks.task_start_date, tasks.task_end_date, tasks.task_milestone
               		FROM tasks
               		LEFT JOIN projects ON projects.project_id = tasks.task_project
               		LEFT JOIN companies c1 ON projects.project_company = c1.company_id
               		WHERE projects.project_id = {$p["project_id"]} 
               		ORDER BY tasks.task_end_date ASC";
 		// End of select for tasks for each project
 		
 		$tasks = db_loadList( $sqlTasks );
 		foreach($tasks as $t)
 		{
 			if ($t["task_end_date"] == null)
 				$t["task_end_date"] = $t["task_start_date"];
 				
 			if ($t["task_milestone"] != 1)
 			{/*		*/
 				$bar2 = new GanttBar($row++, array(substr(" --".$t["task_name"], 0, 20)."...", ' ', ' '/*substr($t["task_start_date"],  substr($t["task_end_date"], 2, 8)*/, ' '), ' ', ' ', ' '/*chr(32)*/, 0.6);
// 				$bar2 = new GanttBar($row++, array("   * ".$t["task_name"], $t["task_start_date"], $t["task_end_date"]," "), "0", "0;", 0.6);
				$bar2->title->SetColor("#".$p['project_color_identifier']);
 				$bar2->SetFillColor("#".$p['project_color_identifier']);		
 				$graph->Add($bar2);
 			}
 			else
 			{
 				$bar2  = new MileStone ($row++, "-- " . $t["task_name"], $t["task_start_date"], (substr($t["task_start_date"], 0, 10)));
 				$bar2->title->SetColor("#CC0000");
 				$graph->Add($bar2);
 			}				
 				
 				// Insert workers for each task into Gantt Chart 
 				$sqlWorkers = "
                 				SELECT DISTINCT  user_username, t.task_id
                 				FROM user_tasks t
                 				LEFT JOIN users ON users.user_id = t.user_id
                 				WHERE t.task_id = ";
                 				$sqlWorkers .=  $t["task_id"] . " 
                 				ORDER BY user_username ASC";
 				
 				$workers = db_loadList($sqlWorkers);
 				$workersName = "";
 				foreach($workers as $w)
 				{	
 					$workersName .= " ".$w["user_username"];
 				
 					$bar3 = new GanttBar($row++, array("   * ".$w["user_username"], " ", " "," "), "0", "0;", 0.6);							
 					$bar3->title->SetColor("#".$p['project_color_identifier']);
 					$bar3->SetFillColor("#".$p['project_color_identifier']);		
 					$graph->Add($bar3);
 				}
 				// End of insert workers for each task into Gantt Chart  				
 		}
 		// End of insert tasks into Gantt Chart 
 	}			
 	// End of if showAllGant checkbox is checked
}
} // End of check for valid projects array.

$today = date("y-m-d");
$vline = new GanttVLine($today, $AppUI->_('Today', UI_OUTPUT_RAW));
$graph->Add($vline);
$graph->Stroke();
?>
