<?php // $Id$
if (!defined('DP_BASE_DIR')){
	die('You should not access this file directly');
}

/**
 * Permissions system extends the phpgacl class.  Very few changes have
 * been made, however the main one is to provide the database details from
 * the main dP environment.
 */

// Set the ADODB directory
if (! defined('ADODB_DIR')) {
  define('ADODB_DIR', DP_BASE_DIR . '/lib/adodb');
}
 
// Include the PHPGACL library
require_once DP_BASE_DIR . '/lib/phpgacl/gacl.class.php';
require_once DP_BASE_DIR . '/lib/phpgacl/gacl_api.class.php';
// Include the db_connections 

// Now extend the class
/** Permissions checking class, based on phpgacl
 *
 * Extends the gacl_api class.  There is an argument to separate this
 * into a gacl and gacl_api class on the premise that normal activity
 * only needs the functions in gacl, but it would appear that this is
 * not so for dP, which tends to require reverse lookups rather than
 * just forward ones (i.e. looking up who is allowed to do x, rather
 * than is x allowed to do y).
 */
class dPacl extends gacl_api {

  /** dPacl constructor
   * @param $opts Options to supply to the parent gacl_api object
   */
  function dPacl($opts = null)
  {
    global $db;
    
    if (! is_array($opts))
      $opts = array();
    $opts['db_type'] = dPgetConfig('dbtype');
    $opts['db_host'] = dPgetConfig('dbhost');
    $opts['db_user'] = dPgetConfig('dbuser');
    $opts['db_password'] = dPgetConfig('dbpass');
    $opts['db_name'] = dPgetConfig('dbname');
    $opts['db'] = $db;
    // We can add an ADODB instance instead of the database
    // connection details.  This might be worth looking at in
    // the future.
    if (dPgetConfig('debug', 0) > 10)
      $opts['debug'] = true;
      
    // Enable caching
    $opts['caching'] = true;
    $opts['cache_expire_time'] = 6000;
    $opts['cache_dir'] = DP_BASE_DIR . '/files/cache/phpgacl';
    
    parent::gacl_api($opts);
  }

  /** calls gacl acl_check() function */
  function acl_check($aco_section_value, $aco_value, $aro_section_value, $aro_value, $axo_section_value=NULL, $axo_value=NULL, $root_aro_group=NULL, $root_axo_group=NULL)
  {
  	global $acltime;
    $startTime = array_sum(explode(' ',microtime()));
    
  	$ret = parent::acl_check($aco_section_value, $aco_value, $aro_section_value, $aro_value, $axo_section_value, $axo_value, $root_aro_group, $root_axo_group);

  	$acltime += array_sum(explode(' ', microtime())) - $startTime;
  	
  	return $ret;
  }
  
  /** Check to see if a user has permission to login
   * @param $login login name to check
   * @return return status of acl_check
   */
  function checkLogin($login)
  {
    // Simple ARO<->ACO check, no AXO's required.
    return $this->acl_check("system", "login", "user", $login);
  }

  /** Check to see if a user has permission to access a module
   * @param $module the module to check against
   * @param $op operation to check(?)
   * @param $userid the user's id to check against
   * @return true if the user has access to the module
   */
  function checkModule($module, $op, $userid = null)
  {
    if (! $userid) {
      $userid = $GLOBALS['AppUI']->user_id;
    }
    $module = ($module == 'sysvals') ? 'system' : $module;
    $result = $this->acl_check("application", $op, "user", $userid, "app", $module);
    dprint(__FILE__, __LINE__, 2, "checkModule( $module, $op, $userid) returned $result");
    return $result;
  }

