if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		$('#autoreplystarton').datetime(
		{
			chainTo: '#autoreplyendby',
		});
		rcmail.register_command('plugin.ispconfig3_autoreply.save', function()
		{ 
			var input_text = rcube_find_object('_autoreplybody');

			if(input_text.value == "")
			{
				parent.rcmail.display_message(rcmail.gettext('textempty','ispconfig3_autoreply'), 'error');    
				input_text.focus();    
			}
			else
			{
				document.forms.autoreplyform.submit();
			}
		}, true);
	})
}