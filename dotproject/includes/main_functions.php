<?php /* INCLUDES $Id$ */
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

/** @file */

/**
 * Global General Purpose Functions
 */

$CR = "\n";
/**
 * \def SECONDS_PER_DAY
 */
define('SECONDS_PER_DAY', 60 * 60 * 24);

/**
 * Returns the best color based on a background color (x is cross-over)
 */

function bestColor( $bg, $lt='#ffffff', $dk='#000000' )
{
// cross-over color = x
	$x = 128;
	$r = hexdec( substr( $bg, 0, 2 ) );
	$g = hexdec( substr( $bg, 2, 2 ) );
	$b = hexdec( substr( $bg, 4, 2 ) );

	if ($r < $x && $g < $x || $r < $x && $b < $x || $b < $x && $g < $x) {
		return $lt;
	} else {
		return $dk;
	}
}

/**
 * returns a select box based on an key,value array where selected is based on key
 */
function arraySelect( &$arr, $select_name, $select_attribs, $selected, $translate=false )
{
	global $AppUI;
	if (! is_array($arr)) {
		dprint(__FILE__, __LINE__, 0, 'arraySelect called with no array');
		return '';
	}
	reset( $arr );
	$s = "\n<select name=\"$select_name\" $select_attribs>";
	$did_selected = 0;
	foreach ($arr as $k => $v ) {
		if ($translate) {
			$v = @$AppUI->_( $v );
			// This is supplied to allow some Hungarian characters to
			// be translated correctly. There are probably others.
			// As such a more general approach probably based upon an
			// array lookup for replacements would be a better approach. AJD.
			$v=str_replace('&#369;','�',$v);
			$v=str_replace('&#337;','�',$v);
		}
		$s .= "\n\t<option value=\"".$k."\"".(($k == $selected && !$did_selected) ? " selected=\"selected\"" : '').">" .  $v  . "</option>";
		if ($k == $selected) {
			$did_selected = 1;
		}
	}
	$s .= "\n</select>\n";
	return $s;
}

/**
 * returns a select box based on an key,value array where selected is based on key
 */
function arraySelectTree( &$arr, $select_name, $select_attribs, $selected, $translate=false )
{
	global $AppUI;
	reset( $arr );

	$children = array();
	// first pass - collect children
	foreach ($arr as $k => $v ) {
		$id = $v[0];
		$pt = $v[2];
		$list = @$children[$pt] ? $children[$pt] : array();
		array_push($list, $v);
	    $children[$pt] = $list;
	}
	$list = tree_recurse($arr[0][2], '', array(), $children);
	return arraySelect( $list, $select_name, $select_attribs, $selected, $translate );
}

function tree_recurse($id, $indent, $list, $children)
{
	if (@$children[$id]) {
		foreach ($children[$id] as $v) {
			$id = $v[0];
			$txt = $v[1];
			$pt = $v[2];
			$list[$id] = "$indent $txt";
			$list = tree_recurse($id, "$indent--", $list, $children);
		}
	}
	return $list;
}

/**
**	Provide Projects Selectbox sorted by Companies
**	@author gregorerhardt with special thanks to original author aramis
**	@param 	int 		userID
**	@param 	string 	HTML select box name identifier
**	@param	string	HTML attributes
**	@param	int			Proejct ID for preselection
**	@param 	int			Project ID which will be excluded from the list 
**									(e.g. in the tasks import list exclude the project to import into)
**	@return	string 	HTML selectbox

*/

function projectSelectWithOptGroup( $user_id, $select_name, $select_attribs, $selected, $excludeProjWithId = null ) {
    global $AppUI ;
    $q = new DBQuery();
    $q->addTable('projects');
    $q->addQuery('project_id, co.company_name, project_name');
		if (!empty($excludeProjWithId)){
			$q->addWhere('project_id != '.$excludeProjWithId);
		}
    $proj = new CProject();
    $proj->setAllowedSQL( $user_id, $q );
    $q->addOrder('co.company_name, project_name');
    $projects = $q->loadList();
    $s = "\n<select name=\"$select_name\" $select_attribs>";
    $s .= "\n\t<option value=\"0\" " . ( $selected == 0 ? "selected=\"selected\"" : "" ) ." >" . $AppUI->_('None') . "</option>" ;
    $current_company = "";
    foreach ($projects as $p )
        {
        if ( $p['company_name'] != $current_company )
            {
            $current_company = $p['company_name'];
            $s .= "\n<optgroup label=\"" . $current_company . "\" >" . $current_company. "</optgroup>" ;
            }
        $s .= "\n\t<option value=\"".$p['project_id']."\"".($selected == $p['project_id'] ? " selected=\"selected\"" : '').">&nbsp;&nbsp;&nbsp;" . $p['project_name'] . "</option>";
        }
    $s .= "\n</select>\n";
    return $s;
}