  /** Check to see if a user has permission to access a module item
   * 
   * The item is a dotProject object for example a project within the projects module.
   * @param $module the module to check against
   * @param $op operation to check(?)
   * @param $item item to check permissions against, if null calls checkModule() to check the basic module permissions
   * @param $userid the user's ID to check with, if null uses the user_id property of the AppUI object (the currently logged in user).
   * @return True if the user has permission
   */
  function checkModuleItem($module, $op, $item = null, $userid = null)
  {
    if (! $userid)
      $userid = $GLOBALS['AppUI']->user_id;
    if (! $item)
      return $this->checkModule($module, $op, $userid);

    $result = $this->acl_query("application", $op, "user", $userid, $module, $item, NULL);
    // If there is no acl_id then we default back to the parent lookup
    if (! $result || ! $result['acl_id']) {
      dprint(__FILE__, __LINE__, 2, "checkModuleItem($module, $op, $userid) did not return a record");
      return $this->checkModule($module, $op, $userid);
    }
    dprint(__FILE__, __LINE__, 2, "checkModuleItem($module, $op, $userid) returned $result[allow]");
    return $result['allow'];
  }

  /** Check if the user is denied permission to access a module item
   *
   * This gets tricky and is there mainly for the compatibility layer
   * for getDeny functions.
   * If we get an ACL ID, and we get allow = false, then the item is
   * actively denied.  Any other combination is a soft-deny (i.e. not
   * strictly allowed, but not actively denied.
   * @param $module the module to check against
   * @param $op operation to check(?)
   * @param $item item to check permissions against
   * @param $userid the user's ID to check with, if null uses the user_id property of the AppUI object (the currently logged in user).
   * @return True if the user has permission  
   */
  function checkModuleItemDenied($module, $op, $item, $user_id = null)
  {
    if (! $user_id) {
      $user_id = $GLOBALS['AppUI']->user_id;
    }
    $result = $this->acl_query("application", $op, "user", $user_id, $module, $item);
    if ( $result && $result['acl_id'] && ! $result['allow'])
      return true;
    else
      return false;
  }

  /** Create an "Access Request Object" that represents a dotProject user
   * @param $login login
   * @param $username username
   * @return true if the user object was added successfully.
   */
  function addLogin($login, $username)
  {
    $res = $this->add_object("user", $username, $login, 1, 0, "aro");
    if (! $res)
      dprint(__FILE__, __LINE__, 0, "Failed to add user permission object");
    return $res;
  }

  /** Update the details of the dotProject user aro
   */
  function updateLogin($login, $username)
  {
    $id = $this->get_object_id("user", $login, "aro");
    if (! $id)
      return $this->addLogin($login, $username);
    // Check if the details have changed.
    list ($osec, $val, $oord, $oname, $ohid) = $this->get_object_data($id, "aro");
    if ($oname != $username) {
      $res = $this->edit_object( $id, "user", $username, $login, 1, 0, "aro");
      if (! $res)
	dprint(__FILE__, __LINE__, 0, "Failed to change user permission object");
    }
    return $res;
  }

  function deleteLogin($login) {
    $id = $this->get_object_id("user", $login, "aro");
    if ($id) {
      $id = $this->del_object($id, "aro", true);
      $id = $this->get_object_id("user", $login, "aro");
	    if ($id) {
	      dprint(__FILE__, __LINE__, 0, "Failed to remove user permission object");
	    }      
    }
    return $id;
  }

  function addModule($mod, $modname)
  {
    $res = $this->add_object("app", $modname, $mod, 1, 0, "axo");
    if ($res) {
       $res = $this->addGroupItem($mod);
    }
    if (! $res) {
      dprint(__FILE__, __LINE__, 0, "Failed to add module permission object");
    }
    return $res;
  }

  function addModuleSection($mod)
  {
    $res = $this->add_object_section(ucfirst($mod) . " Record", $mod, 0, 0, "axo");
    if (! $res) {
      dprint(__FILE__, __LINE__, 0, "Failed to add module permission section");
    }
    return $res;
  }

  function addModuleItem($mod, $itemid, $itemdesc)
  {
    $res = $this->add_object($mod, $itemdesc, $itemid, 0, 0, "axo");
    return $res;
  }

