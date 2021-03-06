<?php // $Id$
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

global $AppUI, $task_parent_options, $loadFromTab;
global $can_edit_time_information, $locale_char_set, $obj;
global $durnTypes, $task_project, $task_id, $tab;

//Time arrays for selects
$start = intval(dPgetConfig('cal_day_start'));
$end   = intval(dPgetConfig('cal_day_end'));
$inc   = intval(dPgetConfig('cal_day_increment'));
if ($start === null ) $start = 8;
if ($end   === null ) $end = 17;
if ($inc   === null)  $inc = 15;
$hours = array();
for ( $current = $start; $current < $end + 1; $current++ ) {
	if ( $current < 10 ) 
		$current_key = '0' . $current;
	else
		$current_key = $current;
	
	if (stristr($AppUI->getPref('TIMEFORMAT'), '%p')) //User time format in 12hr
		$hours[$current_key] = ( $current > 12 ? $current-12 : $current );
	else //User time format in 24hr
		$hours[$current_key] = $current;
}

$minutes = array();
$minutes['00'] = '00';
for ( $current = 0 + $inc; $current < 60; $current += $inc )
	$minutes[$current] = $current;

// format dates
$df = $AppUI->getPref('SHDATEFORMAT');

if (intval($obj->task_start_date))
  $start_date = new CDate($obj->task_start_date);
else if ($task_id != 0)
  $start_date = null;
else {
  $start_date = new CDate();
  $start_date->setHour($start);
  }
//$start_date = intval( $obj->task_start_date ) ? new CDate( $obj->task_start_date ) : new CDate();
$end_date = intval( $obj->task_end_date ) ? new CDate( $obj->task_end_date ) : null;

$task_duration = isset($obj->task_duration) ? $obj->task_duration : 1;
if ($dot = strpos($task_duration, '.') && $obj->task_duration_type == 1)
	$task_duration = floor($task_duration) . ':' . (60 * ($task_duration - floor($task_duration)));

// convert the numeric calendar_working_days config array value to a human readable output format
$cwd = explode(',', dPgetConfig('cal_working_days'));

$cwd_conv = array_map(array('CDate', 'getWeekdayAbbrname'), $cwd);
$cwd_hr = implode(', ', $cwd_conv);

if ($start_date)
{
	$start_time['hour'] 	= $start_date->format('%H');
	$start_time['minute'] = $start_date->format('%M');
	$start_time['ampm'] 	= $start_date->format('%p');
}
else
{
	$start_time['hour'] 	= $start;
	$start_time['minute'] = '00';
	$start_time['ampm'] 	= ($start > 11 ? 'pm' : 'am');
}

if ($end_date)
{
	$end_time['hour'] 	= $end_date->format('%H');
	$end_time['minute'] = $end_date->format('%M');
	$end_time['ampm'] 	= $end_date->format('%p');
}
else
{
	$end_time['hour'] 	= $end;
	$end_time['minute'] = '00';
	$end_time['ampm'] 	= ($end > 11 ? 'pm' : 'am');
}

global $tpl;

$tpl->assign('can_edit_time_information', $can_edit_time_information);
$tpl->assign('task_id', $task_id);
$tpl->assign('end_time', $end_time);
$tpl->assign('start_time', $start_time); 
$tpl->assign('working_days', $cwd_hr);
$tpl->assign('hours', $hours);
$tpl->assign('minutes', $minutes);
$tpl->assign('durnTypes', $durnTypes);
$tpl->assign('tab', $tab); 
$tpl->assign('ampm_time_format', stristr($AppUI->getPref('TIMEFORMAT'), '%p'));

$tpl->assign('obj', $obj); 

$tpl->displayFile('ae_dates');
?>
