<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_settings_install () {
	api_plugin_register_hook('settings', 'config_settings', 'settings_config_settings', 'setup.php');
	api_plugin_register_realm('settings', 'email-test.php', 'Send Test Email', 1);
}

function plugin_settings_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_settings_check_config () {
	// Here we will check to ensure everything is configured
	settings_check_upgrade ();
	return true;
}

function plugin_settings_upgrade () {
	// Here we will upgrade to the newest version
	settings_check_upgrade ();
	return false;
}

function settings_version () {
	return plugin_settings_version();
}

function settings_check_upgrade () {
	$current = plugin_settings_version ();
	$current = $current['version'];
	$old = read_config_option('plugin_settings_version');
	if ($current != $old) {
		// settings_setup_table ();
	}
	db_execute("REPLACE INTO settings (name, value) VALUES ('plugin_settings_version', '$current')");
}

function plugin_settings_version () {
	return array(
			'name' 	=> 'settings',
			'version'  => '0.7',
			'longname' => 'Global Plugin Settings',
			'author'   => 'Jimmy Conner',
			'homepage' => 'http://cactiusers.org',
			'email'    => 'jimmy@sqmail.org',
			'url'      => 'http://versions.cactiusers.org/'
		);
}

function settings_config_settings () {
	global $tabs, $settings, $config;

	include_once($config['base_path'] . '/plugins/settings/include/functions.php');
	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['mail'] = 'Mail / DNS';

      $javascript = '<script type="text/javascript">
<!--
   function emailtest() {
      w = 420;
      h = 350;
      email = window.open("plugins/settings/email-test.php", "EmailTest", "width=" + w + ",height=" + h + ",resizable=0,status=0");
      email.moveTo((screen.width - w) /2 , (screen.height - h) /2 );
   }
//-->
</script>';

	$temp = array(
		"settings_email_header" => array(
			"friendly_name" => "\n$javascript\n<table width='99%' cellspacing=0 cellpadding=0 align=left><tr><td class='textSubHeaderDark'>Emailing Options</td><td align=right class='textSubHeaderDark'><a href='javascript:emailtest();' class='textSubHeaderDark'><font color=white>Send a Test Email</font></a></td></tr></table>",
			"method" => "spacer",
			),
		"settings_test_email" => array(
			"friendly_name" => "Test Email",
			"description" => "This is a email account used for sending a test message to ensure everything is working properly.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"settings_how" => array(
			"friendly_name" => "Mail Services",
			"description" => "Which mail service to use in order to send mail",
			"method" => "drop_array",
			"default" => "PHP Mail() Function",
			"array" => array("PHP Mail() Function", "Sendmail", "SMTP"),
			),
		"settings_from_email" => array(
			"friendly_name" => "From Email Address",
			"description" => "This is the email address that the email will appear from.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"settings_from_name" => array(
			"friendly_name" => "From Name",
			"description" => "This is the actual name that the email will appear from.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"settings_wordwrap" => array(
			"friendly_name" => "Word Wrap",
			"description" => "This is how many characters will be allowed before a line in the email is automatically word wrapped. (0 = Disabled)",
			"method" => "textbox",
			'default' => 120,
			"max_length" => 4,
			),
		"settings_sendmail_header" => array(
			"friendly_name" => "Sendmail Options",
			"method" => "spacer",
			),
		"settings_sendmail_path" => array(
			"friendly_name" => "Sendmail Path",
			"description" => "This is the path to sendmail on your server. (Only used if Sendmail is selected as the Mail Service)",
			"method" => "filepath",
			"max_length" => 255,
			"default" => "/usr/sbin/sendmail",
			),
		"settings_smtp_header" => array(
			"friendly_name" => "SMTP Options",
			"method" => "spacer",
			),
		"settings_smtp_host" => array(
			"friendly_name" => "SMTP Hostname",
			"description" => "This is the hostname/IP of the SMTP Server you will send the email to.",
			"method" => "textbox",
			"default" => "localhost",
			"max_length" => 255,
			),
		"settings_smtp_port" => array(
			"friendly_name" => "SMTP Port",
			"description" => "This is the port on the SMTP Server that SMTP uses.",
			"method" => "textbox",
			"max_length" => 255,
			"default" => 25,
			),
		"settings_smtp_username" => array(
			"friendly_name" => "SMTP Username",
			"description" => "This is the username to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)",
			"method" => "textbox",
			"max_length" => 255,
			),
		"settings_smtp_password" => array(
			"friendly_name" => "SMTP Password",
			"description" => "This is the password to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)",
			"method" => "textbox_password",
			"max_length" => 255,
			),
		"settings_dns_header" => array(
			"friendly_name" => "DNS Options",
			"method" => "spacer",
			),
		"settings_dns_primary" => array(
			"friendly_name" => "Primary DNS IP Address",
			"description" => "Enter the primary DNS IP Address to utilize for reverse lookups.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "30"
			),
		"settings_dns_secondary" => array(
			"friendly_name" => "Secondary DNS IP Address",
			"description" => "Enter the secondary DNS IP Address to utilize for reverse lookups.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "30"
			),
		"settings_dns_timeout" => array(
			"friendly_name" => "DNS Timeout",
			"description" => "Please enter the DNS timeout in milliseconds.  Cacti uses a PHP based DNS resolver.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "10"
			),
	);

	if (isset($settings['mail']))
		$settings['mail'] = array_merge($settings['mail'], $temp);
	else
		$settings['mail']=$temp;
}




