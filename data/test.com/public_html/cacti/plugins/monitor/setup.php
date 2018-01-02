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

function plugin_monitor_install () {
	api_plugin_register_hook('monitor', 'top_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'draw_navigation_text', 'monitor_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('monitor', 'config_form', 'monitor_config_form', 'setup.php');
	api_plugin_register_hook('monitor', 'api_device_save', 'monitor_api_device_save', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_refresh', 'monitor_top_graph_refresh', 'setup.php');
	api_plugin_register_hook('monitor', 'config_settings', 'monitor_config_settings', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_array', 'monitor_device_action_array', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_execute', 'monitor_device_action_execute', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_prepare', 'monitor_device_action_prepare', 'setup.php');

	api_plugin_register_realm('monitor', 'monitor.php', 'View Monitoring', 1);
}

function plugin_monitor_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_monitor_check_config () {
	global $config;
	// Here we will check to ensure everything is configured
	monitor_check_upgrade ();

	include_once($config['library_path'] . '/database.php');
	$r = read_config_option('monitor_refresh');
	$result = db_fetch_assoc("SELECT * FROM settings WHERE name='monitor_refresh'");
	if (!isset($result[0]['name']))
		$r = NULL;
	if ($r == '' or $r < 1 or $r > 300) {
		if ($r == '')
			$sql = "REPLACE INTO settings VALUES ('monitor_refresh','300')";
		else if ($r == NULL)
			$sql = "INSERT INTO settings VALUES ('monitor_refresh','300')";
		else
			$sql = "UPDATE settings SET value = '300' WHERE name = 'monitor_refresh'";
		$result = db_execute($sql);
		kill_session_var('sess_config_array');
	}

	$r = read_config_option('monitor_width');
	$result = db_fetch_assoc("SELECT * FROM settings WHERE name='monitor_width'");
	if (!isset($result[0]['name']))
		$r = NULL;
	if ($r == '' or $r < 1 or $r > 20) {
		if ($r == '')
			$sql = "REPLACE INTO settings VALUES ('monitor_width','10')";
		else if ($r == NULL)
			$sql = "INSERT INTO settings VALUES ('monitor_width','10')";
		else
			$sql = "UPDATE settings SET value = '10' WHERE name = 'monitor_width'";
		$result = db_execute($sql) or die (mysql_error());
		kill_session_var('sess_config_array');
	}

	return true;
}

function plugin_monitor_upgrade () {
	// Here we will upgrade to the newest version
	monitor_check_upgrade ();
	return false;
}

function monitor_version () {
	return plugin_monitor_version();
}

function monitor_check_upgrade () {
	$current = plugin_monitor_version ();
	$current = $current['version'];
	$old = read_config_option('plugin_monitor_version');
	if ($current != $old)
		monitor_setup_table ();
	// Set the new version
	db_execute("REPLACE INTO settings (name, value) VALUES ('plugin_monitor_version', '$current')");
}

function plugin_monitor_version () {
	return array(
		'name'     => 'monitor',
		'version'  => '1.2',
		'longname' => 'Device Monitoring',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function monitor_device_action_execute ($action) {
	global $config;

	if ($action != 'monitor_enable' && $action != 'monitor_disable')
		return $action;

	$selected_items = unserialize(stripslashes($_POST["selected_items"]));

	for ($i = 0; ($i < count($selected_items)); $i++) {
		/* ================= input validation ================= */
		input_validate_input_number($selected_items[$i]);
		/* ==================================================== */
		if ($action == 'monitor_enable') {
			db_execute("update host set monitor='on' where id='" . $selected_items[$i] . "'");
		}else if ($action == 'monitor_disable') {
			db_execute("update host set monitor='' where id='" . $selected_items[$i] . "'");
		}
	}
	return;
}

function monitor_device_action_prepare($save) {
	global $colors, $host_list;
	$action = $save['drp_action'];
	if ($action != 'monitor_enable' && $action != 'monitor_disable')
		return $save;
	if ($action == "monitor_enable") {
		$action_description = "enable";
	} else if ($action == "monitor_disable") {
		$action_description = "disable";
	}

	print "	<tr>
			<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>To ". $action_description ." monitoring on these hosts, press the \"yes\" button below.</p>
				<p>" . $save['host_list'] . "</p>
			</td>
			</tr>";
}

function monitor_device_action_array($device_action_array) {
	$device_action_array['monitor_enable'] = 'Enable Monitoring';
	$device_action_array['monitor_disable'] = 'Disable Monitoring';
	return $device_action_array;
}

function monitor_scan_dir () {
	global $config;

	$ext = array('.wav', '.mp3');
	$d = dir($config['base_path'] . '/plugins/monitor/sounds/');
	$files = array('None' => 'None');
	while (false !== ($entry = $d->read())) {
		if ($entry != '.' && $entry != '..' && in_array(strtolower(substr($entry,-4)),$ext))
			$files[$entry] = $entry;

	}
	$d->close();
	return $files;
}

function monitor_config_settings () {
	global $tabs, $settings;

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['misc'] = 'Misc';

	$temp = array(
		'monitor_header' => array(
			'friendly_name' => 'Monitor',
			'method' => 'spacer',
			),
		'monitor_sound' => array(
			'friendly_name' => 'Alarm Sound',
			'description' => 'This is the sound file that will be played when a host is down.',
			'method' => 'drop_array',
			'array' => monitor_scan_dir(),
			'default' => 'attn-noc.wav',
			),
		'monitor_refresh' => array(
			'friendly_name' => 'Refresh Interval',
			'description' => 'This is the time in seconds before the page refreshes.  (1 - 300)',
			'method' => 'textbox',
			'max_length' => 3,
			),
		'monitor_width' => array(
			'friendly_name' => 'Icon Spacing',
			'description' => 'This is how many icons to show per line.  (1 - 20)',
			'method' => 'textbox',
			'max_length' => 3,
			),
		'monitor_legend' => array(
			'friendly_name' => 'Show Icon Legend',
			'description' => 'Check this to show an icon legend on the Monitor display',
			'method' => 'checkbox',
			),
		"monitor_grouping" => array(
			"friendly_name" => "Grouping",
			"description" => "This is how monitor will group hosts.",
			"method" => "drop_array",
			"array" => array(
				"default" => "Default",
				"default_by_permissions" => "Default with permissions",
				"group_by_tree" => "Tree",
				"group_by_device_template" => "Device Template",
				),
			"default" => "Default",
			),
		"monitor_view" => array(
			"friendly_name" => "View",
			"description" => "This is how monitor will render hosts.",
			"method" => "drop_array",
			"array" => array(
				"default" => "Default",
				"tiles" => "Tiles",
				"list" => "List",
				"color_blocks" => "Colored blocks",
				"simple" => "Simple"
				),
			"default" => "Default",
			),

	);

	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function monitor_top_graph_refresh ($refresh) {
	if (basename($_SERVER['PHP_SELF']) != 'monitor.php')
		return $refresh;
	$r = read_config_option('monitor_refresh');
	if ($r == '' or $r < 1)
		return $refresh;
	return $r;
}

function monitor_show_tab () {
	global $config;
	if (api_user_realm_auth('monitor.php')) {
		if (substr_count($_SERVER["REQUEST_URI"], "monitor.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor_down.gif" alt="monitor" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor.gif" alt="monitor" align="absmiddle" border="0"></a>';
		}
	}
	monitor_setup_table();
}

function monitor_config_form () {
	global $fields_host_edit;
	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['monitor'] = array(
				'method' => 'checkbox',
				'friendly_name' => 'Monitor Host',
				'description' => 'Check this box to monitor this host on the Monitor Tab.',
				'value' => '|arg1:monitor|',
				'default' => '',
				'form_id' => false
			);
			$fields_host_edit3['monitor_text'] = array(
				'friendly_name' => 'Down Host Message',
				'description' => 'This is the message that will be displayed when this host is reported as down.',
				'method' => 'textarea',
				'max_length' => 1000,
				'textarea_rows' => 3,
				'textarea_cols' => 30,
				'value' => '|arg1:monitor_text|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function monitor_api_device_save ($save) {
	if (isset($_POST['monitor']))
		$save['monitor'] = form_input_validate($_POST['monitor'], 'monitor', '', true, 3);
	else
		$save['monitor'] = form_input_validate('', 'monitor', '', true, 3);
	if (isset($_POST['monitor_text']))
		$save['monitor_text'] = form_input_validate($_POST['monitor_text'], 'monitor_text', '', true, 3);
	else
		$save['monitor_text'] = form_input_validate('', 'monitor_text', '', true, 3);
	return $save;
}

function monitor_draw_navigation_text ($nav) {
   $nav['monitor.php:'] = array('title' => 'Monitoring', 'mapping' => '', 'url' => 'monitor.php', 'level' => '1');
   return $nav;
}

function monitor_setup_table () {
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor', 'type' => 'char(3)', 'NULL' => false, 'default' => 'on', 'after' => 'disabled'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_text', 'type' => 'text', 'NULL' => false, 'after' => 'monitor'));
}

