<?php

class ZSR_Study_Rooms_Mailer
{
	private $config;
	private $phpmailer;

	function __construct()
	{
		require_once 'zsr-studyrooms-config.php';
		$this->config = new ZSR_Study_Rooms_Config();
	}

	private function smtp()
	{
		if($this->config->use_phpmailer && !is_object($this->phpmailer))
		{
			require_once $this->config->inc_phpmailer;

			$this->phpmailer = new PHPMailer();
			$this->phpmailer->From = $this->config->admin_email;
			$this->phpmailer->FromName = $this->config->admin_name;
			$this->phpmailer->isSMTP();
			$this->phpmailer->Host = $this->config->smtp['host'];
			$this->phpmailer->SMTPAuth = $this->config->smtp['auth'];
			$this->phpmailer->SMTPSecure = $this->config->smtp['secure'];
			$this->phpmailer->Port = $this->config->smtp['port'];
			$this->phpmailer->Username = $this->config->smtp['username'];
			$this->phpmailer->Password = $this->config->smtp['password'];
			$this->phpmailer->isHTML(true);
		}
	}

	public function dispatch($to,$subject,$body,$headers)
	{
		$this->smtp();

		if(is_object($this->phpmailer))
		{
			$this->phpmailer->ClearAllRecipients();
			$this->phpmailer->AddAddress($to);
			$this->phpmailer->Subject = $subject;
			$this->phpmailer->Body = $body;
			$this->phpmailer->send();
		}
		else
		{
			mail($to,$subject,$body,$headers);
		}
	}

	public function get_carrier_domain($key)
	{
		$carrier_domain = '';

		foreach($this->config->carriers as $name => $domain)
		{
			$carrier_key = strtolower($name);
			$carrier_key = preg_replace('/[^a-z]/','',$carrier_key);

			if($key == $carrier_key)
			{
				$carrier_domain = $domain;
			}
		}

		return $carrier_domain;
	}
}