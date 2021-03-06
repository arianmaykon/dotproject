<?php /* TASKS $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

global $min_view, $m, $a, $user_id, $tab, $tasks;

$min_view = defVal( @$min_view, false);

$project_id = defVal( @$_GET['project_id'], 0);

// sdate and edate passed as unix time stamps
$sdate = dPgetParam( $_POST, 'sdate', 0 );
$edate = dPgetParam( $_POST, 'edate', 0 );
$showLabels = dPgetParam( $_POST, 'showLabels', '0' );
//if set GantChart includes user labels as captions of every GantBar
if ($showLabels!='0') {
    $showLabels='1';
}
$showWork = dPgetParam( $_POST, 'showWork', '0' );
if ($showWork!='0') {
    $showWork='1';
}
$showPinned = dPgetParam( $_POST, 'showPinned', '0' );
if ($showPinned!='0') {
    $showPinned='1';
}
$showArcProjs = dPgetParam( $_POST, 'showArcProjs', '0' );
if ($showArcProjs!='0') {
    $showArcProjs='1';
}
$showHoldProjs = dPgetParam( $_POST, 'showHoldProjs', '0' );
if ($showHoldProjs!='0') {
    $showHoldProjs='1';
}
$showDynTasks = dPgetParam( $_POST, 'showDynTasks', '0' );
if ($showDynTasks!='0') {
    $showDynTasks='1';
}
$showLowTasks = dPgetParam( $_POST, 'showLowTasks', '1' );
if ($showLowTasks!='0') {
    $showLowTasks='1';
}

// months to scroll
$scroll_date = 1;

$display_option = dPgetParam( $_POST, 'display_option', 'this_month' );

// format dates
$df = $AppUI->getPref('SHDATEFORMAT');

if ($display_option == 'custom') 
{
	// custom dates
	$start_date = intval( $sdate ) ? new CDate( $sdate ) : new CDate();
	$end_date = intval( $edate ) ? new CDate( $edate ) : new CDate();
} 
else if ($display_option == 'this_month') // month
{
	$start_date = new CDate();
	$start_date->day = 1;
	$end_date = new CDate($start_date);
	$end_date->addMonths( $scroll_date );
}
else if ($display_option == '3_months') // month
{
	$start_date = new CDate();
	$start_date->day = 1;
	$start_date->addMonths(-1);
	$end_date = new CDate($start_date);
	$end_date->addMonths(3);
}
else if ($display_option == 'first_month' || $display_option == 'all')
{
	//TODO: find project start date
	$q = new DBQuery;
	$q->addQuery('project_start_date');
	$q->addTable('projects');
	$q->addWhere('project_id = ' . $project_id);
	$project_start_date = $q->loadResult();
	$start_date = new CDate($project_start_date);
	$start_date->day = 1;
	$end_date = new CDate($start_date);
	$end_date->addMonths(1);
}

// setup the title block
if (!@$min_view) {
	$titleBlock = new CTitleBlock( 'Gantt Chart', 'applet-48.png', $m, $m.$a );
	$titleBlock->addCrumb( '?m=tasks', 'tasks list' );
	$titleBlock->addCrumb( '?m=projects&a=view&project_id='.$project_id, 'view this project' );
	$titleBlock->show();
}
?>
<script type="text/javascript" language="javascript">
<!--
var calendarField = '';

function popCalendar( field ){
	calendarField = field;
	idate = eval( 'document.editFrm.' + field + '.value' );
	window.open( 'index.php?m=public&a=calendar&dialog=1&callback=setCalendar&date=' + idate, 'calwin', 'width=250, height=220, scrollbars=no' );
}

/**
 *	@param string Input date in the format YYYYMMDD
 *	@param string Formatted date
 */
function setCalendar( idate, fdate ) {
	fld_date = eval( 'document.editFrm.' + calendarField );
	fld_fdate = eval( 'document.editFrm.show_' + calendarField );
	fld_date.value = idate;
	fld_fdate.value = fdate;
}