  function addGroupItem($item, $group = "all", $section = "app", $type = "axo")
  {
    if ($gid = $this->get_group_id($group, null, $type)) {
      return $this->add_group_object($gid, $section, $item, $type);
    }
    return false;
  }

  function deleteModule($mod)
  {
    $id = $this->get_object_id("app", $mod, "axo");
    if ($id) {
      $this->deleteGroupItem($mod);
      $id = $this->del_object($id, "axo", true);
    }
    if (! $id)
      dprint(__FILE__, __LINE__, 0, "Failed to remove module permission object");
    return $id;
  }

  function deleteModuleSection($mod)
  {
    $id = $this->get_object_section_section_id(null, $mod, "axo");
    if ($id) {
      $id = $this->del_object_section($id, "axo", true);
    }
    if (! $id)
      dprint(__FILE__, __LINE__, 0, "Failed to remove module permission section");
    return $id;
  }

  /*
	** Deleting all module-associyted entries from the phpgacl tables
	** such as gacl_aco_maps, gacl_acl and gacl_aro_map
	**
	** @author 	gregorerhardt	
	** @date		20070927
	** @cause		#2140
	**
	** @access 	public
	** @param		string	module (directory) name
	** @return
	*/

	function deleteModuleItems($mod) {
		// Declaring the return string
		$ret = NULL;

		// Fetching module-associated ACL ID's
		$q = new DBQuery;
		$q->addTable('gacl_axo_map');
		$q->addQuery('acl_id');
		$q->addWhere('value = "'.$mod.'"');
		$acls = $q->loadHashList('acl_id');
		$q->clear();
	
		foreach ($acls as $acl => $k) {
			// Deleting gacl_aco_map entries
			$q = new DBQuery;
			$q->setDelete('gacl_aco_map');
			$q->addWhere('acl_id = '.$acl);
			if (!$q->exec()) {
				$ret .= is_null($ret) ? "\n\t".db_error() : db_error();
			} 
			$q->clear();


			// Deleting gacl_aro_map entries
			$q = new DBQuery;
			$q->setDelete('gacl_aro_map');
			$q->addWhere('acl_id = '.$acl);
			if (!$q->exec()) {
				$ret .= "\n\t".db_error();
			} 
			$q->clear();

			// Deleting gacl_aco_map entries
			$q = new DBQuery;
			$q->setDelete('gacl_acl');
			$q->addWhere('id = '.$acl);
			if (!$q->exec()) {
				$ret .= "\n\t".db_error();
			} 
			$q->clear();
		}

		// Returning null (no error) or database error message (error)
		return $ret;
	}

  function deleteGroupItem($item, $group = "all", $section = "app", $type = "axo")
  {
    if ($gid = $this->get_group_id($group, null, $type)) {
      return $this->del_group_object($gid, $section, $item, $type);
    }
    return false;
  }

  function isUserPermitted($userid, $module = null)
  {
    if ($module) {
      return $this->checkModule($module, "view", $userid);
    } else {
      return $this->checkLogin($userid);
    }
  }

  function getPermittedUsers($module = null)
  {
    // Not as pretty as I'd like, but we can do it reasonably well.
    // Check to see if we are allowed to see other users.
    // If not we can only see ourselves.
    global $AppUI;
    $canViewUsers = $this->checkModule('users', 'view');
    $q  = new DBQuery;
    $q->addTable('users');
    $q->addQuery('user_id');
    $q->addJoin('contacts', 'con', 'contact_id = user_contact');
    $q->addOrder('contact_last_name');
    $userlist = $q->loadColumn();

    foreach ($userlist as $k => $user_id) {
      if ( !($canViewUsers && $this->isUserPermitted($user_id, $module))
			&& $user_id != $AppUI->user_id)
				unset($userlist[$k]);
    }
		
    //  Now format the userlist as an assoc array.
    $users = dPgetUsersHash($userlist);

    return $users;
  }