/**
 * Merges arrays maintaining/overwriting shared numeric indicees 
 */
function arrayMerge( $a1, $a2 )
{
	foreach ($a2 as $k => $v) {
		$a1[$k] = $v;
	}
	return $a1;
}

/**
 * breadCrumbs - show a colon separated list of bread crumbs
 * array is in the form url => title
 */
function breadCrumbs( &$arr )
{
	global $AppUI;
	$crumbs = array();
	foreach ($arr as $k => $v) {
		$crumbs[] = "<a href=\"$k\">".$AppUI->_( $v )."</a>";
	}
	return implode( ' <strong>:</strong> ', $crumbs );
}

/**
 * generate link for context help -- old version
 */
function contextHelp( $title, $link='' )
{
	return dPcontextHelp( $title, $link );
}

function dPcontextHelp( $title, $link='' )
{
	global $AppUI;
	return "<a href=\"#$link\" onclick=\"javascript:window.open('?m=help&dialog=1&hid=$link', 'contexthelp', 'width=400, height=400, left=50, top=50, scrollbars=yes, resizable=yes')\">".$AppUI->_($title)."</a>";
}

/**
* Retrieves a configuration setting.
* @param $key string The name of a configuration setting
* @param $default string The default value to return if the key not found.
* @return The value of the setting, or the default value if not found.
*/
function dPgetConfig( $key, $default = null )
{
	global $dPconfig;
	if (array_key_exists( $key, $dPconfig )) {
		return $dPconfig[$key];
	} else {
		return $default;
	}
}

function dPgetUsername( $user )
{
	global $AppUI;

	$q  = new DBQuery;
	$q->addTable('users');
	$q->addQuery('contact_first_name, contact_last_name');
	$q->addQuery('user_username');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');
	$q->addWhere('user_username like \'' . $user . '\' OR user_id = \'' . $user . "'");
	list($contact) = $q->loadList();
	$user_format = $AppUI->getPref('USERFORMAT');
	
	if ($user_format == 'first') {
		return $contact['contact_first_name'] . ' ' . $contact['contact_last_name'];
	} elseif ($user_format == 'last') {
		return $contact['contact_last_name'] . ', ' . $contact['contact_first_name'];
	} elseif ($user_format == 'user') {
		return $contact['user_username'];
	} else {
		return $contact['contact_order_by'];
	}
}

function dPgetUsernameFromID( $user )
{
	return dPgetUsername($user);
}

function dPgetUsersHash($selected = null, $active_only = true)
{
	global $AppUI;

	$q  = new DBQuery;
	$q->addTable('users');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');

	$q->addQuery('user_id');

	$uf = $AppUI->getPref(USERFORMAT);
	if ($uf == 'first')	{
		$q->addQuery('concat_ws(" ", contact_first_name, contact_last_name) as name');
		$q->addOrder('contact_first_name, contact_last_name');
	}	elseif ($uf == 'last')	{
		$q->addQuery('concat_ws(", ", contact_last_name, contact_first_name) as name');
		$q->addOrder('contact_last_name, contact_first_name');
	}	elseif ($uf == 'user') {
		$q->addQuery('user_username as name');
		$q->addOrder('user_username');
	} else {
		$q->addQuery('contact_order_by');
		$q->addOrder('contact_order_by');
	}
	
	if (isset($selected))
	{
		if (is_array($selected))
			$selected = implode(', ', $selected);
		$q->addWhere('user_id IN ('.$selected.')');
	}

	$users = $q->loadHashList();

	if ($active_only)
	{
		$perms = & $AppUI->acl();
		foreach ($users as $user_id => $user_data)
		{
			if ($perms->isUserPermitted($user_id) != true)
				unset($users[$user_id]);
			$roles = $perms->getUserRoles($user_id);
			foreach ($roles as $role)
		    if (strtolower($role['name']) == 'guest')
    		 	unset($users[$user_id]);
		}
	}
	
	return $users;
}