function scrollPrev() {
	f = document.editFrm;
<?php
	$new_start = $start_date;	
	$new_start->day = 1;
	$new_end = $end_date;
	$new_start->addMonths( -$scroll_date );
	$new_end->addMonths( -$scroll_date );
	echo 'f.sdate.value="'.$new_start->format( FMT_TIMESTAMP_DATE ).'";';
	echo 'f.edate.value="'.$new_end->format( FMT_TIMESTAMP_DATE ).'";';
?>
	document.editFrm.display_option.value = 'custom';
	f.submit()
}

function scrollNext() {
	f = document.editFrm;
<?php
	$new_start = $start_date;	
	$new_start->day = 1;
	$new_end = $end_date;
	$new_start->addMonths( $scroll_date );
	$new_end->addMonths( $scroll_date );	
	echo 'f.sdate.value="'.$new_start->format( FMT_TIMESTAMP_DATE ).'";';	
	echo 'f.edate.value="'.$new_end->format( FMT_TIMESTAMP_DATE ).'";';
?>
	document.editFrm.display_option.value = 'custom';
	f.submit()
}

function showGantt(type) {
	document.editFrm.display_option.value = type;
	document.editFrm.submit();
}
-->
</script>

<form name="editFrm" method="post" action="?<?php echo "m=$m&amp;a=$a&amp;tab=$tab&amp;project_id=$project_id";?>">
<table border="0" cellpadding="4" cellspacing="0">
<tr>
	<td colspan="3"><?php echo $AppUI->_('Predefined filters:');?></td>
	<td>
		<select name="display_option" class="text" onChange="document.editFrm.submit();">
			<option value="custom" <?php if ($display_option == 'custom') echo 'selected'; ?>>&nbsp;</option>
			<option value="all" <?php if ($display_option == 'all') echo 'selected'; ?>><?php echo $AppUI->_('show full project'); ?></option>
			<option value="this_month" <?php if ($display_option == 'this_month') echo 'selected'; ?>><?php echo $AppUI->_('show this month');?></option>
			<option value="3_months" <?php if ($display_option == '3_months') echo 'selected'; ?>><?php echo $AppUI->_('3 months');?></option>
			<option value="first_month" <?php if ($display_option == 'first_month') echo 'selected'; ?>><?php echo $AppUI->_('show first month');?></option>
		</select>
	</td>
</tr>
<tr>
	<td align="left" valign="top" width="20">
<?php if ($display_option != "all") { ?>
		<a href="javascript:scrollPrev()">
			<img src="./images/prev.gif" width="16" height="16" alt="<?php echo $AppUI->_( 'previous' );?>" border="0" />
		</a>
<?php } ?>
	</td>

	<td align="right" nowrap="nowrap"><?php echo $AppUI->_( 'From' );?>:</td>
	<td align="left" nowrap="nowrap">
		<input type="hidden" name="sdate" value="<?php echo $start_date->format( FMT_TIMESTAMP_DATE );?>" />
		<input type="text" class="text" name="show_sdate" value="<?php echo $start_date->format( $df );?>" size="12" disabled="disabled" />
		<a href="javascript:popCalendar('sdate')"><img src="./images/calendar.gif" width="24" height="12" alt="" border="0" /></a>
	</td>

	<td align="right" nowrap="nowrap"><?php echo $AppUI->_( 'To' );?>:</td>
	<td align="left" nowrap="nowrap">
		<input type="hidden" name="edate" value="<?php echo $end_date->format( FMT_TIMESTAMP_DATE );?>" />
		<input type="text" class="text" name="show_edate" value="<?php echo $end_date->format( $df );?>" size="12" disabled="disabled" />
		<a href="javascript:popCalendar('edate')"><img src="./images/calendar.gif" width="24" height="12" alt="" border="0" /></a>
	<td valign="top">
		<input type="checkbox" name="showLabels" <?php echo (($showLabels==1) ? 'checked="checked"' : '');?> /><?php echo $AppUI->_( 'Show captions' );?>
	</td>
	<td valign="top">
		<input type="checkbox" name="showWork" <?php echo (($showWork==1) ? 'checked="checked"' : '');?> /><?php echo $AppUI->_( 'Show work instead of duration' );?>
	</td>	
	<td align="left">
		<input type="button" class="button" value="<?php echo $AppUI->_( 'submit' );?>" onclick="document.editFrm.display_option.value='custom';submit();" />
	</td>

	<td align="right" valign="top" width="20">
