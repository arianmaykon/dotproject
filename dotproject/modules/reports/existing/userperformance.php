<?php
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

$do_report 		    = dPgetParam( $_POST, "do_report", 0 );
$log_start_date 	= dPgetParam( $_POST, "log_start_date", 0 );
$log_end_date 	    = dPgetParam( $_POST, "log_end_date", 0 );
$log_all		    = dPgetParam($_POST["log_all"], 0);
$group_by_unit      = dPgetParam($_POST["group_by_unit"],"day");

// create Date objects from the datetime fields
$start_date = intval( $log_start_date ) ? new CDate( $log_start_date ) : new CDate();
$end_date = intval( $log_end_date ) ? new CDate( $log_end_date ) : new CDate();

if (!$log_start_date) {
	$start_date->addDays(-14);
}
$end_date->setTime( 23, 59, 59 );
?>

<script type="text/javascript" language="javascript">
<!--
var calendarField = '';

function popCalendar( field ){
	calendarField = field;
	idate = eval( 'document.editFrm.log_' + field + '.value' );
	window.open( 'index.php?m=public&a=calendar&dialog=1&callback=setCalendar&date=' + idate, 'calwin', 'width=250, height=220, scrollbars=no' );
}

/**
 *	@param string Input date in the format YYYYMMDD
 *	@param string Formatted date
 */
function setCalendar( idate, fdate ) {
	fld_date = eval( 'document.editFrm.log_' + calendarField );
	fld_fdate = eval( 'document.editFrm.' + calendarField );
	fld_date.value = idate;
	fld_fdate.value = fdate;
}
-->
</script>

<form name="editFrm" action="index.php?m=reports" method="post">
	<input type="hidden" name="project_id" value="<?php echo $project_id;?>" />
	<input type="hidden" name="report_category" value="<?php echo $report_category;?>" />
	<input type="hidden" name="report_type" value="<?php echo $report_type;?>" />

<table cellspacing="0" cellpadding="4" border="0" width="100%" class="std">
<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('For period');?>:</td>
	<td nowrap="nowrap">
		<input type="hidden" name="log_start_date" value="<?php echo $start_date->format( FMT_TIMESTAMP_DATE );?>" />
		<input type="text" name="start_date" value="<?php echo $start_date->format( $df );?>" class="text" disabled="disabled" />
		<a href="#" onclick="popCalendar('start_date')">
			<img src="./images/calendar.gif" width="24" height="12" alt="<?php echo $AppUI->_('Calendar');?>" border="0" />
		</a>
	</td>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('to');?></td>
	<td nowrap="nowrap">
		<input type="hidden" name="log_end_date" value="<?php echo $end_date ? $end_date->format( FMT_TIMESTAMP_DATE ) : '';?>" />
		<input type="text" name="end_date" value="<?php echo $end_date ? $end_date->format( $df ) : '';?>" class="text" disabled="disabled" />
		<a href="#" onclick="popCalendar('end_date')">
			<img src="./images/calendar.gif" width="24" height="12" alt="<?php echo $AppUI->_('Calendar');?>" border="0" />
		</a>
	</td>
	
	<td nowrap='nowrap'>
		<input type="checkbox" name="log_all" <?php if ($log_all) echo "checked" ?> />
		<?php echo $AppUI->_( 'Log All' );?>
	</td>

	<td align="right" width="50%" nowrap="nowrap">
		<input class="button" type="submit" name="do_report" value="<?php echo $AppUI->_('submit');?>" />
	</td>
</tr>
</table>
</form>

