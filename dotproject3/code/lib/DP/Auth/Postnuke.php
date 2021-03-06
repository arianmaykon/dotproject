<?php
class DP_Auth_Postnuke extends DP_Auth_Sql
{
	private $fallback = null;

	public function __construct()
	{
		$this->fallback = DP_Config::getConfig('postnuke_allow_login', false);
	}

	public function displayName()
	{
		return 'PostNuke';
	}

	/**
	 * Should really only return true if we can find a PostNuke instance
	 */
	public function supported()
	{
		return true;
	}

	/**
	 * Todo: This is probably completely broken now.
	 */
	public function authenticate($username, $password)
	{
		$AppUI = DP_AppUI::getInstance();
		if (!isset($_REQUEST['userdata'])) { // fallback to SQL Authentication if PostNuke fails.
			if ($this->fallback) {
				return parent::authenticate($username, $password);
			} else {
				die($AppUI->_('You have not configured your PostNuke site correctly'));
			}
		}

		if (!$compressed_data = base64_decode(urldecode($_REQUEST['userdata']))) {
			die($AppUI->_('The credentials supplied were missing or corrupted') . ' (1)');
		}
		if (!$userdata = gzuncompress($compressed_data)) {
			die($AppUI->_('The credentials supplied were missing or corrupted') . ' (2)');
		}
		if (!$_REQUEST['check'] = md5($userdata)) {
			die($AppUI->_('The credentials supplied were issing or corrupted') . ' (3)');
		}
		$user_data = unserialize($userdata);

		// Now we need to check if the user already exists, if so we just
		// update.  If not we need to create a new user and add a default
		// role.
		$username = trim($user_data['login']);
		$this->username = $username;
		$names = explode(' ', trim($user_data['name']));
		$last_name = array_pop($names);
		$first_name = implode(' ', $names);
		$passwd = trim($user_data['passwd']);
		$email = trim($user_data['email']);
		
		$q = new DP_Query();
		$q->addTable('users');
		$q->addQuery('user_id, user_password, user_contact');
		$q->addWhere("user_username = '$username'");
		if (!$rs = $q->exec()) {
			die($AppUI->_('Failed to get user details') . ' - error was ' . $db->ErrorMsg());
		}
		if ($rs->rowCount() < 1) {
			$q->clear();
			$this->createsqluser($username, $passwd, $email, $first_name, $last_name);
		} else {
			if (! $row = $rs->fetch()) {
				die($AppUI->_('Failed to retrieve user detail'));
			}
			// User exists, update the user details.
			$this->user_id = $row['user_id'];
			$q->clear();
			$q->addTable('users');
			$q->addUpdate('user_password', $passwd);
			$q->addWhere("user_id = {$this->user_id}");
			if (!$q->exec()) {
				die($AppUI->_('Could not update user credentials'));
			}
			$q->clear();
			$q->addTable('contacts');
			$q->addUpdate('contact_first_name', $first_name);
			$q->addUpdate('contact_last_name', $last_name);
			$q->addUpdate('contact_email', $email);
			$q->addWhere("contact_id = {$row['user_contact']}");
			if (!$q->exec()) {
				die($AppUI->_('Could not update user details'));
			}
			$q->clear();
		}
		return true;
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $email
	 * @param string $first
	 * @param string $last
	 * 
	 * @return void Does not return anything
	 */
	function createsqluser($username, $password, $email, $first, $last)
	{
		$AppUI = DP_AppUI::getInstance();
		require_once $AppUI->getModuleClass("contacts");

		$c = new CContact();
		$c->contact_first_name = $first;
		$c->contact_last_name = $last;
		$c->contact_email = $email;
		$c->contact_order_by = "$last, $first";

		db_insertObject('contacts', $c, 'contact_id');
		$contact_id = ($c->contact_id == NULL) ? "NULL" : $c->contact_id;
		if (!$c->contact_id) {
			die($AppUI->_('Failed to create user details'));
		}
		$q  = new DP_Query();
		$q->addTable('users');
		$q->addInsert('user_username',$username );
		$q->addInsert('user_password', $password);
		$q->addInsert('user_type', '1');
		$q->addInsert('user_contact', $c->contact_id);
		if (!$q->exec()) {
			die($AppUI->_('Failed to create user credentials'));
		}
		$user_id = $db->Insert_ID();
		$this->user_id = $user_id;
		$q->clear();

		$acl =& $AppUI->acl();
		$acl->insertUserRole($acl->get_group_id('anon'), $this->user_id);
	}

}

?>