function dPgetUsers($active_only = true)
{
	global $AppUI;
	if ($_SESSION['users'])
		$users = $_SESSION['users'];
	else
	{
		$q  = new DBQuery;
		$q->addTable('users');
		$q->addJoin('contacts', 'con', 'contact_id = user_contact');

		$q->addQuery('user_id');

		$uf = $AppUI->getPref(USERFORMAT);
		if ($uf == 'first')	{
			$q->addQuery('concat_ws(" ", contact_first_name, contact_last_name) as name');
			$q->addOrder('contact_first_name, contact_last_name');
		}	else if ($uf == 'last')	{
			$q->addQuery('concat_ws(", ", contact_last_name, contact_first_name) as name');
			$q->addOrder('contact_last_name, contact_first_name');
		}	elseif ($uf == 'user') {
		$q->addQuery('user_username as name');
		$q->addOrder('user_username');
		} else {
			$q->addQuery('contact_order_by');
			$q->addOrder('contact_order_by');
		}

		$users = $q->loadHashList();
		$_SESSION['users'] = $users;
	}
	if ($active_only)
	{
		$perms = & $AppUI->acl();
		foreach ($users as $user_id => $user_data)
		{
			if ($perms->isUserPermitted($user_id) != true)
				unset($users[$user_id]);
			$roles = $perms->getUserRoles($user_id);
			foreach ($roles as $role)
		    if (strtolower($role['name']) == 'guest')
    		 	unset($users[$user_id]);

		}
	}
	
	return arrayMerge( array( 0 => $AppUI->_('All Users') ), $users );
}

/**
 * displays the configuration array of a module for informational purposes
 */
function dPshowModuleConfig( $config )
{
	global $AppUI;
	$s = '<table cellspacing="2" cellpadding="2" border="0" class="std" width="50%">';
	$s .= '<tr><th colspan="2">'.$AppUI->_( 'Module Configuration' ).'</th></tr>';
	foreach ($config as $k => $v) {
		$s .= '<tr><td width="50%">'.$AppUI->_( $k ).'</td><td width="50%" class="hilite">'.$AppUI->_( $v ).'</td></tr>';
	}
	$s .= '</table>';
	return ($s);
}

/**
 *	Function to recussively find an image in a number of places
 *	@param string The name of the image
 *	@param string Optional name of the current module
 */
function dPfindImage( $name, $module=null )
{
// uistyle must be declared globally
	global $uistyle, $iconstyle;

	// Iconset
	if ($iconstyle && $module && file_exists(DP_BASE_DIR . "/style/_iconsets/$iconstyle/$module/$name" )) {
		return "./style/_iconsets/$iconstyle/$module/$name";
	} else if ($iconstyle && file_exists(DP_BASE_DIR . "/style/_iconsets/$iconstyle/_icons/$name" )) {
		return "./style/_iconsets/$iconstyle/_icons/$name";
	} else if ($iconstyle && file_exists(DP_BASE_DIR . "/style/_iconsets/$iconstyle/_obj/$name" )) {
		return "./style/_iconsets/$iconstyle/_obj/$name";
	} else if ($iconstyle && file_exists(DP_BASE_DIR . "/style/_iconsets/$iconstyle/$name" )) {
		return "./style/_iconsets/$iconstyle/$name";
	// Template style
	} else if (file_exists(DP_BASE_DIR . "/style/$uistyle/images/$name" )) {
		return "./style/$uistyle/images/$name";
	// Module
	} else if ($module && file_exists(DP_BASE_DIR . "/modules/$module/images/$name" )) {
		return "./modules/$module/images/$name";
	// general image/icon
	} else if (file_exists(DP_BASE_DIR . '/images/icons/'.$name)) {
		return './images/icons/'.$name;
	} else if (file_exists(DP_BASE_DIR . '/images/obj/'.$name)) {
		return './images/obj/'.$name;
	} else {
		return './images/'.$name;
	}
}

/**
 *  Function deprecated - to be replaced by a smarty dPshowImage function
 *	Workaround removed due to problems in Opera and other issues
 *	with IE6.
 *	Workaround to display png images with alpha-transparency in IE6.0
 *	@param string The name of the image
 *	@param string The image width
 *	@param string The image height
 *	@param string The alt text for the image
 */