<?php
if($do_report){

	$q  = new DBQuery;
	$q->addTable('users', 'u');
	$q->addJoin('contacts', 'con', 'user_contact = contact_id');
	$q->addQuery('user_id, user_username, contact_first_name, contact_last_name');
	$q->addOrder('contact_last_name, contact_first_name');
	
	$user_list = $q->loadHashList('user_id');
	$q->clear();
	
	// Now which tasks will we need and the real allocated hours (estimated time / number of users)
	// Also we will use tasks with duration_type = 1 (hours) and those that are not marked
	// as milstones
	// GJB: Note that we have to special case duration type 24 and this refers to the hours in a day, NOT 24 hours
	$working_hours = dPgetConfig('daily_working_hours');

	$q  = new DBQuery;
	$q->addTable('tasks', 't');
	$q->addTable('user_tasks', 'ut');
	$q->addQuery('t.task_id, round(t.task_duration * IF(t.task_duration_type = 24, '.$working_hours.', t.task_duration_type)/count(ut.task_id),2) as hours_allocated');
	$q->addWhere('t.task_id = ut.task_id');
	$q->addWhere("t.task_milestone    ='0'");
	$q->addGroup('t.task_id');
	
	if($project_id != 0)
		$q->addWhere("t.task_project='$project_id'");
	
	if(!$log_all){
		$q->addWhere('t.task_start_date >= \''.$start_date->format( FMT_DATETIME_MYSQL ) . "'");
		$q->addWhere('t.task_start_date <= \''.$end_date->format( FMT_DATETIME_MYSQL ) . "'");
	}
		
	$task_list = $q->loadHashList('task_id');
	$q->clear();

?>

<table cellspacing="1" cellpadding="4" border="0" class="tbl">
	<tr>
		<th colspan='2'><?php echo $AppUI->_('User');?></th>
		<th><?php echo $AppUI->_('Hours allocated'); ?></th>
		<th><?php echo $AppUI->_('Hours worked'); ?></th>
		<th><?php echo $AppUI->_('% of work done (based on duration)'); ?></th>
		<th><?php echo $AppUI->_('User Efficiency (based on completed tasks)'); ?></th>
	</tr>

<?php
	if(count($user_list)){
		$percentage_sum = $hours_allocated_sum = $hours_worked_sum = 0;
		$sum_total_hours_allocated = $sum_total_hours_worked = 0;
		$sum_hours_allocated_complete = $sum_hours_worked_complete = 0;
	
//TODO: Split times for which more than one users were working...	
		foreach($user_list as $user_id => $user){
		
			$q  = new DBQuery;
			$q->addTable('user_tasks', 'ut');
			$q->addQuery('task_id');
			$q->addWhere("user_id = $user_id");	
			$tasks_id = array_keys($q->loadHashList('task_id'));
			$q->clear();

			$total_hours_allocated = $total_hours_worked = 0;
			$hours_allocated_complete = $hours_worked_complete = 0;
			
			foreach($tasks_id as $task_id){
				if(isset($task_list[$task_id])){
					// Now let's figure out how many time did the user spent in this task
					$q  = new DBQuery;
					$q->addTable('task_log');
					$q->addQuery('sum(task_log_hours)');
					$q->addWhere("task_log_task = $task_id");
					$q->addWhere("task_log_creator = $user_id");	
					$hw = $q->loadList();
					$q->clear();
					$hours_worked = round($hw[0]['sum(task_log_hours)'],2);
					
					$q  = new DBQuery;
					$q->addTable('tasks');
					$q->addQuery('task_percent_complete');
					$q->addWhere("task_id = $task_id");
					$pt = $q->loadList();
					$q->clear();
					$percent = round($pt[0]['task_percent_complete'],2);
					
                                        $complete = ($percent[0] == 100);
                                        
                                        if ($complete)
                                        {
                                                $hours_allocated_complete += $task_list[$task_id]["hours_allocated"];
                                                $hours_worked_complete += $hours_worked;
                                        }

					$total_hours_allocated += $task_list[$task_id]["hours_allocated"];
					$total_hours_worked    += $hours_worked;
				}
			}
			
			$sum_total_hours_allocated += $total_hours_allocated;
			$sum_total_hours_worked    += $total_hours_worked;

			$sum_hours_allocated_complete += $hours_allocated_complete;
			$sum_hours_worked_complete    += $hours_worked_complete;
			
			if($total_hours_allocated > 0 || $total_hours_worked > 0){
				$percentage = 0;
				if($total_hours_allocated > 0){
					$percentage = ($total_hours_worked/$total_hours_allocated)*100;
				}
				else{
					$percentage = "N/A";
				}
				
				$percentage_e = 0;
				if($hours_worked_complete > 0){
					$percentage_e = ($hours_allocated_complete/$hours_worked_complete)*100;
				}
				else{
					$percentage_e = "N/A";
				}
				?>
				<tr>
					<td><?php echo "(".$user["user_username"].") </td><td> ".$user["contact_first_name"]." ".$user["contact_last_name"]; ?></td>
					<td align='right'><?php echo $total_hours_allocated; ?> </td>
					<td align='right'><?php echo $total_hours_worked; ?> </td>
					<?php $percentage = ($percentage == "N/A"?"N/A":number_format($percentage, 0).'%'); ?>
					<td align='right'><?php echo $percentage; ?>% </td>
					<?php $percentage_e = ($percentage_e == "N/A"?"N/A":number_format($percentage_e, 0).'%'); ?>
					<td align='right'><?php echo $percentage_e; ?>% </td>
				</tr>
				<?php
			}
		}
		$sum_percentage = 0;
		if($sum_total_hours_allocated > 0){
			$sum_percentage = ($sum_total_hours_worked/$sum_total_hours_allocated)*100;
		}
		else{
			$sum_percentage = "N/A";
		}
                $sum_efficiency = 0;
		if($sum_hours_worked_complete > 0){
			$sum_efficiency = ($sum_hours_allocated_complete/$sum_hours_worked_complete)*100;
		}
		else{
			$sum_efficiency = "N/A";
		}
		?>
			<tr>
				<td colspan='2'><?php echo $AppUI->_('Total'); ?></td>
				<td align='right'><?php echo $sum_total_hours_allocated; ?></td>
				<td align='right'><?php echo $sum_total_hours_worked; ?></td>
				<?php $sum_percentage = ($sum_percentage == "N/A"?"N/A":number_format($sum_percentage, 0).'%'); ?>
				<td align='right'><?php echo $sum_percentage; ?>%</td>
				<?php $sum_efficiency = ($sum_efficiency == "N/A"?"N/A":number_format($sum_efficiency, 0).'%'); ?>
				<td align='right'><?php echo $sum_efficiency; ?>%</td>
			</tr>
		<?php
	} else {
		?>
		<tr>
		    <td><p><?php echo $AppUI->_('There are no tasks that fulfill selected filters');?></p></td>
		</tr>
		<?php
	}
	echo '</table>';
}
?>