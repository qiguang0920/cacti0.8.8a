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

$guest_account = true;

define('CHARS_PER_TIER', 3);

chdir('../../');
include_once('./include/auth.php');

/* Record Start Time */
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;

$sound = true;
// Check to see if we just turned on/off the sound via the button
if (isset($_POST['sound'])) {
	if ($_POST['sound'] == 'off') {
		$_SESSION['sound'] = 'off';
		$sound = false;
	} else {
		$_SESSION['sound'] = 'on';
		$sound = true;
	}
}

// Check to see if we turned off the sound before
if (isset($_SESSION['sound']) && $_SESSION['sound'] == 'off' && $_SESSION['sound'] != '') {
	$sound = false;
}

// Check to see if a host is down
$host_down = false;
$dhosts = array();
$chosts = array();
$chosts = get_host_down_by_permission();

if (sizeof($chosts)>0){
	$host_down = true;
}

$muted_hosts = array();
if (isset($_SESSION['muted_hosts'])) {
	$muted_hosts = explode(',',$_SESSION['muted_hosts']);
}

if (!$host_down) {
	$sound = true;
	$_SESSION['sound'] = 'on';
	$_SESSION['hosts_down'] = '';
	$_SESSION['muted_hosts'] = '';
} else {
	// Check the session to see if we had any down hosts before
	if (isset($_SESSION['hosts_down'])) {
		$dhosts = explode(',',$_SESSION['hosts_down']);
		$x = count($dhosts);
		$y = count($chosts);

		if (!$sound) {
			$muted_hosts = $dhosts;
			$_SESSION['muted_hosts'] = implode(',',$dhosts);
		}

		if ($x != $y && $x < $y) {
			// We have more down hosts than before
			$sound = true;
			$_SESSION['sound'] = 'on';
			$_SESSION['hosts_down'] = implode(',',$chosts);
		} elseif ($x > $y) {
			// We have less down hosts than before
			// Need to check here to make sure that one didn't come on line and others go off
			$_SESSION['hosts_down'] = implode(',',$chosts);
		} else {
			// We have the same number of hosts, so loop through and make sure they are the same ones
			// These arrays are already sorted, so we don't need to worry about doing a real compare
			for ($a = 0; $a < $x; $a++) {
				if ($dhosts[$a] != $chosts[$a]) {
					$sound = true;
					$_SESSION['sound'] = 'on';
					$_SESSION['hosts_down'] = implode(',',$chosts);
					break;
				}
			}
		}
	} else {
		$_SESSION['hosts_down'] = implode(',',$chosts);
	}
}
$_SESSION['custom']=false;

include_once('./plugins/monitor/general_header.php');
?>
<style>

a.info{
    position:relative; /*this is the key*/
    z-index:24;
    color:#000;
    text-decoration:none;
}
a.info:hover{
	z-index:25;
	background-color:#fff;
}
a.info span{
	display: none;
}
a.info:visited {
	color:#000;
}
a.info:hover span{
	display:block;
	position:absolute;
	border:1px solid #000;
	background-color:#ffffcc; color:#000;
	padding: 3px;
}
</style>

<?php
// Display the Current Time
print '<center>Last Refresh : ' . date('g:i:s a', time()) . '</center><br>';

// If the sound is on, and a host is down, show the button to silence it
if ($sound && $host_down)
	print "<center><form action='" . $config['url_path'] . "plugins/monitor/monitor.php' method=POST><input type=hidden name=sound value='off'><input type='submit' name='button_nosound_x' value='Mute'></center></form><br>";

if (!$sound && $host_down)
	print '<center><b>Alerting has been disabled by the client!</b></center><br>';

print "<center><table border=0 cellspacing=3>\n";