  function getItemACLs($module, $uid = null)
  {
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;
    // Grab a list of all acls that match the user/module, for which Deny permission is set.
    return $this->search_acl("application", "view", "user", $uid, false, $module, false, false, false);
  }
  function getItemEditACLs($module, $uid = null)
  {
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;
    // Grab a list of all acls that match the user/module, for which Deny permission is set.
    return $this->search_acl("application", "edit", "user", $uid, false, $module, false, false, false);
  }

  function getUserACLs($uid = null)
  {
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;
    return $this->search_acl("application", false, "user", $uid, null, false, false, false, false);
  }

  function getRoleACLs($role_id)
  {
    $role = $this->getRole($role_id);
    return $this->search_acl("application", false, false, false, $role['name'], false, false, false, false);
  }

  function getRole($role_id)
  {
    $data = $this->get_group_data($role_id);
    if ($data) {
      return array('id' => $data[0],
      	'parent_id' => $data[1],
	'value' => $data[2],
	'name' => $data[3],
	'lft' => $data[4],
	'rgt' => $data[5]);
    } else {
      return false;
    }
  }

  function & getDeniedItems($module, $uid = null)
  {
    $items = array();
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;

    $acls = $this->getItemACLs($module, $uid);
    // If we get here we should have an array.
    if (is_array($acls)) {
      // Grab the item values
      foreach ($acls as $acl) {
	$acl_entry = $this->get_acl($acl);
	if ($acl_entry['allow'] == false && $acl_entry['enabled'] == true && isset($acl_entry['axo'][$module]))
	  foreach ($acl_entry['axo'][$module] as $id) {
	  	$items[] = $id;
	  }
      }
    } else {
      dprint(__FILE__, __LINE__, 2, "getDeniedItems($module, $uid) - no ACL's match");
    }
    dprint(__FILE__,__LINE__, 2, "getDeniedItems($module, $uid) returning " . count($items) . " items");
    return $items;
  }

  // This is probably redundant.
  function & getAllowedItems($module, $uid = null)
  {
    $items = array();
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;
    $acls = $this->getItemACLs($module, $uid);
    if (is_array($acls)) {
      foreach ($acls as $acl) {
	$acl_entry = $this->get_acl($acl);
	if ($acl_entry['allow'] == true && $acl_entry['enabled'] == true && isset($acl_entry['axo'][$module])) {
	  foreach ($acl_entry['axo'][$module] as $id) {
	    $items[] = $id;
	  }
	}
      }
    } else {
      dprint(__FILE__, __LINE__, 2, "getAllowedItems($module, $uid) - no ACL's match");
    }
    dprint(__FILE__,__LINE__, 2, "getAllowedItems($module, $uid) returning " . count($items) . " items");
    return $items;
  }