function dPshowImage( $src, $wid='', $hgt='', $alt='', $title='' )
{
	global $AppUI;
	/*
	if (strpos( $src, '.png' ) > 0 && strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 6.0' ) !== false) {
		return "<div style=\"height:{$hgt}px; width:{$wid}px; filter:progid:DXImageTransform.Microsoft.AlphaImageLoader(src='$src', sizingMethod='scale');\" ></div>";
	} else {
	*/
		if ($src == '')
			return '';

		$result = "<img src='$src'"; //align='center'";
		if ($wid)
		  $result .= " width='$wid'";
		if ($hgt)
		  $result .= " height='$hgt'";
		if (empty($alt))
			$alt = substr($src, 0, -4);
	  $result .= " alt='" . $AppUI->_($alt) . "'";

		if ($title)
		  $result .= " title='" . $AppUI->_($title) . "'";
		$result .= ' border="0" />';

		return $result;
	// }
}

/**
 * function to return a default value if a variable is not set
 */
function defVal($var, $def)
{
	return isset($var) ? $var : $def;
}

/**
* Utility function to return a value from a named array or a specified default
*/
function dPgetParam( &$arr, $name, $def=null )
{
	return isset( $arr[$name] ) ? $arr[$name] : $def;
}

/**
 * add history entries for tracking changes
 */
function addHistory( $table, $id, $action = 'modify', $details = '')
{ //$description = '', $project_id = 0) {
	global $AppUI;
	/*
	 * TODO:
	 * 1) description should be something like:
	 * 		command(arg1, arg2...)
         *  The command should be as module_action
	 *  for example:
	 * 		forums_new('Forum Name', 'URL')
	 *
	 * This way, the history module will be able to display descriptions
	 * using locale definitions:
	 * 		"forums_new" -> "New forum '%s' was created" -> "Se ha creado un nuevo foro llamado '%s'"
	 *
	 * 2) project_id and module_id should be provided in order to filter history entries
	 *
	 */
	if (!dPgetConfig('log_changes')) return;

//	$hsql = "select * from modules where mod_name = 'History' and mod_active = 1";
	$q  = new DBQuery;
	$q->addTable('modules');
	$q->addWhere("mod_name = 'History' and mod_active = 1");
	$qid = $q->exec();

	if (! $qid || db_num_rows($qid) == 0) {
	  $AppUI->setMsg('History module is not loaded, but your config file has requested that changes be logged.  You must either change the config file or install and activate the history module to log changes.', UI_MSG_ALERT);
		$q->clear();
	  return;
	}
	

	$q->clear();
	$q->addTable('history');
	$q->addInsert('history_action', $action);
	
	if (class_exists('cdate'))
	{
		$now = new CDate();
		$q->addInsert('history_date', $now->format(FMT_DATETIME_MYSQL));
	}
	else
		$q->addInsert('history_date', 'now()', false, true); 
	
	$q->addInsert('history_user', $AppUI->user_id);
	$q->addInsert('history_table', $table);
	$q->addInsert('history_item', $id);
	if (is_array($details)) {
		foreach ($details as $field => $value) {
			$q->addInsert('history_' . $field, $value);
		}
	} else {
		$details = str_replace("'", "\'", $details);
		$q->addInsert('history_description', $details);
	}

//	$q->addInsert('history_project', $project_id);

	$q->exec();
	echo db_error();
	$q->clear();
}

// Old sysvals function
/*
function dPgetSysVal( $title )
{
	$q  = new DBQuery;
	$q->addTable('sysvals');
	$q->leftJoin('syskeys', 'sk', 'syskey_id = sysval_key_id');
	$q->addQuery('syskey_type, syskey_sep1, syskey_sep2, sysval_value');
	$q->addWhere("sysval_title = '$title'");
	$q->exec();
	$row = $q->fetchRow();
	$q->clear();
// type 0 = list
	$sep1 = $row['syskey_sep1'];	// item separator
	$sep2 = $row['syskey_sep2'];	// alias separator

	// A bit of magic to handle newlines and returns as separators
	// Missing sep1 is treated as a newline.
	if (!isset($sep1) || empty($sep1)) {
	  $sep1 = "\n";
	}
	if ($sep1 == "\\n") {
	  $sep1 = "\n";
	}
	if ($sep1 == "\\r") {
	  $sep1 = "\r";
	}

	$temp = explode( $sep1, $row['sysval_value'] );
	$arr = array();
	// We use trim() to make sure a numeric that has spaces
	// is properly treated as a numeric
	foreach ($temp as $item) {
		if ($item) {
			$sep2 = empty($sep2) ? "\n" : $sep2;
			$temp2 = explode( $sep2, $item );
			if (isset( $temp2[1] )) {
				$arr[trim($temp2[0])] = trim($temp2[1]);
			} else {
				$arr[trim($temp2[0])] = trim($temp2[0]);
			}
		}
	}
	return $arr;
}
*/

