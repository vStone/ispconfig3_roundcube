<?php
/**
* ISPConfig 3 Autoselect Host
*
* Make use of the ISPConfig 3 remote library to select the corresponding Host
*
* @author Horst Fickel ( web-wack.at )
*/

class ispconfig3_autoselect extends rcube_plugin
{
	public $task = 'login|mail|logout';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->load_config();
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));
									
		$this->add_hook('startup', array($this, 'startup'));
		$this->add_hook('authenticate', array($this, 'authenticate'));
		$this->add_hook('template_object_loginform', array($this, 'template_object_loginform'));
	}
	
	function load_config()
	{
		$config_1 = $this->home.'/config/config.inc.php';
		$config_2 = "plugins/ispconfig3_account/config/config.inc.php";
		if(file_exists($config_1))
		{
			if(!$this->rcmail_inst->config->load_from_file($config_1))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config_2))
		{
			if(!$this->rcmail_inst->config->load_from_file($config_2))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config_1 . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config_1 . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config_2 . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config_2 . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
	}

	function startup($args)
	{
		if (empty($args['action']) && empty($_SESSION['user_id']) && !empty($_POST['_user']) && !empty($_POST['_pass']))
			$args['action'] = 'login';
			
		return $args;
	}

	function template_object_loginform($args)
	{
		$args['content'] = substr($args['content'], 0, 545).substr($args['content'], 701);

		return $args;
	}

	function authenticate($args)
	{
		if(isset($_POST['_user']) && isset($_POST['_pass']))  
			$args['host'] = $this->getHost(get_input_value('_user', RCUBE_INPUT_POST));

		return $args;
	}

	function getHost($user)
	{
		$host = '';

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $user));  

			if(count($mail_user) == 1)
			{
				$mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'server');
				$host = $mail_server['hostname'];
			}

			$this->soap->logout($session_id);
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		return $host;
	}
}
?>