  function & getEdittableItems($module, $uid = null)
  {
    $items = array();
    if (! $uid)
      $uid = $GLOBALS['AppUI']->user_id;
    $acls = $this->getItemEditACLs($module, $uid);
    if (is_array($acls)) {
      foreach ($acls as $acl) {
  $acl_entry = $this->get_acl($acl);
  if ($acl_entry['allow'] == true && $acl_entry['enabled'] == true && isset($acl_entry['axo'][$module])) {
    foreach ($acl_entry['axo'][$module] as $id) {
      $items[] = $id;
    }
  }
      }
    } else {
      dprint(__FILE__, __LINE__, 2, "getEdittableItems($module, $uid) - no ACL's match");
    }
    dprint(__FILE__,__LINE__, 2, "getEdittableItems($module, $uid) returning " . count($items) . " items");
    return $items;
  }
  // Copied from get_group_children in the parent class, this version returns
  // all of the fields, rather than just the group ids.  This makes it a bit
  // more efficient as it doesn't need the get_group_data call for each row.
  function getChildren($group_id, $group_type = 'ARO', $recurse = 'NO_RECURSE')
  {
	$this->debug_text("get_group_children(): Group_ID: $group_id Group Type: $group_type Recurse: $recurse");

	switch (strtolower(trim($group_type))) {
		case 'axo':
			$group_type = 'axo';
			$table = $this->_db_table_prefix .'axo_groups';
			break;
		default:
			$group_type = 'aro';
			$table = $this->_db_table_prefix .'aro_groups';
	}

	if (empty($group_id)) {
		$this->debug_text("get_group_children(): ID ($group_id) is empty, this is required");
		return FALSE;
	}

	$q = new DBQuery;
	$q->addTable($table, 'g1');
	$q->addQuery('g1.id, g1.name, g1.value, g1.parent_id');
	$q->addOrder('g1.value');
	
	//FIXME-mikeb: Why is group_id in quotes?
	switch (strtoupper($recurse)) {
		case 'RECURSE':
			$q->addJoin($table, 'g2', 'g2.lft<g1.lft AND g2.rgt>g1.rgt');
			$q->addWhere('g2.id='. $group_id);
			break;
		default:
			$q->addWhere('g1.parent_id='. $group_id);
	}
	
	$result = array();
	$q->exec();
	while ($row = $q->fetchRow()) {
		$result[] = array(
		 'id' => $row[0],
		 'name' => $row[1],
		 'value' => $row[2],
		 'parent_id' => $row[3]);
	}
	$q->clear();
	return $result;
  }

  function insertRole($value, $name)
  {
    $role_parent = $this->get_group_id("role");
    $value = str_replace(" ", "_", $value);
    return $this->add_group($value, $name, $role_parent);
  }

  function updateRole($id, $value, $name)
  {
    return $this->edit_group($id, $value, $name);
  }

  function deleteRole($id)
  {
    // Delete all of the group assignments before deleting group.
    $objs = $this->get_group_objects($id);
    foreach ($objs as $section => $value) {
      $this->del_group_object($id, $section, $value);
    }
    return $this->del_group($id, false);
  }

  function insertUserRole($role, $user)
  {
    // Check to see if the user ACL exists first.
    $id = $this->get_object_id("user", $user, "aro");
    if (! $id) {
      $q = new DBQuery;
      $q->addTable('users');
      $q->addQuery('user_username');
      $q->addWhere("user_id = $user");
      $rq = $q->exec();
      if (! $rq) {
	dprint(__FILE__, __LINE__, 0, "Cannot add role, user $user does not exist!<br>" . db_error() );
				$q->clear();
	return false;
      }
      $row = $q->fetchRow();
      if ($row) {
	$this->addLogin($user, $row['user_username']);
      }
			$q->clear();
    }
    return $this->add_group_object($role, "user", $user);
  }

  function deleteUserRole($role, $user)
  {
    return $this->del_group_object($role, "user", $user);
  }

  /**
   * Returns the group ids of all groups this user is mapped to.
   * Not provided in original phpGacl, but useful.
   */
  function getUserRoles($user)
  {
    $id = $this->get_object_id('user', $user, 'aro');
    $result = $this->get_group_map($id);
    if (!is_array($result))
      $result = array();
    return $result;
  }

  /**
   * Return a list of module groups and modules that a user can
   * be permitted access to.
   */
  function getModuleList()
  {
    $result = array();
    // First grab all the module groups.
    $parent_id = $this->get_group_id("mod", null, "axo");
    if (! $parent_id)
      dprint(__FILE__, __LINE__, 0, "failed to get parent for module groups");
    $groups = $this->getChildren($parent_id, "axo");
    if (is_array($groups)) {
      foreach ($groups as $group) {
	$result[] = array('id' => $group['id'], 'type' => 'grp', 'name' => $group['name'], 'value' => $group['value']);
      }
    } else {
      dprint(__FILE__, __LINE__, 1, 'No groups available for ' . $parent_id);
    }
    // Now the individual modules.
    $modlist = $this->get_objects_full("app", 0, "axo");
    if (is_array($modlist)) {
      foreach ($modlist as $mod) {
	$result[] = array('id' => $mod['id'], 'type' => 'mod', 'name' => $mod['name'], 'value' => $mod['value']);
      }
    }
    return $result;
  }