/**
 * Looks up a value from the SYSVALS table
 */
function dPgetSysVal( $title )
{
	$q = new DBQuery;
	$q->addTable('sysvals');
	$q->addQuery('sysval_value_id, sysval_value');
	$q->addWhere("sysval_title = '$title'");
	$q->exec();

	$arr = $q->loadHashList();

	$q->clear();

	return $arr;
}

function dPuserHasRole( $name )
{
	global $AppUI;
	$uid = $AppUI->user_id;
	$sql = "SELECT r.role_id FROM roles AS r,user_roles AS ur WHERE ur.user_id=$uid AND ur.role_id=r.role_id AND r.role_name='$name'";
	$q  = new DBQuery;
	$q->addTable('roles', 'r');
	$q->addTable('user_roles', 'ur');
	$q->addQuery('r.role_id');
	$q->addWhere("ur.user_id=$uid AND ur.role_id=r.role_id AND r.role_name='$name'");
	return $q->loadResult();
}

function dPformatDuration($x)
{
    global $AppUI;
    $dur_day = floor($x / dPgetConfig('daily_working_hours'));
    //$dur_hour = fmod($x, dPgetConfig('daily_working_hours'));
    $dur_hour = $x - $dur_day*dPgetConfig('daily_working_hours');
    $str = '';
    if ($dur_day > 1) {
        $str .= $dur_day .' '. $AppUI->_('days'). ' ';
    } elseif ($dur_day == 1) {
        $str .= $dur_day .' '. $AppUI->_('day'). ' ';
    }

    if ($dur_hour > 1 ) {
        $str .= $dur_hour .' '. $AppUI->_('hours');
    } elseif ($dur_hour > 0 and $dur_hour <= 1) {
        $str .= $dur_hour .' '. $AppUI->_('hour');
    }

    if ($str == '') {
        $str = $AppUI->_('n/a');
    }

    return $str;
}

/**
*/
function dPsetMicroTime()
{
	global $microTimeSet;
	list($usec, $sec) = explode(' ', microtime());
	$microTimeSet = (float)$usec + (float)$sec;
}

/**
*/
function dPgetMicroDiff()
{
	global $microTimeSet;
	$mt = $microTimeSet;
	dPsetMicroTime();
	return sprintf( '%.3f', $microTimeSet - $mt );
}

/**
* Make text safe to output into double-quote enclosed attirbutes of an HTML tag
*/
function dPformSafe( $txt, $deslash = false )
{
	global $locale_char_set;
	
	if (!$locale_char_set){
		$locale_char_set = 'utf-8';
	}
	
	if (is_object( $txt )) {
		foreach (get_object_vars($txt) as $k => $v) {
			if ($deslash) {
				$obj->$k = htmlspecialchars( stripslashes( $v ), ENT_COMPAT, $locale_char_set );
			} else {
				$obj->$k = htmlspecialchars( $v, ENT_COMPAT, $locale_char_set );
			}
		}
	} elseif (is_array( $txt )) {
		foreach ($txt as $k=>$v) {
			if ($deslash) {
				$txt[$k] = htmlspecialchars( stripslashes( $v ), ENT_COMPAT, $locale_char_set );
			} else {
				$txt[$k] = htmlspecialchars( $v, ENT_COMPAT, $locale_char_set );
			}
		}
	} else {
		if ($deslash) {
			$txt = htmlspecialchars( stripslashes( $txt ), ENT_COMPAT, $locale_char_set );
		} else {
			$txt = htmlspecialchars( $txt, ENT_COMPAT, $locale_char_set );
		}
	}
	return $txt;
}