//if ($host_down) {
	/* Print our blink javascript only once */
	print "\n	<script><!--
		window.onerror = null;
		function blink_status(id,color,hilight){
			var hilight = (hilight == null) ? \"1\" : hilight;
			if(document.getElementById){
				var item = document.getElementById(id);
				if (item == null) {return;}
				if (hilight==1) {
					setTimeout(\"blink_status('\"+ id + \"','\" + color + \"','\" + 0 +\"')\", 1000);
					//item.style.background='#ffffff';
					//item.style.color='#000000';
					if ((item.tagName==\"A\") || (item.tagName==\"FONT\")) {
						item.style.background=color;
						item.style.color='#ffffff';
						return;
					}
					item.style.border='1px solid ' + color;
				}else{
					setTimeout(\"blink_status('\"+ id + \"','\" + color + \"','\" + 1 +\"')\", 1000);
					//item.style.background=color;
					//item.style.color='#ffffff';
					if ((item.tagName==\"A\") || (item.tagName==\"FONT\")) {
						item.style.color=color;
						item.style.background='none';
						return;
					}
					color='#aaaaaa';
					if (item.tagName==\"IMG\") {
						color='#ffffff';
					}
					item.style.border='1px solid ' + color;
				}
			}
		}
		//--></script>\n";
//}

if (in_array('thold',$plugins)) {
	$thold_alerts = array();
	$thold_hosts = array();

	$result = db_fetch_assoc('SELECT rra_id FROM thold_data WHERE thold_alert > 0 AND thold_enabled = "on"', FALSE);

	if (count($result)) {
		foreach ($result as $row) {
			$thold_alerts[] = $row['rra_id'];
		}
		if (count($thold_alerts) > 0) {
			$result = db_fetch_assoc('SELECT id, host_id FROM data_local');

			foreach ($result as $h) {
				if (in_array($h['id'], $thold_alerts)) {
					$thold_hosts[] = $h['host_id'];
				}
			}
		}
	}
}

$thold = (in_array('thold',$plugins) ? true : false);

// Default = default
// Default with permissions = default_by_permissions
// Tree  = group_by_tree
$render_style = read_config_option('monitor_grouping');
$function = "render_$render_style";
if (function_exists($function)) {
	print $function();
}else{
	print render_default();
}


print '</table></center>';

if ($host_down) {
	$render_down_host_message = 0;
	$down_host_message = '';
	$down_host_message .= '<br><br><center><h2>Down Host Messages</h2><table cellspacing=0 cellpadding=1 bgcolor=black><tr><td><table bgcolor=white width="100%">';
	foreach ($chosts as $id) {
		$message = db_fetch_row("select hostname, description, monitor_text from host where id=$id");
		$message['monitor_text'] = str_replace("\n", '<br>', $message['monitor_text']);

		if ($message['monitor_text'] != '') {
			$render_down_host_message = 1;
			$down_host_message .= '<tr><td><b>' . $message['description'] . ' (' . $message['hostname'] . ')</b> - </td><td>' . $message['monitor_text'] . '</td></tr>';
		}
	}
	$down_host_message .= '</table></td></tr></table></center>';
	if ($render_down_host_message) {
		print $down_host_message;
	}
}


$monitor_legend = read_config_option('monitor_legend');

if ($monitor_legend) {
	print "<br><br><br><center><table cellpadding=1 cellspacing=0 bgcolor='#000000'><tr><td>&nbsp;<font color='#FFFFFF'><b>Legend</b></font></td></tr><tr><td bgcolor='#000000'>\n";
	print "<table cellspacing=10 bgcolor='#FFFFFF' id=legend>\n";
	if ($thold) {
		print "<tr align=center><td><img src='" . $config['url_path'] . "plugins/monitor/images/green.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/blue.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/orange.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/red.gif'></td></tr>\n";
		print "<tr valign=top align=center><td width='25%'>Normal</td><td width='25%'>Recovering</td><td width='25%'>Threshold<br>Breached</td><td width='25%'>Down</td></tr>";
	} else {
		print "<tr align=center><td><img src='" . $config['url_path'] . "plugins/monitor/images/green.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/blue.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/red.gif'></td></tr>\n";
		print "<tr valign=top align=center><td width='33%'>Normal</td><td width='33%'>Recovering</td><td width='33%'>Down</td></tr>";
	}
	print "</table></td></tr></table></center>\n";
}

// If the host is down, we need to insert the embedded wav file
if ($host_down && $sound) {
	$monitor_sound = read_config_option('monitor_sound');
	if ($monitor_sound != '' && $monitor_sound != 'None') {
		print '<EMBED src="' . $config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound . '" autostart=true loop=true volume=100 hidden=true><NOEMBED><BGSOUND src="' . $config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound . '"></NOEMBED>' . "\n";
	}
}

print '</body></html>';

	//$debug = TRUE;
	/* Record Total Time */
	//if ($debug) {
		list($micro,$seconds) = split(" ", microtime());
		$end = $seconds + $micro;
		$total_time = $end - $start;
		//print "time taken: " . $total_time;
	//}

/* Render functions */
function render_default() {
	global $row_stripe;

	$result = '';
	$sql = "SELECT host.id, description, status, hostname, cur_time, status_fail_date, status_rec_date, availability FROM host where disabled = '' and monitor = 'on' ORDER BY description";
	$queryresult = mysql_query($sql) or die (mysql_error());

	$x = 0;
	$r = read_config_option('monitor_width');
	while ($row = mysql_fetch_array($queryresult, MYSQL_ASSOC)) {

		if ($x == 0) {
			// If we are the the beginning of a row, start the row
			$result .= '<tr>';
		}
		$result .= '<td valign=top>';
		$result .= render_host($row);
		$result .= '</td>';

		$x++;
		if ($x == $r) {
			// After so many items, end the row and start over
			$result .= '</tr>';
			$x = 0;
			if ($row_stripe) { $row_stripe = false; }else{ $row_stripe = true; }
		}
	}

	// If we didn't close the table row above, do so now
	if ($x != 0)
		$result .= '</tr>';

	return $result;
}


function render_default_by_permissions() {
	global $row_stripe;
	$result = "";
	$tree_hosts = get_host_tree_array('host_id > 0',true);
	$non_tree_hosts = get_host_non_tree_array();
	$heirarchy = array_merge ($tree_hosts, $non_tree_hosts);

	$x = 0;
	$r = read_config_option("monitor_width");
	if (sizeof($heirarchy) > 0) {
		foreach ($heirarchy as $leaf) {
			if ($x == 0) {
				// If we are the the beginning of a row, start the row
				$result .= '<tr>';
			}
			$result .= '<td valign=top>';
			$result .= render_host($leaf);
			$result .= "</td>";

			$x++;
			if ($x == $r) {
				// After so many items, end the row and start over
				$result .= '</tr>';
				$x = 0;
				if ($row_stripe) { $row_stripe = false; }else{ $row_stripe = true; }
			}
		}
	}

	// If we didn't close the table row above, do so now
	if ($x != 0)
		$result .= '</tr>';

	return $result;
}


function render_group_by_device_template() {
	$result = '';

	$heirarchy = get_host_template_array();
	$leafs = array();
	$branchleafs = 0;

	$current_tier = 0;
	$previous_tier = 0;

	$current_tree_name = "";
	$tree_names = array();

	for ($i = 0; $i < sizeof($heirarchy); $i++) {
		$leaf = $heirarchy[$i];

		$current_tier = $heirarchy[$i]["host_template_id"];
		$leaf["branch_name"] = $heirarchy[$i]["host_template_name"];
		if (array_key_exists("id",$leaf) && $leaf["id"] > 0) {
			if (sizeof($leafs) > 0 && ($previous_tier != $current_tier) ) {
				$result .= render_branch($leafs);
				/* start next branch */
				$leafs = array();
				if (isset($tree_names[$current_tier-1])) {
					$current_tree_name  = $tree_names[$current_tier-1];
				}else{
					$current_tree_name  = "";
				}
			}
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}elseif (array_key_exists("host_template_name",$leaf)) {
			$currentTitle = $leaf["host_template_name"];
			$tree_names[0] = $currentTitle;
		}

		if ($previous_tier != $current_tier) {
			$previous_tier = $current_tier;
		}

		/* Last leaf, process what what's left  */
		if ($i == sizeof($heirarchy)-1) {
			if (sizeof($leafs) > 0 ) {
				$result .= render_branch($leafs);
			}
		}
	}

	$result = "<tr><td valign=top><center>" . $result;
	$result = $result . "</center></td></tr>";
	return $result;
}

function render_group_by_tree() {
	$result = '';

	$heirarchy = get_host_tree_array();
	$leafs = array();
	$branchleafs = 0;

	$current_tier = 0;
	$previous_tier = 0;

	$current_tree_name = "";
	$tree_names = array();

	for ($i = 0; $i < sizeof($heirarchy); $i++) {
		$leaf = $heirarchy[$i];

		if (array_key_exists('order_key',$leaf)) {
			$current_tier = tree_tier($leaf['order_key']);
		}

		if (array_key_exists( intval($current_tier-1) ,$tree_names)) {
			$leaf["branch_name"] = $tree_names[$current_tier-1];
		} else {
			$leaf["branch_name"] = "";
		}

		if (array_key_exists("host_id",$leaf) && $leaf["host_id"] > 0) {
			if (sizeof($leafs) > 0 && ($previous_tier != $current_tier) ) {
				$result .= render_branch($leafs);
				/* start next branch */
				$leafs = array();
				$current_tree_name  = $tree_names[$current_tier-1];
			}
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;

		}elseif (array_key_exists("name",$leaf)) {
			$currentTitle = $leaf["name"];
			$tree_names[0] = $currentTitle;

		} elseif ($leaf["title"] != "")  {
			$tree_names[$current_tier] = $leaf["title"];
		}

		if ($previous_tier != $current_tier) {
			$previous_tier = $current_tier;
		}

		/* Last leaf, process what what's left  */
		if ($i == sizeof($heirarchy)-1) {
			if (sizeof($leafs) > 0 ) {
				$result .= render_branch($leafs);
			}
		}
	}

	/* begin others - lets get the monitor items that are not associated with any tree */
	$heirarchy = get_host_non_tree_array();
	if (sizeof($heirarchy) > 0) {
		$result .= render_branch($heirarchy, "Other");
	}

	$result = "<tr><td valign=top><center>" . $result;
	$result = $result . "</center></td></tr>";
	return $result;

}

/* Branch rendering */
function render_branch($leafs, $title = "") {
	global $render_style;
	global $row_stripe;

	$row_stripe=false;

	if ($title == "") {
		foreach ($leafs as $row) {
			/* get our proper branch title */
			$title = $row["branch_name"];
			break;
		}
	}
	if ($title == "") {
		/* Insert a default title */
		$title = "Items";
		$title .= " (" . sizeof($leafs) . ")";
	}
	//$branch_percentup = '%' . leafs_percentup($leafs);
	//$title .= " - $branch_percentup";

	/* select function to render here */
	$function = "render_branch_$render_style";
	if (function_exists($function)) {
		/* Call the custom render_branch_ function */
		return $function($leafs, $title);
	}else{
		return render_branch_group_by_tree($leafs, $title);
	}
}

function render_branch_group_by_tree($leafs, $title) {
	global $config;
	global $row_stripe;

	$result = '';
	$color = '#183C8F';
	$color = get_status_color(leafs_status_min($leafs));

	$result .= "<table border=0 style='float:left; margin:10px;' cellpadding=1 cellspacing=0 bgcolor='$color'><tr><td>&nbsp;<font color='#FFFFFF'><b>$title</b></font></td></tr><tr><td bgcolor='$color'>\n";
	$result .= "<table border=0 cellspacing=5 bgcolor='#FFFFFF' width='100%' id=$title>\n";

	$x = 0;
	$r = read_config_option("monitor_width");
	foreach ($leafs as $row) {
		if ($x == 0) {
			// If we are the the beginning of a row, start the row
			$result .= "<tr>";
		}
		$result .= "<td valign=top>";
		$result .= render_host($row);
		$result .= "</td>";
		$x++;
		if ($x == $r) {
			// After so many items, end the row and start over
			$result .= '</tr>';
			$x = 0;
			if ($row_stripe) { $row_stripe = false; }else{ $row_stripe = true; }
		}
	}

	$result .= "</table></td></tr></table>\n";
	return $result;
}


/*Single host  rendering */
function render_host($host) {
	global $thold, $thold_hosts, $config, $muted_hosts;

	//throw out tree root items
	if (array_key_exists("name",$host))  {return;}
	if ($host['id'] <= 0) {return;}

	// Create the status array (0=unknown, 1=down, 2=recovering, 3=up)
	$icolors = array('red.gif', 'red.gif', 'blue.gif', 'green.gif', 'orange.gif', 'muted.gif');
	$icolorsdisplay = array('Unknown', '<font color=red><b>Down</b></font>', 'Recovering', 'Up', 'Threshold Breached', '<font color=red><b>Down</b></font> (Muted)');

	$result = "";
	$row = $host;
	// Loop through each host one by one
	$id = $host['id'];
	$anchor = $config['url_path'] . "graph_view.php?action=preview&host_id=" . $row['id'];
	$name = $row['description'];
	$status = $row['status'];
	$hostname =  $row['hostname'];
	$ptime = round($row['cur_time'],2);
	$d = $row['status_rec_date'];
	if ($d == "0000-00-00 00:00:00") $d = "Never";
	$avail = round($row['availability'],2);

	if ($thold) {
		if ($status == 3 && in_array($row['id'], $thold_hosts)) {
			$status = 4;
			if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
				$anchor = $config['url_path'] . 'plugins/thold/thold_graph.php';
			} else {
				$anchor = $config['url_path'] . 'plugins/thold/graph_thold.php';
			}
		}
	}

	/* If the host has been muted, show the muted Icon */
	if (in_array($row['id'], $muted_hosts) && $status == 1)
		$status = 5;

	$icolor = $icolors[$status];
	$sdisplay = $icolorsdisplay[$status];

	$s = $s2 = '';
	$dt = '';
	if ($status < 2 || $status == 5) {
		// If the host is down, we bold the name
		$s = '<b>';
		$s2 = '</b>';
		$dt = monitor_print_host_downtime ($row['status_fail_date']);
	}

	$title = "<table cellpadding=0 cellspacing=0><tr><td colspan=2><b>$name</b></td></tr><tr valign=top><td>Status:</td><td>$sdisplay</td></tr><tr valign=top><td>IP Address:</td><td>$hostname</td></tr><tr valign=top><td>Ping:</td><td>$ptime ms</td></tr><tr valign=top><td>Last Fail:</td><td>$d</td></tr><tr valign=top><td>Availability:&nbsp;&nbsp;</td><td>$avail%</td></tr></table>";

	$render_style = read_config_option("monitor_view");
	$function = "render_host_$render_style";

	if (function_exists($function)) {
		/* Call the custom render_host_ function */
		$detail["id"] = $id;
		$detail["anchor"] = $anchor;
		$detail["icolor"] = $icolor;
		$detail["name"] = $name;
		$detail["title"] = $title;
		$detail["displayname"] = $s . $name . $s2;
		$detail["status"] = $status;
		$detail["sdisplay"] = $sdisplay;
		$detail["hostname"] = $hostname;
		$detail["ping_time"] = $ptime;
		$detail["last_fail"] = $row['status_fail_date'];
		$detail["availibility"] = $avail;
		$detail["downtime"] = $s . $dt . $s2;
		$result .= $function($detail);
	}else{
		if ($status < 2 || $status == 5) {
			$result .= "<center><a class=info href='$anchor'><img id='status_$id' src='images/$icolor' border='0'><br>$s$name$s2<span>$title</span><br><font color='red'>$s$dt$s2</font></a></center>\n";
		} else {
			$result .= "<center><a class=info href='$anchor'><img id='status_$id' src='images/$icolor' border='0'><br><span>$title</span>$s$name$s2</a></center>\n";
		}
	}

	if ($status != 3) {
		$color = get_status_color($status);
		$result .= "<script><!--
			blink_status('status_$id','$color');
		//--></script>";
	}

	return $result;
}

function monitor_print_host_downtime ($status_fail_date) {
	// If the host is down, make a downtime since message
	$dt = "";
	$sfd = time() - strtotime($status_fail_date);
	$dt_d = floor($sfd/86400);
	$dt_h = floor(($sfd - ($dt_d * 86400))/3600);
	$dt_m = floor(($sfd - ($dt_d * 86400) - ($dt_h * 3600))/60);
	$dt_s = $sfd - ($dt_d * 86400) - ($dt_h * 3600) - ($dt_m * 60);
	if ($dt_d > 0 ) {
		$dt .= $dt_d . "d " . $dt_h . "h " . $dt_m . "m " . $dt_s . "s";
	} else if ($dt_h > 0 ) {
		$dt .= $dt_h . "h " . $dt_m . "m " . $dt_s . "s";
	} else if ($dt_m > 0 ) {
		$dt .= $dt_m . "m " . $dt_s . "s";
	} else {
		$dt .= $dt_s . "s";
	}
	return $dt;
}

function render_host_tiles($detail) {
	$result = "";
	$id = $detail["id"];
	$anchor = $detail["anchor"];
	$icolor = $detail["icolor"];
	$name= $detail["name"];
	$title= $detail["title"];
	$displayname= $detail["displayname"];
	$status= $detail["status"];
	$sdisplay= $detail["sdisplay"];
	$hostname = $detail["hostname"];
	$ptime = $detail["ping_time"];
	$d = $detail["last_fail"];
	$avail = $detail["availibility"];

	$result .= "<table id='status_$id' width='100%' border='0' style='border: 1px solid #aaaaaa;'>
	<tr>
		<td rowspan='6' width='30px' valign='top' ><a class=info href='$anchor'><img src='images/$icolor' name='$name' id='$name' border='0'></a></td>
		<td colspan='2'><a href='$anchor'><font color='black'>$displayname</font></a></td>
	</tr>
	<tr bgcolor='#eeeeee'>
		<td  width='100px'>Status:</td>
		<td>$sdisplay</td>
	</tr>
	<tr>
		<td>IP Address:</td>
		<td>$hostname</td>
	</tr>
	<tr bgcolor='#eeeeee'>
		<td>Ping:</td>
		<td>$ptime ms</td>
	</tr>
	<tr>
		<td>Last Fail:</td>
		<td>$d</td>
	</tr>
	<tr bgcolor='#eeeeee'>
		<td>Availability:</td>
		<td>$avail%</td>
	</tr>
	</table>\n";
	return $result;
}

function render_host_list($detail) {
	$result = "";
	$id = $detail["id"];
	$anchor = $detail["anchor"];
	$icolor = $detail["icolor"];
	$name= $detail["name"];
	$title= $detail["title"];
	$displayname= $detail["displayname"];
	$status= $detail["status"];
	$sdisplay= $detail["sdisplay"];
	$hostname = $detail["hostname"];
	$ptime = $detail["ping_time"];
	$d = $detail["last_fail"];
	$avail = $detail["availibility"];
	$result .= "<a class=info href='$anchor'>
			<img id='status_$id' style='border: 1px solid #ffffff;' width='12' height='12' src='images/$icolor' name='$name' id='$name' border='0'><span>$title</span>&nbsp;<font color='black'>$displayname</font>
		</a>\n";
	if ($status < 2 || $status == 5) {
		$result .= '<br><font color=red><strong>' . monitor_print_host_downtime ($detail['last_fail']) . '</strong></font>';
	}

	return $result;
}

function render_host_color_blocks($detail) {
	$result = "";
	$id = $detail["id"];
	$anchor = $detail["anchor"];
	$icolor = $detail["icolor"];
	$name= $detail["name"];
	$title= $detail["title"];
	$displayname= $detail["displayname"];
	$status= $detail["status"];
	$sdisplay= $detail["sdisplay"];
	$hostname = $detail["hostname"];
	$ptime = $detail["ping_time"];
	$d = $detail["last_fail"];
	$avail = $detail["availibility"];

	$color = get_status_color($status);
	$result .= "
		<center><table id='status_$id' border=0 cellspacing=1 cellpadding=4 width='100px' bgcolor='$color'><tbody>
		<tr><td align='center'>
			<a class=info href='$anchor'>
			<img style='border: 1px solid #ffffff;' src='images/$icolor' name='$name' id='$name' border='0'><span>$title</span>
			</a>
		</td></tr>
		<tr><td bgcolor='#ffffff' align='center'>
			<a class=info href='$anchor'>
			<font color='black'>$displayname</font>
			</a>
		</td></tr>
		</tbody></table></center>\n";
	return $result;
}

function render_host_simple($detail) {
	$result = "";
	$id = $detail["id"];
	$anchor = $detail["anchor"];
	$icolor = $detail["icolor"];
	$name= $detail["name"];
	$title= $detail["title"];
	$displayname= $detail["displayname"];
	$status= $detail["status"];
	$sdisplay= $detail["sdisplay"];
	$hostname = $detail["hostname"];
	$ptime = $detail["ping_time"];
	$d = $detail["last_fail"];
	$avail = $detail["availibility"];
	$dt = $detail["downtime"];

	$color = get_status_color($status);
	$result .= "		<a class=info href='$anchor'><font id='status_$id' color='$color'>$displayname</font><span>$title</span><br><font color='red'>$dt</font></a>\n";
	return $result;
}

/*  Data retrieval */

function get_host_down_by_permission() {
	$result = array();

	global $render_style;
	if ($render_style == "default") {
		$sql = "SELECT description, id, status, disabled FROM host where status < 2 and disabled = '' and monitor = 'on'";
		$query_result = mysql_query($sql) or die (mysql_error());
		// do a quick loop through to pull the hosts that are down
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$host_down = true;
			$result[] = $row['id'];
			sort($result);
		}
	} else {
		/* Only get hosts */
		$tree_hosts = get_host_tree_array("host.status < 2 and host.id > 0",true);
		$non_tree_hosts = get_host_non_tree_array('host.status < 2 and host.id > 0');

		$heirarchy = array_merge ($tree_hosts, $non_tree_hosts);
		if (sizeof($heirarchy) > 0) {
			foreach ($heirarchy as $leaf) {
				$host_down = true;
				$result[] = $leaf['id'];
				sort($result);
			}
		}
	}
	return $result;
}

function get_host_tree_array($where = "",$only_hosts = false) {
	$leafs = array();
	$branchleafs = 0;
	$tree_list = get_graph_tree_array();

	/* auth check for hosts on the trees */
	if (read_config_option("global_auth") == "on") {
		$current_user = db_fetch_row("select policy_hosts from user_auth where id=" . $_SESSION["sess_user_id"]);

		$sql_join = "left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")";

		if ($current_user["policy_hosts"] == "1") {
			$sql_where = "and !(user_auth_perms.user_id is not null and graph_tree_items.host_id > 0)";
		}elseif ($current_user["policy_hosts"] == "2") {
			$sql_where = "and !(user_auth_perms.user_id is null and graph_tree_items.host_id > 0)";
		}
	}else{
		$sql_join = "";
		$sql_where = "";
	}

	/* Only return monitor items */
	$sql_where .= " and ((host.disabled = '' and host.monitor = 'on') or (title != ''))";
	if (strstr($where,'and') != $where) {
		$where = "and " . $where;
	}
	$sql_where .= $where . " ";

	if (sizeof($tree_list) > 0) {
		foreach ($tree_list as $tree) {
			$heirarchy = db_fetch_assoc("select
				graph_tree_items.title,
				graph_tree_items.order_key,
				graph_tree_items.host_id,
				graph_tree_items.host_grouping_type,
				host.id,
				host.description,
				host.status,
				host.hostname,
				host.cur_time,
				host.status_rec_date,
				host.status_fail_date,
				host.availability
				from graph_tree_items
				left join host on (host.id=graph_tree_items.host_id)
				$sql_join
				where graph_tree_items.graph_tree_id=" . $tree["id"] . "
				$sql_where
				and graph_tree_items.local_graph_id = 0
				order by graph_tree_items.order_key");

			if (sizeof($heirarchy) > 0) {
				if (!$only_hosts) {
					$leafs[$branchleafs] = $tree;
					$branchleafs++;
				}
				foreach ($heirarchy as $leaf) {
					$leafs[$branchleafs] = $leaf;
					$branchleafs++;
				}
			}
		}
	}
	return $leafs;
}

function get_host_non_tree_array($where = "") {
	$leafs = array();
	/* auth check for hosts on the trees */
	if (read_config_option("global_auth") == "on") {
		$current_user = db_fetch_row("select policy_hosts from user_auth where id=" . $_SESSION["sess_user_id"]);

		$sql_join = "left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")";

		if ($current_user["policy_hosts"] == "1") {
			$sql_where = "and !(user_auth_perms.user_id is not null and graph_tree_items.host_id > 0)";
		}elseif ($current_user["policy_hosts"] == "2") {
			$sql_where = "and !(user_auth_perms.user_id is null and graph_tree_items.host_id > 0)";
		}
	}else{
		$sql_join = "";
		$sql_where = "";
	}
	$sql_where .= " and ((host.disabled = '' and host.monitor = 'on') or (title != ''))";
	if (strstr($where,'and') != $where) {
		$where = "and " . $where;
	}
	$sql_where .= $where . " ";
	$heirarchy = db_fetch_assoc("select
		graph_tree_items.title,
		graph_tree_items.order_key,
		graph_tree_items.host_id,
		graph_tree_items.host_grouping_type,
		host.id,
		host.description,
		host.status,
		host.hostname,
		host.cur_time,
		host.status_rec_date,
		host.status_fail_date,
		host.availability,
		graph_tree_items.graph_tree_id
		from host
		left join graph_tree_items on (host.id=graph_tree_items.host_id)
		$sql_join
		where graph_tree_items.graph_tree_id IS NULL
		$sql_where
		order by host.description");

	if (sizeof($heirarchy) > 0) {
		$leafs = array();
		$branchleafs = 0;
		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}
	return $leafs;
}


function get_host_template_array($where = "") {
	$leafs = array();
	/* auth check for hosts on the trees */
	if (read_config_option("global_auth") == "on") {
		$current_user = db_fetch_row("select policy_hosts from user_auth where id=" . $_SESSION["sess_user_id"]);

		$sql_join = "left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")";
		$sql_where = "";
	}else{
		$sql_join = "";
		$sql_where = "";
	}
	$sql_where .= "WHERE ((host.disabled = '' and host.monitor = 'on'))";
	if (strstr($where,'and') != $where) {
		$where = "and " . $where;
	}
	$sql_where .= $where . " ";
	$heirarchy = db_fetch_assoc("select
		host_template.name AS host_template_name,
		host.id,
		host.host_template_id,
		host.description,
		host.status,
		host.hostname,
		host.cur_time,
		host.status_rec_date,
		host.status_fail_date,
		host.availability
		from host
		left join host_template on (host.host_template_id=host_template.id)
		$sql_join
		$sql_where
		order by host_template.name ASC, host.description ASC");

	$leafs = array();
	if (sizeof($heirarchy) > 0) {
		$branchleafs = 0;
		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}
	return $leafs;
}

/* Supporting functions */
function get_status_color($status=3) {
	$color = '#183C8F';
	switch ($status) {
		case 0: //error
			$color = '#993333';
			break;
		case 1: //error
			$color = '#993333';
			break;
		case 2: //recovering
			$color = '#7293B9';
			break;
		case 3: //ok
			$color = '#669966';
			break;
		case 4: //threshold
			$color = '#c56500';
			break;
		case 5: //muted
			$color = '#996666';
			break;
		default: //unknown
			$color = '#999999';
			break;
		}
	return $color;
}

function tree_tier($order_key, $chars_per_tier = CHARS_PER_TIER) {
	$root_test = str_pad('', $chars_per_tier, '0');

	if (preg_match("/^$root_test/", $order_key)) {
		$tier = 0;
	}else{
		$tier = ceil(strlen(preg_replace("/0+$/",'',$order_key)) / $chars_per_tier);
	}
	return $tier;
}

function leafs_status_min($leafs) {
	global $thold;
	global $thold_hosts;
	$thold_breached = 0;
	$result = 3;
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($result > $status) {
			$result = $status;
		}
		if ($thold) {
			if ($status == 3 && in_array($row['id'], $thold_hosts)) {
				$thold_breached = 1;
			}
		}
	}
	if ($result == 3 && $thold_breached) {
		$result = 4;
	}
	return $result;
}

function leafs_percentup($leafs) {
	$result = 0;
	$countup = 0;
	$count = sizeof($leafs);
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($status >= 3) {
			$countup++;
		}
	}
	if ($countup>=$count){
		return 100;
	}
	$result = round($countup/$count*100,0);
	return $result;
}