  /**
   * An assignable module is one where there is a module sub-group
   * Effectivly we just list those module in the section "modname"
   */ 
  function getAssignableModules()
  {
    return $this->get_object_sections(null, 0, 'axo', "value not in ('sys', 'app')");
  }

  function getPermissionList()
  {
    $list = $this->get_objects_full("application", 0, "aco");
    // We only need the id and the name
    $result = array();
    if (! is_array($list))
      return $result;
    foreach ($list as $perm)
      $result[$perm['id']] = $perm['name'];
    return $result;
  }

  function get_group_map($id, $group_type = 'ARO')
  {
		$this->debug_text("get_group_map(): Assigned ID: $id Group Type: $group_type");
	
		switch (strtolower(trim($group_type))) {
			case 'axo':
				$group_type = 'axo';
				$table = $this->_db_table_prefix .'axo_groups';
				$map_table = $this->_db_table_prefix . 'groups_axo_map';
				$map_field = "axo_id";
				break;
			default:
				$group_type = 'aro';
				$table = $this->_db_table_prefix .'aro_groups';
				$map_table = $this->_db_table_prefix . 'groups_aro_map';
				$map_field = "aro_id";
		}
	
		if (empty($id)) {
			$this->debug_text("get_group_map(): ID ($id) is empty, this is required");
			return FALSE;
		}
	
		$q = new DBQuery;
		$q->addTable($table, 'g1');
		$q->addTable( $map_table, 'g2');
		$q->addQuery('g1.id, g1.name, g1.value, g1.parent_id');
		$q->addWhere("g1.id = g2.group_id AND g2.$map_field = $id");
		$q->addOrder('g1.value');
	
		$result = array();
		$q->exec();
		while ($row = $q->fetchRow()) {
				$result[] = array(
				 'id' => $row[0],
				 'name' => $row[1],
				 'value' => $row[2],
				 'parent_id' => $row[3]);
		}
		$q->clear();
		
		return $result;
  }

/**
 * Function:	get_object()
 */
	function get_object_full($value = null , $section_value = null, $return_hidden=1, $object_type = null)
	{

		switch(strtolower(trim($object_type))) {
			case 'aco':
				$object_type = 'aco';
				$table = $this->_db_table_prefix .'aco';
				break;
			case 'aro':
				$object_type = 'aro';
				$table = $this->_db_table_prefix .'aro';
				break;
			case 'axo':
				$object_type = 'axo';
				$table = $this->_db_table_prefix .'axo';
				break;
			case 'acl':
				$object_type = 'acl';
				$table = $this->_db_table_prefix .'acl';
				break;
			default:
				$this->debug_text('get_object(): Invalid Object Type: '. $object_type);
				return FALSE;
		}

		$this->debug_text("get_object(): Section Value: $section_value Object Type: $object_type");

		$q = new DBQuery;
		$q->addTable($table);
		$q->addQuery('id, section_value, name, value, order_value, hidden');
	
		if (!empty($value)) {
			$q->addWhere('value=' . $this->db->quote($value));

		}

		if (!empty($section_value)) {
			$q->addWhere('section_value='. $this->db->quote($section_value));

		}

		if ($return_hidden==0 AND $object_type != 'acl') {
			$q->addWhere('hidden=0');

		}


		$q->exec();
		$row = $q->fetchRow();
		$q->clear();

		if (!is_array($row)) {
			$this->debug_db('get_object');
			return false;
		}

		// Return Object info.
		return array(
		  'id' => $row[0],
		  'section_value' => $row[1],
		  'name' => $row[2],
		  'value' => $row[3],
		  'order_value' => $row[4],
		  'hidden' => $row[5]
		);
	}