function convert2days( $durn, $units )
{
	switch ($units) {
		case 0:
		case 1:
			return $durn / dPgetConfig('daily_working_hours');
			break;
		case 24:
			return $durn;
	}
}

/**
 * This function is necessary because Windows likes to
 * write their own standards.  Nothing that depends on locales
 * can be trusted in Windows.
 */
function formatCurrency( $number, $format )
{
	global $AppUI, $locale_char_set;

	if (!$format) {
		$format = $AppUI->getPref('SHCURRFORMAT');
	}
	// If the requested locale doesn't work, don't fail,
	// revert to the system default.
	if ( $locale_char_set != 'utf-8' || ! setlocale(LC_MONETARY, $format . '.UTF8') ) {
		if (! setlocale(LC_MONETARY, $format)) {
			setlocale(LC_MONETARY, "");
		}
	}
	if (function_exists('money_format')) {
		return money_format('%i', $number);
	}

	// NOTE: This is called if money format doesn't exist.
	// Money_format only exists on non-windows 4.3.x sites.
	// This uses localeconv to get the information required
	// to format the money.  It tries to set reasonable defaults.
	$mondat = localeconv();
	if (! isset($mondat['int_frac_digits']) || $mondat['int_frac_digits'] > 100) {
		$mondat['int_frac_digits'] = 2;
	}
	if (! isset($mondat['int_curr_symbol'])) {
		$mondat['int_curr_symbol'] = '';
	}
	if (! isset($mondat['mon_decimal_point'])) {
		$mondat['mon_decimal_point'] = '.';
	}
	if (! isset($mondat['mon_thousands_sep'])) {
		$mondat['mon_thousands_sep'] = ',';
	}
	$numeric_portion = number_format(abs($number),
		$mondat['int_frac_digits'],
		$mondat['mon_decimal_point'],
		$mondat['mon_thousands_sep']);
	// Not sure, but most countries don't put the sign in if it is positive.
	$letter='p';
	$currency_prefix='';
	$currency_suffix='';
	$prefix='';
	$suffix='';
	if ($number < 0) {
		$sign = $mondat['negative_sign'];
		$letter = 'n';
		switch ($mondat['n_sign_posn']) {
			case 0:
				$prefix="(";
				$suffix=")";
				break;
			case 1:
				$prefix = $sign;
				break;
			case 2:
				$suffix = $sign;
				break;
			case 3:
				$currency_prefix = $sign;
				break;
			case 4:
				$currency_suffix = $sign;
				break;
		}
	}
	$currency .= $currency_prefix . $mondat['int_curr_symbol'] . $currency_suffix;
	$space = '';
	if ($mondat[$letter . '_sep_by_space']) {
		$space = ' ';
	}
	if ($mondat[$letter . '_cs_precedes']) {
		$result = "$currency$space$numeric_portion";
	} else {
		$result = "$numeric_portion$space$currency";
	}
	return $result;
}

function format_backtrace($bt, $file, $line, $msg)
{
  echo "<pre>\n";
  echo "ERROR: $file($line): $msg\n";
  echo "Backtrace:\n";
  foreach ($bt as $level => $frame) {
    echo "$level $frame[file]:$frame[line] $frame[function](";
    $in = false;
		if (!empty($frame['args']))
    foreach ($frame['args'] as $arg) {
      if ($in)
	echo ",";
      else
	$in = true;
      echo var_export($arg, true);
    }
    echo ")\n";
  }
}

function dprint($file, $line, $level, $msg)
{
  $max_level = 0;
  $max_level = (int)dPgetConfig('debug', 0);
	$display_debug = dPgetConfig('display_debug', false);
  if ($level <= $max_level) {
    error_log("$file($line): $msg");
		if ($display_debug)
			echo "$file($line): $msg <br />";
    if ($level == 0 && $max_level > 0 && version_compare(phpversion(), "4.3.0") >=0 ) {
      format_backtrace(debug_backtrace(), $file, $line, $msg);
    }
  }
}

/**
 * Return a list of modules that are associated with tabs for this
 * page.  This can be used to find post handlers, for instance.
 */
