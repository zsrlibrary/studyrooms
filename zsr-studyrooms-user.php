<?php

class ZSR_Study_Rooms_User
{
	public $username;
	private $display_name;

	function __construct()
	{
		require_once 'zsr-studyrooms-config.php';
		$this->config = new ZSR_Study_Rooms_Config();

		$this->init_sh();
		$this->set_username();
		$this->set_display_name();
	}

	/* --------------------------------------------------------------------- */

	private function init_sh()
	{
		$this->start();

		if(!empty($_SESSION['expires']) && $_SESSION['expires'] <= time())
		{
			$this->restart();
		}
	}

	private function set_username()
	{
		$this->username = false;

		if(!empty($_SESSION['username']))
		{
			$this->username = $_SESSION['username'];
		}
	}

	private function set_display_name()
	{
		$this->display_name = array();
		
		if(!empty($_SESSION['username']))
		{
			$ldap = ldap_connect($this->config->ldap['host'],$this->config->ldap['port']);
			if($ldap)
			{
				$ldap_bind = ldap_bind($ldap);
				$ldap_search = ldap_search($ldap,$this->config->ldap['dn'],'uid='.$_SESSION['username']);
				$info = ldap_get_entries($ldap,$ldap_search);
				ldap_close($ldap);
			}
			if(!empty($info['count']))
			{
				for($i = 0; $i < $info['count']; $i++)
				{
					$firstname = $info[$i]['givenname'][0];
					$lastname = $info[$i]['sn'][0];

					$this->display_name['fullname'] = $firstname.' '.$lastname;
					$this->display_name['firstname'] = $firstname;
					$this->display_name['lastname'] = $lastname;
				}
			}
		}
	}

	/* --------------------------------------------------------------------- */

	public function bind_login($username,$password)
	{
		return $this->ldap_login($username,$password);
	}

	public function is_login($username,$password)
	{
		if(!empty($username) && $username == $this->username)
		{
			return true;
		}
		if($this->bind_login($username,$password))
		{
			return true;
		}

		return false;
	}

	public function is_logged_in()
	{
		return !empty($this->username);
	}

	public function clear_login()
	{
		if(!empty($_SESSION['username']))
		{
			$this->restart();
		}
		$this->username = false;
		$this->display_name = array();

		return true;
	}

	public function save_login($username)
	{
		if(!empty($username))
		{
			$this->username = $username;

			$_SESSION['username'] = $username;
			$_SESSION['expires'] = $this->is_extended() ? time()+$this->config->session['extended'] : time()+$this->config->session['default'];

			$this->set_display_name();
		}
	}

	public function is_admin()
	{
		return in_array($this->username,$this->config->users['admin']);
	}

	public function is_extended()
	{
		return in_array($this->username,$this->config->users['extended']) || $this->is_admin();
	}

	public function is_blocked($username)
	{
		return in_array($username,$this->config->users['blocked']);
	}

	public function get_display_name($type)
	{
		$name = '';

		if(!empty($this->display_name[$type]))
		{
			$name = $this->display_name[$type];
		}

		return $name;
	}

	/* --------------------------------------------------------------------- */

	private function start()
	{
		session_name('zsr_studyrooms');
		session_start();
	}

	private function restart()
	{
		session_unset();
		session_destroy();
		$this->start();
	}

	private function ldap_login($u,$p)
	{
		if(!empty($u) && !empty($p) && !preg_match('/\x00/',$p))
		{
			$ldap = ldap_connect($this->config->ldap['host'],$this->config->ldap['port']);
			if($ldap)
			{
				$dn = 'uid='.$u.','.$this->config->ldap['dn'];
				$ldap_bind = @ldap_bind($ldap,$dn,$p);
				ldap_close($ldap);
				if($ldap_bind)
				{
					return true;
				}
			}
		}
		return false;
	}
}