	/**
	 * Function:	get_objects ()
	 * Purpose:	Grabs all Objects in the database, or specific to a section_value
	 * 		returns format suitable for add_acl and is_conflicting_acl
	 */
	function get_objects_full($section_value = null, $return_hidden = 1, $object_type = null, $limit_clause = null)
	{
		switch (strtolower(trim($object_type))) {
			case 'aco':
				$object_type = 'aco';
				$table = $this->_db_table_prefix .'aco';
				break;
			case 'aro':
				$object_type = 'aro';
				$table = $this->_db_table_prefix .'aro';
				break;
			case 'axo':
				$object_type = 'axo';
				$table = $this->_db_table_prefix .'axo';
				break;
			default:
				$this->debug_text('get_objects(): Invalid Object Type: '. $object_type);
				return FALSE;
		}

		$this->debug_text("get_objects(): Section Value: $section_value Object Type: $object_type");

		$q = new DBQuery;
		$q->addTable($table);
		$q->addQuery('id, section_value, name, value, order_value, hidden');

		if (!empty($section_value)) {
			$q->addWhere('section_value='. $this->db->quote($section_value));
		}

		if ($return_hidden==0) {
			$q->addWhere('hidden=0');
		}

		if (!empty($limit_clause)) {
			$q->addWhere($limit_clause);
		}

		$q->addOrder('order_value');

		/*
		$rs = $q->exec();

		if (!is_object($rs)) {
			$this->debug_db('get_objects');
			return FALSE;
		}
		*/

		$retarr = array();

		$q->exec();
		while ($row = $q->fetchRow()) {
			$retarr[] = array(
			  'id' => $row[0],
			  'section_value' => $row[1],
			  'name' => $row[2],
			  'value' => $row[3],
			  'order_value' => $row[4],
			  'hidden' => $row[5]
			);
		}
		$q->clear();

		// Return objects
		return $retarr;
	}

	function get_object_sections($section_value = null, $return_hidden = 1, $object_type = null, $limit_clause = null)
	{
		switch (strtolower(trim($object_type))) {
			case 'aco':
				$object_type = 'aco';
				$table = $this->_db_table_prefix .'aco_sections';
				break;
			case 'aro':
				$object_type = 'aro';
				$table = $this->_db_table_prefix .'aro_sections';
				break;
			case 'axo':
				$object_type = 'axo';
				$table = $this->_db_table_prefix .'axo_sections';
				break;
			default:
				$this->debug_text('get_object_sections(): Invalid Object Type: '. $object_type);
				return FALSE;
		}

		$this->debug_text("get_objects(): Section Value: $section_value Object Type: $object_type");

		// $query = 'SELECT id, value, name, order_value, hidden FROM '. $table;
		$q = new DBQuery;
		$q->addTable($table);
		$q->addQuery('id, value, name, order_value, hidden');


		if (!empty($section_value)) {
			$q->addWhere('value='. $this->db->quote($section_value));

		}

		if ($return_hidden==0) {
			$q->addWhere('hidden=0');

		}

		if (!empty($limit_clause)) {
			$q->addWhere($limit_clause);

		}

		$q->addOrder('order_value');

		$rs = $q->exec();

		/*
		if (!is_object($rs)) {
			$this->debug_db('get_object_sections');
			return FALSE;
		}
		*/

		$retarr = array();

		while ($row = $q->fetchRow()) {
			$retarr[] = array(
			  'id' => $row[0],
			  'value' => $row[1],
			  'name' => $row[2],
			  'order_value' => $row[3],
			  'hidden' => $row[4]
			);
		}
		$q->clear();

		// Return objects
		return $retarr;
	}