function findTabModules($module, $file = null)
{
	$modlist = array();
	if (!isset($_SESSION['all_tabs']) || ! isset($_SESSION['all_tabs'][$module]))
		return $modlist;

	if (isset($file)) {
		if (isset($_SESSION['all_tabs'][$module][$file]) && is_array($_SESSION['all_tabs'][$module][$file]))
			$tabs_array =& $_SESSION['all_tabs'][$module][$file];
		else
			return $modlist;
	} else {
		$tabs_array =& $_SESSION['all_tabs'][$module];
	}
	foreach ($tabs_array as $tab) {
		if (isset($tab['module']))
			$modlist[] = $tab['module'];
	}
	return array_unique($modlist);
}

/**
 * @return void
 * @param mixed $var
 * @param char $title
 * @desc Show an estructure (array/object) formatted
*/
function showFVar(&$var, $title = "")
{
    echo '
	<h1>' . $title . '</h1>
	<pre>' . print_r($var, true) . '</pre>';
}

function getUsersCombo($default_user_id = 0, $first_option = 'All users')
{
    global $AppUI;
    
    $parsed = '<select name="user_id" class="text">';
    if($first_option != '')
        $parsed .= '<option value="0" '.(!$default_user_id ? 'selected="selected"' : '').'>'.$AppUI->_($first_option).'</option>';

		$users = dPgetUsers();
    foreach($users as $user_id => $user_name){
        $selected = $user_id == $default_user_id ? 'selected="selected"' : '';
        $parsed .= '<option value="'.$user_id.'" '.$selected.'>'.$user_name.'</option>';
    }
    $parsed .= '</select>';
    return $parsed;
}

/**
 * Function to format hours into useful numbers.
 * Supplied by GrahamJB.
 */
function formatHours($hours)
{
	global $AppUI;

	$hours = (int)$hours;
	$working_hours = dPgetConfig('daily_working_hours');

	if ($hours < $working_hours) {
		if ($hours == 1) {
			return '1 ' . $AppUI->_('hour');
		} else {
			return $hours . ' ' . $AppUI->_('hours');
		}
	}

	$hoursPart = $hours % $working_hours;
	$daysPart = (int)($hours / $working_hours);
	if ($hoursPart == 0) {
		if ($daysPart == 1) {
			return '1 ' . $AppUI->_('day');
		} else {
			return $daysPart . ' ' . $AppUI->_('days');
		}
	}

	if ($daysPart == 1) {
		return '1 ' . $AppUI->_('day') . ' ' . $hoursPart . ' ' . $AppUI->_('hr');
	} else {
		return $daysPart . ' ' . $AppUI->_('days') . ' ' . $hoursPart . ' ' . $AppUI->_('hr');
	} 
}

/**
 * PHP doesn't come with a signum function
 */
function dPsgn($x) {
   return $x ? ($x>0 ? 1 : -1) : 0;
}

/**
 * This function is now deprecated and will be removed.
 * In the interim it now does nothing.
 */
function dpRealPath($file)
{
	return $file;
}

/**
 * Create the Required Fields (From Sysvals) JavaScript Code
 * For instance implemented in projects and tasks addedit.php
 * @param array required field array from SysVals
 */
function dPrequiredFields($requiredFields) 
{
	global $AppUI, $m;
	$buffer = 'var foc=false;'."\n";

	if (!empty($requiredFields))
	{
		foreach ($requiredFields as $rf=>$comparator)
		{
			$buffer.= "\n".'if ('. $rf . html_entity_decode($comparator, ENT_QUOTES) .') {'."\n";
			$buffer.= "\t".'msg += "\n'.$AppUI->_('required_field_'.$rf, UI_OUTPUT_JS).'";'."\n";

			/* MSIE cannot handle the focus command for some disabled or hidden fields like the start/end date fields
			** Another workaround would be to check whether the field is disabled, 
			** but then one would for instance need to use end_date instead of project_end_date in the projects addedit site.
			** As this cannot be guaranteed since these fields are grabbed from a user-specifiable 
			** System Value it's IMHO more safe to disable the focus for MSIE.
			*/
			$r = strstr($rf, '.');
			$buffer .= "\t".'if((foc==false) && (navigator.userAgent.indexOf(\'MSIE\')== -1)) {'."\n";
			$buffer.= "\t\t".'f.'.substr($r,1,strpos($r,'.',1)-1).'.focus();'."\n";
			$buffer.= "\t\t".'foc=true;'."\n";
			$buffer.= "\t}\n";
			$buffer.= "}\n";
		}
	}
	return $buffer;
}
?>