<?php if ($display_option != "all") { ?>
	  <a href="javascript:scrollNext()">
	  	<img src="./images/next.gif" width="16" height="16" alt="<?php echo $AppUI->_( 'next' );?>" border="0" />
	  </a>
<?php } ?>
	</td>
</tr>
<?php if($a == 'todo') { ?>
<tr>
	<td align="center" valign="bottom" nowrap="nowrap" colspan="7">
		<table width="100%" border="0" cellpadding="1" cellspacing="0">
			<tr>
			<td align="center" valign="bottom" nowrap="nowrap">
				<input type=checkbox name="showPinned" <?php echo $showPinned ? 'checked="checked"' : ""; ?> /><?php echo $AppUI->_('Pinned Only'); ?>
			</td>
			<td align="center" valign="bottom" nowrap="nowrap">
				<input type=checkbox name="showArcProjs" <?php echo $showArcProjs ? 'checked="checked"' : ""; ?> /><?php echo $AppUI->_('Archived Projects'); ?>
			</td>
			<td align="center" valign="bottom" nowrap="nowrap">
				<input type=checkbox name="showHoldProjs" <?php echo $showHoldProjs ? 'checked="checked"' : ""; ?> />
			<?php echo $AppUI->_('Projects on Hold'); ?>
			</td>
			<td align="center" valign="bottom" nowrap="nowrap">
				<input type=checkbox name="showDynTasks" <?php echo $showDynTasks ? 'checked="checked"' : ""; ?> />
			<?php echo $AppUI->_('Dynamic Tasks'); ?>
			</td>
			<td align="center" valign="bottom" nowrap="nowrap">
				<input type=checkbox name="showLowTasks" <?php echo $showLowTasks ? 'checked="checked"' : ""; ?> />
				<?php echo $AppUI->_('Low Priority Tasks'); ?>
			</td>
			</tr>
		</table>
	</td>
</tr>
<?php } ?>
</table>
</form>

<table cellspacing="0" cellpadding="0" border="1" align="center">
<tr>
	<td>
<?php
if ($a != 'todo') {
	$q = new DBQuery;
	$q->addTable('tasks');
	$q->addQuery('COUNT(*) AS N');
	$q->addWhere('task_project='.$project_id);

	$cnt = $q->loadList();
	$q->clear();
} else {
	if (empty($tasks))
		$cnt[0]['N'] = 0;
	else 	
		$cnt[0]['N'] = 1;
}

if ($cnt[0]['N'] > 0) {
	$src = '?m=tasks&a=gantt&suppressHeaders=1&project_id='.$project_id;
	// Set the width of the image (based on browser window width.
	$src .= "&width=' + ((navigator.appName=='Netscape'?window.innerWidth:document.body.offsetWidth)*" . (($uistyle == 'classic')?0.8:0.95) . ") + '";
	$src .= '&showLabels='.$showLabels;
	$src .= '&showWork='.$showWork;
	$src .= '&showPinned='.$showPinned;
	$src .= '&showArcProjs='.$showArcProjs;
	$src .= '&showHoldProjs='.$showHoldProjs;
	$src .= '&showDynTasks='.$showDynTasks;
	$src .= '&showLowTasks='.$showLowTasks;
	$src .= '&caller='.$a;
	$src .= '&user_id='.$user_id;
	if ($display_option != 'all')
		$src .=	'&start_date=' . $start_date->format( '%Y-%m-%d' ) . '&end_date=' . $end_date->format( '%Y-%m-%d' );


	// document.write used so javascript is applied to find browser width.
	echo "
<script type=\"text/javascript\">
<!--
document.write('<img src=\"$src\" alt=\"gantt chart\" />');
-->
</script>";
} else {
	echo $AppUI->_( 'No tasks to display' );
}
?>
	</td>
</tr>
</table>
<br />