  /** Called from do_perms_aed, allows us to add a new ACL */
  function addUserPermission()
  {
    // Need to have a user id, 
    // parse the permissions array
    if (! is_array($_POST['permission_type'])) {
      $this->debug_text("you must select at least one permission");
      return false;
    }
    /*
    echo "<pre>\n";
    var_dump($_POST);
    echo "</pre>\n";
    return true;
    */

    $mod_type = substr($_POST['permission_module'],0,4);
    $mod_id = substr($_POST['permission_module'],4);
    $mod_group = null;
    $mod_mod = null;
    if ($mod_type == 'grp,') {
      $mod_group = array($mod_id);
    } else {
      if (isset($_POST['permission_item']) && $_POST['permission_item']) {
	$mod_mod = array();
	$mod_mod[$_POST['permission_table']][] =  $_POST['permission_item'];
	// check if the item already exists, if not create it.
	// First need to check if the section exists.
	if (! $this->get_object_section_section_id(null, $_POST['permission_table'], 'axo')) {
	  $this->addModuleSection($_POST['permission_table']);
	}
	if (! $this->get_object_id($_POST['permission_table'], $_POST['permission_item'],  'axo')) {
	  $this->addModuleItem($_POST['permission_table'], $_POST['permission_item'], $_POST['permission_name']);
	}
      } else {
	// Get the module information
	$mod_info = $this->get_object_data($mod_id, 'axo');
	$mod_mod = array();
	$mod_mod[$mod_info[0][0]][] = $mod_info[0][1];
      }
    }
    $aro_info = $this->get_object_data($_POST['permission_user'], 'aro');
    $aro_map = array();
    $aro_map[$aro_info[0][0]][] = $aro_info[0][1];
    // Build the permissions info
    $type_map = array();
    foreach ($_POST['permission_type'] as $tid) {
      $type = $this->get_object_data($tid, 'aco');
      foreach ($type as $t) {
	$type_map[$t[0]][] = $t[1];
      }
    }
    return $this->add_acl(
      $type_map,
      $aro_map,
      null,
      $mod_mod,
      $mod_group,
      $_POST['permission_access'],
      1,
      null,
      null,
      "user");
  }

  function addRolePermission()
  {
    if (! is_array($_POST['permission_type'])) {
      $this->debug_text("you must select at least one permission");
      return false;
    }

    $mod_type = substr($_POST['permission_module'],0,4);
    $mod_id = substr($_POST['permission_module'],4);
    $mod_group = null;
    $mod_mod = null;
    if ($mod_type == 'grp,') {
      $mod_group = array($mod_id);
    } else {
	if (isset($_POST['permission_item']) && $_POST['permission_item']) {
	$mod_mod = array();
	$mod_mod[$_POST['permission_table']][] =  $_POST['permission_item'];
	// check if the item already exists, if not create it.
	// First need to check if the section exists.
	if (! $this->get_object_section_section_id(null, $_POST['permission_table'], 'axo')) {
	  $this->addModuleSection($_POST['permission_table']);
	}
	if (! $this->get_object_id($_POST['permission_table'], $_POST['permission_item'],  'axo')) {
	  $this->addModuleItem($_POST['permission_table'], $_POST['permission_item'], $_POST['permission_name']);
	}
      } else {
	// Get the module information
	$mod_info = $this->get_object_data($mod_id, 'axo');
	$mod_mod = array();
	$mod_mod[$mod_info[0][0]][] = $mod_info[0][1];
      }
    }
    $aro_map = array($_POST['role_id']);
    // Build the permissions info
    $type_map = array();
    foreach ($_POST['permission_type'] as $tid) {
      $type = $this->get_object_data($tid, 'aco');
      foreach ($type as $t) {
	$type_map[$t[0]][] = $t[1];
      }
    }
    return $this->add_acl(
      $type_map,	// permissions types
      null,
      $aro_map, 	// role_id
      $mod_mod,		// mod_id, mod_name
      $mod_group,	// null or mod_id
      $_POST['permission_access'], // allow or deny (allow by default)
      1,
      null,
      null,
      'user');
  }

  /**
   * Some function overrides.
   */ 
  function debug_text($text)
  {
    $this->_debug_msg = $text;
    dprint(__FILE__, __LINE__, 9, $text);
  }

  function msg()
  {
    return $this->_debug_msg;
  }
}
?>
