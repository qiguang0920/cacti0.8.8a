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

function send_mail($to, $from, $subject, $message, $filename = '', $headers = '') {
	global $config;
	include_once($config["base_path"] . "/plugins/settings/include/mailer.php");

	$message = str_replace('<SUBJECT>', $subject, $message);
	$message = str_replace('<TO>', $to, $message);
	$message = str_replace('<FROM>', $from, $message);

	$how = read_config_option("settings_how");
	if ($how < 0 || $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host = read_config_option("settings_smtp_host");
		$smtp_port = read_config_option("settings_smtp_port");
		$smtp_username = read_config_option("settings_smtp_username");
		$smtp_password = read_config_option("settings_smtp_password");

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from = read_config_option('settings_from_email');
		$fromname = read_config_option('settings_from_name');
		if ($from == "") {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cactiusers.org';
			}
		}
		if ($fromname == "")
			$fromname = "Cacti";

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format('Cacti', $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	if ($to == '')
		return "Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.";
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set("To", $t)) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option("settings_wordwrap");
	if ($wordwrap == '')
		$wordwrap = 76;
	if ($wordwrap > 9999)
		$wordwrap = 9999;
	if ($wordwrap < 0)
		$wordwrap = 76;

	$Mailer->Config["Mail"]["WordWrap"] = $wordwrap;

	if (! $Mailer->header_set("Subject", $subject)) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	if (is_array($filename) && !empty($filename) && strstr($message, '<GRAPH>') !==0) {
		foreach($filename as $val) {
			$graph_data_array = array("output_flag"=> RRDTOOL_OUTPUT_STDOUT);
  			$data = @rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
			if ($data != "") {
				$cid = $Mailer->content_id();
				if ($Mailer->attach($data, $val['filename'].'.png', "image/png", "inline", $cid) == false) {
					print "ERROR: " . $Mailer->error() . "\n";
					return $Mailer->error();
				}
				$message = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $message);
			} else {
				$message = str_replace('<GRAPH>', "<br><img src='" . $val['file'] . "'><br>Could not open!<br>" . $val['file'], $message);
			}
		}
	}
	$text = array('text' => '', 'html' => '');
	if ($filename == '') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	if ($Mailer->send($text) == false) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	return '';

}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function get_dns_from_ip ($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode(".", $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return "ERROR";

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return "ERROR";
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info["timed_out"]) {
		return "timed_out";
	}

	/* more error handling */
	if ($response == "") { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack("s", substr($response, $requestsize+2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = "";
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack("c", substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return strtoupper(substr($host, 0, strlen($host) -1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . ".";

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return strtoupper($ip);
	}

	/* error - return the hostname */
	return strtoupper($ip);
}
