<?php

class ispconfig3_fetchmail extends rcube_plugin
{
	public $task = 'settings';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->load_config();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));

		$this->register_action('plugin.ispconfig3_fetchmail', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_fetchmail.save', array($this, 'save'));
		$this->register_action('plugin.ispconfig3_fetchmail.del', array($this, 'del'));
		
		$this->api->output->add_handler('fetchmail_form', array($this, 'gen_form'));
		$this->api->output->add_handler('fetchmail_table', array($this, 'gen_table'));
		$this->api->output->add_handler('sectionname_fetchmail', array($this, 'prefs_section_name'));
		
		$this->include_script('fetchmail.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('acc_fetchmail')); 
		$this->rcmail_inst->output->send('ispconfig3_fetchmail.fetchmail');
	}
	
	function load_config()
	{
		$config = $this->home.'/config/config.inc.php';
		if(file_exists($config))
		{
			if(!$this->rcmail_inst->config->load_from_file($config))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
	}

	function prefs_section_name()
	{
		return $this->gettext('acc_fetchmail');
	}

	function del()
	{
		$id     = get_input_value('_id', RCUBE_INPUT_GET);

		if ($id != 0 || $id != '')
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);
				
				if ($mail_fetchmail['destination'] == $this->rcmail_inst->user->data['username'])
				{
					$delete = $this->soap->mail_fetchmail_delete($session_id, $id);
					
					$this->rcmail_inst->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
				}
				
				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		}
	}

	function save()
	{ 
		$id     = get_input_value('_id', RCUBE_INPUT_POST);
		$serverid     = get_input_value('_serverid', RCUBE_INPUT_POST);
		$destination        = $this->rcmail_inst->user->data['username'];
		$typ     = get_input_value('_fetchmailtyp', RCUBE_INPUT_POST);
		$server     = get_input_value('_fetchmailserver', RCUBE_INPUT_POST);
		$user     = get_input_value('_fetchmailuser', RCUBE_INPUT_POST);
		$pass     = get_input_value('_fetchmailpass', RCUBE_INPUT_POST);
		$delete     = get_input_value('_fetchmaildelete', RCUBE_INPUT_POST);
		$enabled     = get_input_value('_fetchmailenabled', RCUBE_INPUT_POST);

		if(!$delete)
			$delete = 'n';
		else
			$delete = 'y';

		if(!$enabled)
			$enabled = 'n';
		else
			$enabled = 'y';

		if($id == 0 || $id == '')
		{
			$limit = $this->rcmail_inst->config->get('fetchmail_limit');

			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				
				$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				
				if(count($mail_fetchmail) < $limit)
				{
					$params = array('server_id' => $mail_user[0]['server_id'],
									'type' => $typ,
									'source_server' => $server,
									'source_username' => $user,
									'source_password' => $pass,							
									'source_delete' => $delete,
									'destination' => $destination,
									'active' => $enabled);
									
					$add = $this->soap->mail_fetchmail_add($session_id, $mail_user[0]['server_id'], $params);
					
					$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
				}
				else
					$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('fetchmaillimitreached'), 'error');
				
				$this->soap->logout($session_id);

			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		}
		else
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);
				
				if ($mail_fetchmail['destination'] == $destination)
				{
					$params = array('server_id' => $serverid,
									'type' => $typ,
									'source_server' => $server,
									'source_username' => $user,
									'source_password' => $pass,							
									'source_delete' => $delete,
									'destination' => $destination,
									'active' => $enabled);
									
					$uid = $this->soap->client_get_id($session_id, $mail_fetchmail['sys_userid']);
					$add = $this->soap->mail_fetchmail_update($session_id, $uid, $id, $params);
					
					$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
				}
				else
					$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');
				
				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		}
		
		$this->init_html();
	}

	function gen_form()
	{
		$id = get_input_value('_id', RCUBE_INPUT_GET);

		$this->rcmail_inst->output->add_label('ispconfig3_fetchmail.fetchmaildelconfirm',
												'ispconfig3_fetchmail.textempty');
									 
		if ($id != '' || $id != 0)
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);
				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}

			$enabled = $mail_fetchmail['active'];
			$delete = $mail_fetchmail['source_delete'];

			if ($mail_fetchmail['destination'] != $this->rcmail_inst->user->data['username'])
			{
				$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');	

				$enabled = 'n';
				$delete = 'n';
				$mail_fetchmail['mailget_id'] = '';
				$mail_fetchmail['server_id'] = '';
				$mail_fetchmail['type'] = '';
				$mail_fetchmail['source_server'] = '';
				$mail_fetchmail['source_username'] = '';
				$mail_fetchmail['source_delete'] = '';
			}
		}

		if ($delete == 'y')
			$delete = 1;
		else
			$delete = 0;

		if ($enabled == 'y')
			$enabled = 1;
		else
			$enabled = 0;

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$out .= '<fieldset><legend>' . $this->gettext('acc_fetchmail') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $mail_fetchmail['mailget_id']));
		$out .= $hidden_id->show();

		$hidden_serverid = new html_hiddenfield(array('name' => '_serverid', 'value' => $mail_fetchmail['server_id']));
		$out .= $hidden_serverid->show();

		$field_id = 'fetchmailtyp';
		$input_fetchmailtyp = new html_select(array('name' => '_fetchmailtyp', 'id' => $field_id));
		$input_fetchmailtyp->add(array('POP3','IMAP','POP3 SSL','IMAP SSL'), array('pop3','imap','pop3ssl','imapssl'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('fetchmailtyp')),
						$input_fetchmailtyp->show($mail_fetchmail['type']));

		$field_id = 'fetchmailserver';
		$input_fetchmailserver = new html_inputfield(array('name' => '_fetchmailserver', 'id' => $field_id, 'maxlength' => 320, 'size' => 40));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('fetchmailserver')),
						$input_fetchmailserver->show($mail_fetchmail['source_server']));

		$field_id = 'fetchmailuser';
		$input_fetchmailuser = new html_inputfield(array('name' => '_fetchmailuser', 'id' => $field_id, 'maxlength' => 320, 'size' => 40));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('username')),
						$input_fetchmailuser->show($mail_fetchmail['source_username']));

		$field_id = 'fetchmailpass';
		$input_fetchmailpass = new html_passwordfield(array('name' => '_fetchmailpass', 'id' => $field_id, 'maxlength' => 320, 'size' => 40, 'autocomplete' => 'off'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('password')),
						$input_fetchmailpass->show($mail_fetchmail['source_password']));

		$field_id = 'fetchmaildelete';
		$input_fetchmaildelete = new html_checkbox(array('name' => '_fetchmaildelete', 'id' => $field_id, 'value' => '1'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('fetchmaildelete')),
						$input_fetchmaildelete->show($delete));

		$field_id = 'fetchmailenabled';
		$input_fetchmailenabled = new html_checkbox(array('name' => '_fetchmailenabled', 'id' => $field_id, 'value' => '1'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('fetchmailenabled')),
						$input_fetchmailenabled->show($enabled));                                                

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n";    

		$this->rcmail_inst->output->add_gui_object('fetchmailform', 'fetchmail-form');

		return $out;
	}

	function gen_table($attrib)
	{
		$this->rcmail_inst->output->set_env('framed', true);

		$out = '<fieldset><legend>'.$this->gettext('fetchmail_entries').' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";

		$fetch_table = new html_table(array('id' => 'fetch-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$fetch_table->add_header(array('width' => '388px'), $this->gettext('fetchmailserver'));
		$fetch_table->add_header(array('width' => '16px'), '');
		$fetch_table->add_header(array('width' => '16px'), '');

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$fetchmail = $this->soap->mail_fetchmail_get($session_id, array('destination' => $this->rcmail_inst->user->data['username']));
			$this->soap->logout($session_id);

			for ( $i = 0; $i < count($fetchmail); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );

				if($fetchmail[$i]['mailget_id'] == get_input_value('_id', RCUBE_INPUT_GET))
					$class = 'selected';

				$fetch_table->set_row_attribs(array('class' => $class,'id' => 'fetch_'.$fetchmail[$i]['mailget_id']));
				$this->_fetch_row($fetch_table,$fetchmail[$i]['source_username'].'@'.$fetchmail[$i]['source_server'],$fetchmail[$i]['active'],$fetchmail[$i]['mailget_id'],$attrib);
			}
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
		
		if(count($fetchmail) == 0)
		{
			$fetch_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('nofetch')));
			$fetch_table->set_row_attribs(array('class' => 'odd'));
			$fetch_table->add_row();
		}

		$out .= "<div id=\"fetch-cont\">".$fetch_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";
		
		return $out;
	}

	private function _fetch_row($fetch_table,$name,$active,$id,$attrib)
	{
		$fetch_table->add(array('class' => 'fetch','onclick' => 'fetchmail_edit('.$id.');'), $name);

		$enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
		$disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));

		if($active == 'y')
			$status_button = $enable_button;
		else
			$status_button = $disable_button;

		$fetch_table->add(array('class' => 'control'), '&nbsp;'.$status_button);

		$del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_fetchmail.del', 'prop' => $id, 'type' => 'image',
														'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'),
														'title' => $this->gettext('delete')));
		
		$fetch_table->add(array('class' => 'control'), $del_button);

		return $fetch_table;
	}
}
?>