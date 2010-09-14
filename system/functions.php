<?php
/**
 * Main function library.
 *
 * @package Cotonti
 * @version 0.9.0
 * @author Neocrome, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2010
 * @license BSD License
 */

defined('COT_CODE') or die('Wrong URL');

// System requirements check
if (!defined('COT_INSTALL'))
{
	(function_exists('version_compare') && version_compare(PHP_VERSION, '5.2.0', '>=')) or die('Cotonti system requirements: PHP 5.2 or above.'); // TODO: Need translate
	extension_loaded('mbstring') or die('Cotonti system requirements: mbstring PHP extension must be loaded.'); // TODO: Need translate
}

// Group constants
define('COT_GROUP_DEFAULT', 0);
define('COT_GROUP_GUESTS', 1);
define('COT_GROUP_INACTIVE', 2);
define('COT_GROUP_BANNED', 3);
define('COT_GROUP_MEMBERS', 4);
define('COT_GROUP_SUPERADMINS', 5);
define('COT_GROUP_MODERATORS', 6);

/* ======== Pre-sets ========= */

$out = array();
$plu = array();
$sys = array();
$usr = array();

$i = explode(' ', microtime());
$sys['starttime'] = $i[1] + $i[0];

$cfg['svnrevision'] = '$Rev$'; //DO NOT MODIFY this is set by SVN automatically
$cfg['version'] = '0.7.0';
$cfg['dbversion'] = '0.7.0';

// Set default file permissions if not present in config
if (!isset($cfg['file_perms']))
{
	$cfg['file_perms'] = 0664;
}
if (!isset($cfg['dir_perms']))
{
	$cfg['dir_perms'] = 0777;
}

/*
 * =========================== System Functions ===============================
*/

/**
 * Strips everything but alphanumeric, hyphens and underscores
 *
 * @param string $text Input
 * @return string
 */
function cot_alphaonly($text)
{
	return(preg_replace('/[^a-zA-Z0-9\-_]/', '', $text));
}

/**
 * Truncates a string
 *
 * @param string $res Source string
 * @param int $l Length
 * @return unknown
 */
function cot_cutstring($res, $l)
{
	global $cfg;
	if (mb_strlen($res)>$l)
	{
		$res = mb_substr($res, 0, ($l-3)).'...';
	}
	return $res;
}

/**
 * Returns a list of plugins registered for a hook
 *
 * @param string $hook Hook name
 * @param string $cond Permissions
 * @return array
 */
function cot_getextplugins($hook, $cond='R')
{
	global $cot_plugins, $cot_cache;

	$extplugins = array();

	if (is_array($cot_plugins[$hook]))
	{
		foreach($cot_plugins[$hook] as $k)
		{
			if ($k['pl_module'])
			{
				$cat = $k['pl_code'];
				$opt = 'a';
			}
			else
			{
				$cat = 'plug';
				$opt = $k['pl_code'];
			}
			if (cot_auth($cat, $opt, $cond))
			{
				$extplugins[] = $k['pl_file'];
			}
		}
	}

	// Trigger cache handlers
	$cot_cache && $cot_cache->trigger($hook);

	return $extplugins;
}

/**
 * Imports data from the outer world
 *
 * @param string $name Variable name
 * @param string $source Source type: G (GET), P (POST), C (COOKIE) or D (variable filtering)
 * @param string $filter Filter type
 * @param int $maxlen Length limit
 * @param bool $dieonerror Die with fatal error on wrong input
 * @return mixed
 */
function cot_import($name, $source, $filter, $maxlen=0, $dieonerror=FALSE)
{
	switch($source)
	{
		case 'G':
			$v = (isset($_GET[$name])) ? $_GET[$name] : NULL;
			$log = TRUE;
			break;

		case 'P':
			$v = (isset($_POST[$name])) ? $_POST[$name] : NULL;
			$log = TRUE;
			if ($filter=='ARR')
			{
				return($v);
			}
			break;

		case 'R':
			$v = (isset($_REQUEST[$name])) ? $_REQUEST[$name] : NULL;
			$log = TRUE;
			break;

		case 'C':
			$v = (isset($_COOKIE[$name])) ? $_COOKIE[$name] : NULL;
			$log = TRUE;
			break;

		case 'D':
			$v = $name;
			$log = FALSE;
			break;

		default:
			cot_diefatal('Unknown source for a variable : <br />Name = '.$name.'<br />Source = '.$source.' ? (must be G, P, C or D)');
			break;
	}

	if (MQGPC && ($source=='G' || $source=='P' || $source=='C') )
	{
		$v = stripslashes($v);
	}

	if ($v=='' || $v == NULL)
	{
		return($v);
	}

	if ($maxlen>0)
	{
		$v = mb_substr($v, 0, $maxlen);
	}

	$pass = FALSE;
	$defret = NULL;
	$filter = ($filter=='STX') ? 'TXT' : $filter;

	switch($filter)
	{
		case 'INT':
			if (is_numeric($v) && floor($v)==$v)
			{
				$pass = TRUE;
			}
			break;

		case 'NUM':
			if (is_numeric($v))
			{
				$pass = TRUE;
			}
			break;

		case 'TXT':
			$v = trim($v);
			if (mb_strpos($v, '<')===FALSE)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = str_replace('<', '&lt;', $v);
			}
			break;

		case 'SLU':
			$v = trim($v);
			$f = preg_replace('/[^a-zA-Z0-9_=\/]/', '', $v);
			if ($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = '';
			}
			break;

		case 'ALP':
			$v = trim($v);
			$f = cot_alphaonly($v);
			if ($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = $f;
			}
			break;

		case 'PSW':
			$v = trim($v);
			$f = preg_replace('#[\'"&<>]#', '', $v);
			$f = mb_substr($f, 0 ,32);

			if ($v == $f)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = $f;
			}
			break;

		case 'HTM':
			$v = trim($v);
			$pass = TRUE;
			break;

		case 'ARR':
			$pass = TRUE;
			break;

		case 'BOL':
			if ($v == '1' || $v == 'on')
			{
				$pass = TRUE;
				$v = '1';
			}
			elseif ($v=='0' || $v=='off')
			{
				$pass = TRUE;
				$v = '0';
			}
			else
			{
				$defret = '0';
			}
			break;

		case 'LVL':
			if (is_numeric($v) && $v >= 0 && $v <= 100 && floor($v)==$v)
			{
				$pass = TRUE;
			}
			else
			{
				$defret = NULL;
			}
			break;

		case 'NOC':
			$pass = TRUE;
			break;

		default:
			cot_diefatal('Unknown filter for a variable : <br />Var = '.$cv_v.'<br />Filter = &quot;'.$filter.'&quot; ?');
			break;
	}

	$v = preg_replace('/(&#\d+)(?![\d;])/', '$1;', $v);
	if ($pass)
	{
		return $v;
	}
	else
	{
		if ($log)
		{
			cot_log_import($source, $filter, $name, $v);
		}
		if ($dieonerror)
		{
			cot_diefatal('Wrong input.');
		}
		else
		{
			return $defret;
		}
	}
}

/**
 * Puts POST data into the cross-request buffer
 */
function cot_import_buffer_save()
{
	unset($_SESSION['cot_buffer']);
	$_SESSION['cot_buffer'] = $_POST;
}

/**
 * Attempts to fetch a buffered value for a variable previously imported
 * if the currently imported value is empty
 *
 * @param string $name Input name
 * @param mixed $value Currently imported value
 * @return mixed Input value or NULL if the variable is not in the buffer
 */
function cot_import_buffered($name, $value)
{
	if (empty($value))
	{
		if (isset($_SESSION['cot_buffer'][$name]))
		{
			return htmlspecialchars($_SESSION['cot_buffer'][$name]);
		}
		else
		{
			return null;
		}
	}
	else
	{
		return $value;
	}
}

/**
 * Imports date stamp
 *
 * @param string $name Variable name preffix
 * @param string $ext Variable name suffix
 * @param bool $usertimezone Use user timezone
 * @param bool $returnarray Return Date Array
 * @return mixed
 */
function cot_import_date($name = '', $ext='', $usertimezone = true, $returnarray = false)
{
	global $L, $R, $usr;
	$name = preg_match('#^(\w+)\[(.*?)\]$#', $name, $mt) ? $mt[1] : $name;

	$year = cot_import($name.'_year'.$ext, 'P', 'INT');
	$month = cot_import($name.'_month'.$ext, 'P', 'INT');
	$day = cot_import($name.'_day'.$ext, 'P', 'INT');
	$hour = cot_import($name.'_hour'.$ext, 'P', 'INT');
	$minute = cot_import($name.'_minute'.$ext, 'P', 'INT');

	if (((int)($month) > 0 && (int)($day) > 0 && (int)($year) > 0) || ((int)($day) > 0 && (int)($minute) > 0))
	{
		$result = cot_mktime($hour, $minute, 0, $month, $day, $year);
		$result = ($usertimezone) ? ($result - $usr['timezone'] * 3600) : $result;
	}
	else
	{
		$result = 0;
	}

	if($returnarray)
	{
		$result['stamp'] = $result;
		$result['year'] = $year;
		$result['month'] = $month;
		$result['day'] = $day;
		$result['hour'] = $hour;
		$result['minute'] = $minute;
	}

	return $result;
}

/**
 * Loads comlete category structure into array
 */
function cot_load_structure()
{
	global $db_structure, $db_extra_fields, $cfg, $L, $cot_cat, $cot_extrafields;
	$cot_cat = array();
	$sql = cot_db_query("SELECT * FROM $db_structure ORDER BY structure_path ASC");

	while ($row = cot_db_fetcharray($sql))
	{
		if (!empty($row['structure_icon']))
		{
			$row['structure_icon'] = '<img src="'.$row['structure_icon'].'" alt="'.htmlspecialchars($row['structure_title']).'" title="'.htmlspecialchars($row['structure_title']).'" />'; // TODO - to resorses
		}

		$path2 = mb_strrpos($row['structure_path'], '.');

		$row['structure_tpl'] = (empty($row['structure_tpl'])) ? $row['structure_code'] : $row['structure_tpl'];

		if ($path2 > 0)
		{
			$path1 = mb_substr($row['structure_path'], 0, ($path2));
			$path[$row['structure_path']] = $path[$path1].'.'.$row['structure_code'];
			$tpath[$row['structure_path']] = $tpath[$path1].' '.$cfg['separator'].' '.$row['structure_title'];
			$row['structure_tpl'] = ($row['structure_tpl'] == 'same_as_parent') ? $parent_tpl : $row['structure_tpl'];
		}
		else
		{
			$path[$row['structure_path']] = $row['structure_code'];
			$tpath[$row['structure_path']] = $row['structure_title'];
		}

		$order = explode('.', $row['structure_order']);
		$parent_tpl = $row['structure_tpl'];

		$cot_cat[$row['structure_code']] = array(
			'path' => $path[$row['structure_path']],
			'tpath' => $tpath[$row['structure_path']],
			'rpath' => $row['structure_path'],
			'tpl' => $row['structure_tpl'],
			'title' => $row['structure_title'],
			'desc' => $row['structure_desc'],
			'icon' => $row['structure_icon'],
			'group' => $row['structure_group'],
			'ratings' => $row['structure_ratings'],
			'order' => $order[0],
			'way' => $order[1]
		);

		if (is_array($cot_extrafields['structure']))
		{
			foreach ($cot_extrafields['structure'] as $row_c)
			{
				$cot_cat[$row['structure_code']][$row_c['field_name']] = $row['structure_'.$row_c['field_name']];
			}
		}

		/* == Hook == */
		foreach (cot_getextplugins('structure') as $pl)
		{
			include $pl;
		}
		/* ===== */
	}
}

/**
 * Updates online users table
 * @global array $cfg
 * @global array $sys
 * @global array $usr
 * @global array $out
 * @global string $db_online
 * @global Cache $cot_cache
 * @global array $cot_usersonline
 * @global string $location Location string
 */
function cot_online_update()
{
	global $cfg, $sys, $usr, $out, $db_online, $db_stats, $cot_cache, $cot_usersonline, $location, $Ls;
	if (!$cfg['disablewhosonline'])
	{
		if ($location != $sys['online_location']
			|| !empty($sys['sublocaction']) && $sys['sublocaction'] != $sys['online_subloc'])
		{
			if ($usr['id'] > 0)
			{
				if (empty($sys['online_location']))
				{
					cot_db_query("INSERT INTO $db_online (online_ip, online_name, online_lastseen, online_location, online_subloc, online_userid, online_shield, online_hammer)
						VALUES ('".$usr['ip']."', '".cot_db_prep($usr['name'])."', ".(int)$sys['now'].", '".cot_db_prep($location)."',  '".cot_db_prep($sys['sublocation'])."', ".(int)$usr['id'].", 0, 0)");
				}
				else
				{
					cot_db_query("UPDATE $db_online SET online_lastseen='".$sys['now']."', online_location='".cot_db_prep($location)."', online_subloc='".cot_db_prep($sys['sublocation'])."', online_hammer=".(int)$sys['online_hammer']." WHERE online_userid=".$usr['id']);
				}
			}
			else
			{
				if (empty($sys['online_location']))
				{
					cot_db_query("INSERT INTO $db_online (online_ip, online_name, online_lastseen, online_location, online_subloc, online_userid, online_shield, online_hammer)
						VALUES ('".$usr['ip']."', 'v', ".(int)$sys['now'].", '".cot_db_prep($location)."', '".cot_db_prep($sys['sublocation'])."', -1, 0, 0)");
				}
				else
				{
					cot_db_query("UPDATE $db_online SET online_lastseen='".$sys['now']."', online_location='".$location."', online_subloc='".cot_db_prep($sys['sublocation'])."', online_hammer=".(int)$sys['online_hammer']." WHERE online_ip='".$usr['ip']."'");
				}
			}
		}
		if ($cot_cache && $cot_cache->mem && $cot_cache->mem->exists('whosonline', 'system'))
		{
			$whosonline_data = $cot_cache->mem->get('whosonline', 'system');
			$sys['whosonline_vis_count'] = $whosonline_data['vis_count'];
			$sys['whosonline_reg_count'] = $whosonline_data['reg_count'];
			$out['whosonline_reg_list'] = $whosonline_data['reg_list'];
			unset($whosonline_data);
		}
		else
		{
			$online_timedout = $sys['now'] - $cfg['timedout'];
			cot_db_query("DELETE FROM $db_online WHERE online_lastseen < $online_timedout");
			$sys['whosonline_vis_count'] = cot_db_result(cot_db_query("SELECT COUNT(*) FROM $db_online WHERE online_name='v'"), 0, 0);
			$sql_o = cot_db_query("SELECT DISTINCT o.online_name, o.online_userid FROM $db_online o WHERE o.online_name != 'v' ORDER BY online_name ASC");
			$sys['whosonline_reg_count'] = cot_db_numrows($sql_o);
			$ii_o = 0;
			while ($row_o = cot_db_fetcharray($sql_o))
			{
				$out['whosonline_reg_list'] .= ($ii_o > 0) ? ', ' : '';
				$out['whosonline_reg_list'] .= cot_build_user($row_o['online_userid'], htmlspecialchars($row_o['online_name']));
				$cot_usersonline[] = $row_o['online_userid'];
				$ii_o++;
			}
			cot_db_freeresult($sql_o);
			unset($ii_o, $sql_o, $row_o);
			if ($cot_cache && $cot_cache->mem)
			{
				$whosonline_data = array(
					'vis_count' => $sys['whosonline_vis_count'],
					'reg_count' => $sys['whosonline_reg_count'],
					'reg_list' => $out['whosonline_reg_list']
				);
				$cot_cache->mem->store('whosonline', $whosonline_data, 'system', 30);
			}
		}
		$sys['whosonline_all_count'] = $sys['whosonline_reg_count'] + $sys['whosonline_vis_count'];
		$out['whosonline'] = ($cfg['disablewhosonline']) ? '' : cot_declension($sys['whosonline_reg_count'], $Ls['Members']).', '.cot_declension($sys['whosonline_vis_count'], $Ls['Guests']);

		/* ======== Max users ======== */
		if (!$cfg['disablehitstats'])
		{
			if ($cot_cache && $cot_cache->mem && $cot_cache->mem->exists('maxusers', 'system'))
			{
				$maxusers = $cot_cache->mem->get('maxusers', 'system');
			}
			else
			{
				$sql = cot_db_query("SELECT stat_value FROM $db_stats where stat_name='maxusers' LIMIT 1");
				$maxusers = (int) @cot_db_result($sql, 0, 0);
				$cot_cache && $cot_cache->mem && $cot_cache->mem->store('maxusers', $maxusers, 'system', 0);
			}

			if ($maxusers < $sys['whosonline_all_count'])
			{
				$sql = cot_db_query("UPDATE $db_stats SET stat_value='".$sys['whosonline_all_count']."'
					WHERE stat_name='maxusers'");
			}
		}
	}
}

/**
 * Standard SED output filters, adds XSS protection to forms
 *
 * @param unknown_type $output
 * @return unknown
 */
function cot_outputfilters($output)
{
	global $cfg;

	/* === Hook === */
	foreach (cot_getextplugins('output') as $pl)
	{
		include $pl;
	}
	/* ==== */

	$output = str_ireplace('</form>', cot_xp().'</form>', $output);

	return($output);
}

/**
 * Sends standard HTTP headers and disables browser cache
 *
 * @param string $content_type Content-Type value (without charset)
 * @param string $status_line HTTP status line containing response code
 * @return bool
 */
function cot_sendheaders($content_type = 'text/html', $status_line = 'HTTP/1.1 200 OK')
{
	global $cfg;
	header($status_line);
	header('Expires: Mon, Apr 01 1974 00:00:00 GMT');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0,pre-check=0', FALSE);
	header('Content-Type: '.$content_type.'; charset=UTF-8');
	header('Cache-Control: no-store,no-cache,must-revalidate');
	header('Cache-Control: post-check=0,pre-check=0', FALSE);
	header('Pragma: no-cache');
	return TRUE;
}

/**
 * Set cookie with optional HttpOnly flag
 * @param string $name The name of the cookie
 * @param string $value The value of the cookie
 * @param int $expire The time the cookie expires in unixtime
 * @param string $path The path on the server in which the cookie will be available on.
 * @param string $domain The domain that the cookie is available.
 * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection. When set to TRUE, the cookie will only be set if a secure connection exists.
 * @param bool $httponly HttpOnly flag
 * @return bool
 */
function cot_setcookie($name, $value, $expire, $path, $domain, $secure = false, $httponly = false)
{
	if (strpos($domain, '.') === FALSE)
	{
		// Some browsers don't support cookies for local domains
		$domain = '';
	}

	if ($domain != '')
	{
		// Make sure www. is stripped and leading dot is added for subdomain support on some browsers
		if (strtolower(substr($domain, 0, 4)) == 'www.')
		{
			$domain = substr($domain, 4);
		}
		if ($domain[0] != '.')
		{
			$domain = '.'.$domain;
		}
	}

	return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
}

/**
 * Performs actions required right before shutdown
 */
function cot_shutdown()
{
	global $cot_cache, $cot_error;
	// Clear import buffer if everything's OK on POST
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$cot_error)
	{
		unset($_SESSION['cot_buffer']);
	}
	while (ob_get_level() > 0)
	{
		ob_end_flush();
	}
	$cot_cache = null; // Need to destroy before DB connection is lost
	cot_db_close();
}

/**
 * Generates a title string by replacing submasks with assigned values
 *
 * @param string $area Area maskname or actual mask
 * @param array $params An associative array of available parameters
 * @return string
 */
function cot_title($mask, $params = array())
{
	global $cfg;
	$res = (!empty($cfg[$mask])) ? $cfg[$mask] : $mask;
	is_array($params) ? $args = $params : mb_parse_str($params, $args);
	if (preg_match_all('#\{(.+?)\}#', $res, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $m)
		{
			$var = $m[1];
			$res = str_replace($m[0], htmlspecialchars($args[$var], ENT_COMPAT, 'UTF-8', false), $res);
		}
	}
	return $res;
}

/**
 * Sends item to trash
 *
 * @param string $type Item type
 * @param string $title Title
 * @param int $itemid Item ID
 * @param mixed $datas Item contents
 */
function cot_trash_put($type, $title, $itemid, $datas)
{
	global $db_trash, $sys, $usr;

	$sql = cot_db_query("INSERT INTO $db_trash (tr_date, tr_type, tr_title, tr_itemid, tr_trashedby, tr_datas)
	VALUES
	(".$sys['now_offset'].", '".cot_db_prep($type)."', '".cot_db_prep($title)."', '".cot_db_prep($itemid)."', ".$usr['id'].", '".cot_db_prep(serialize($datas))."')");
}

/**
 * Generates random string
 *
 * @param int $l Length
 * @return string
 */
function cot_unique($l=16)
{
	return(mb_substr(md5(mt_rand()), 0, $l));
}

/*
 * ================================= Authorization Subsystem ==================================
*/

/**
 * Returns specific access permissions
 *
 * @param string $area Seditio area
 * @param string $option Option to access
 * @param string $mask Access mask
 * @return mixed
 */
function cot_auth($area, $option, $mask = 'RWA')
{
	global $sys, $usr;

	$mn['R'] = 1;
	$mn['W'] = 2;
	$mn['1'] = 4;
	$mn['2'] = 8;
	$mn['3'] = 16;
	$mn['4'] = 32;
	$mn['5'] = 64;
	$mn['A'] = 128;

	$masks = str_split($mask);
	$res = array();

	foreach ($masks as $k => $ml)
	{
		if (empty($mn[$ml]))
		{
			$sys['auth_log'][] = $area.'.'.$option.'.'.$ml.'=0';
			$res[] = FALSE;
		}
		elseif ($option == 'any')
		{
			$cnt = 0;

			if (is_array($usr['auth'][$area]))
			{
				foreach ($usr['auth'][$area] as $k => $g)
				{
					$cnt += (($g & $mn[$ml]) == $mn[$ml]);
				}
			}
			$cnt = ($cnt == 0 && $usr['auth']['admin']['a'] && $ml == 'A') ? 1 : $cnt;

			$sys['auth_log'][] = ($cnt > 0) ? $area.'.'.$option.'.'.$ml.'=1' : $area.'.'.$option.'.'.$ml.'=0';
			$res[] = ($cnt > 0) ? TRUE : FALSE;
		}
		else
		{
			$sys['auth_log'][] = (($usr['auth'][$area][$option] & $mn[$ml]) == $mn[$ml]) ? $area.'.'.$option.'.'.$ml.'=1' : $area.'.'.$option.'.'.$ml.'=0';
			$res[] = (($usr['auth'][$area][$option] & $mn[$ml]) == $mn[$ml]) ? TRUE : FALSE;
		}
	}
	return (count($res) == 1) ? $res[0] : $res;
}

/**
 * Builds Access Control List (ACL) for a specific user
 *
 * @param int $userid User ID
 * @param int $maingrp User main group
 * @return array
 */
function cot_auth_build($userid, $maingrp = 0)
{
	global $db_auth, $db_groups_users;

	$groups = array();
	$authgrid = array();
	$tmpgrid = array();

	if ($userid == 0 || $maingrp == 0)
	{
		$groups[] = 1;
	}
	else
	{
		$groups[] = $maingrp;
		$sql = cot_db_query("SELECT gru_groupid FROM $db_groups_users WHERE gru_userid=$userid");

		while ($row = cot_db_fetcharray($sql))
		{
			$groups[] = $row['gru_groupid'];
		}
	}

	$sql_groups = implode(',', $groups);
	$sql = cot_db_query("SELECT auth_code, auth_option, auth_rights FROM $db_auth WHERE auth_groupid IN (".$sql_groups.") ORDER BY auth_code ASC, auth_option ASC");

	while ($row = cot_db_fetcharray($sql))
	{
		$authgrid[$row['auth_code']][$row['auth_option']] |= $row['auth_rights'];
	}

	return $authgrid;
}

/**
 * Block user if he is not allowed to access the page
 *
 * @param bool $allowed Authorization result
 * @return bool
 */
function cot_block($allowed)
{
	if (!$allowed)
	{
		global $sys;
		cot_redirect(cot_url('message', 'msg=930&'.$sys['url_redirect'], '', true));
	}
	return FALSE;
}


/**
 * Block guests from viewing the page
 *
 * @return bool
 */
function cot_blockguests()
{
	global $usr, $sys;

	if ($usr['id'] < 1)
	{
		cot_redirect(cot_url('message', "msg=930&".$sys['url_redirect'], '', true));
	}
	return FALSE;
}

/*
 * =========================== Output forming functions ===========================
*/

/**
 * Calculates age out of D.O.B.
 *
 * @param int $birth Date of birth as UNIX timestamp
 * @return int
 */
function cot_build_age($birth)
{
	global $sys;

	if ($birth==1)
	{
		return ('?');
	}

	$day1 = @date('d', $birth);
	$month1 = @date('m', $birth);
	$year1 = @date('Y', $birth);

	$day2 = @date('d', $sys['now_offset']);
	$month2 = @date('m', $sys['now_offset']);
	$year2 = @date('Y', $sys['now_offset']);

	$age = ($year2-$year1)-1;

	if ($month1<$month2 || ($month1==$month2 && $day1<=$day2))
	{
		$age++;
	}

	if($age < 0)
	{
		$age += 136;
	}

	return ($age);
}

/**
 * Builds category path
 *
 * @param string $cat Category code
 * @param string $mask Format mask
 * @return string
 */
function cot_build_catpath($cat, $mask = 'link_catpath')
{
	global $cot_cat, $cfg;
	$mask = str_replace('%1$s', '{$url}', $mask);
	$mask = str_replace('%2$s', '{$title}', $mask);
	if ($cfg['homebreadcrumb'])
	{
		$tmp[] = cot_rc('link_catpath', array(
			'url' => $cfg['mainurl'],
			'title' => htmlspecialchars($cfg['maintitle'])
		));
	}
	$pathcodes = explode('.', $cot_cat[$cat]['path']);
	$last = count($pathcodes) - 1;
	$list = defined('COT_LIST');
	foreach ($pathcodes as $k => $x)
	{
		if ($k != 'system')
		{
			$tmp[] = ($list && $k === $last) ? htmlspecialchars($cot_cat[$x]['title'])
				: cot_rc($mask, array(
				'url' =>cot_url('list', 'c='.$x),
				'title' => htmlspecialchars($cot_cat[$x]['title'])
			));
		}
	}
	return is_array($tmp) ? implode(' '.$cfg['separator'].' ', $tmp) : '';
}

/**
 * Returns country text button
 *
 * @param string $flag Country code
 * @return string
 */
function cot_build_country($flag)
{
	global $cot_countries;
	if (!$cot_countries) include_once cot_langfile('countries', 'core');
	$flag = (empty($flag)) ? '00' : $flag;
	return cot_rc_link(cot_url('users', 'f=country_'.$flag), $cot_countries[$flag], array(
		'title' => $cot_countries[$flag]
	));
}

/**
 * Returns user email link
 *
 * @param string $email E-mail address
 * @param bool $hide Hide email option
 * @return string
 */
function cot_build_email($email, $hide = false)
{
	global $L;
	if ($hide)
	{
		return $L['Hidden'];
	}
	elseif (!empty($email) && preg_match('#^\w[\._\w\-]+@[\w\.\-]+\.[a-z]+$#', $email))
	{
		return cot_obfuscate('<a href="mailto:'.$email.'">'.$email.'</a>');
	}
}

/**
 * Returns country flag button
 *
 * @param string $flag Country code
 * @return string
 */
function cot_build_flag($flag)
{
	global $cot_countries;
	if (!$cot_countries) include_once cot_langfile('countries', 'core');
	$flag = (empty($flag)) ? '00' : $flag;
	return cot_rc_link(cot_url('users', 'f=country_'.$flag),
		cot_rc('icon_flag', array('code' => $flag, 'alt' => $flag)),
		array('title' => $cot_countries[$flag])
	);
}

/**
 * Returns IP Search link
 *
 * @param string $ip IP mask
 * @return string
 */
function cot_build_ipsearch($ip)
{
	global $sys;
	if (!empty($ip))
	{
		return cot_rc_link(cot_url('admin', 'm=tools&p=ipsearch&a=search&id='.$ip.'&x='.$sys['xk']), $ip);
	}
	return '';
}

/**
 * Odd/even class choser for row
 *
 * @param int $number Row number
 * @return string
 */
function cot_build_oddeven($number)
{
	return ($number % 2 == 0 ) ? 'even' : 'odd';
}

/**
 * Builds ratings for an item
 *
 * @param $code Item code
 * @param $url Base url
 * @param $display Display available for edit
 * @return array
 */
function cot_build_ratings($code, $url, $display)
{
	global $db_ratings, $db_rated, $db_users, $cfg, $usr, $sys, $L, $R;
	static $called = false;

	list($usr['auth_read_rat'], $usr['auth_write_rat'], $usr['isadmin_rat']) = cot_auth('ratings', 'a');

	if ($cfg['disable_ratings'] || !$usr['auth_read_rat'])
	{
		return (array('', ''));
	}

	if (COT_AJAX)
	{
		$rcode = cot_import('rcode', 'G', 'ALP');
		if (!empty($rcode))
		{
			$code = $rcode;
		}
	}

	$sql = cot_db_query("SELECT * FROM $db_ratings WHERE rating_code='$code' LIMIT 1");

	if ($row = cot_db_fetcharray($sql))
	{
		$rating_average = $row['rating_average'];
		$yetrated = TRUE;
		if ($rating_average<1)
		{
			$rating_average = 1;
		}
		elseif ($rating_average>10)
		{
			$rating_average = 10;
		}
		$rating_cntround = round($rating_average, 0);
	}
	else
	{
		$yetrated = FALSE;
		$rating_average = 0;
		$rating_cntround = 0;
	}

	if (COT_AJAX && !empty($rcode))
	{
		ob_clean();
		echo $rating_cntround;
		ob_flush();
		exit;
	}

	$rating_fancy =  '';
	for ($i = 1; $i <= 10; $i++)
	{
		$star_class = ($i <= $rating_cntround) ? 'star-rating star-rating-on' : 'star-rating star-rating-readonly';
		$star_margin = (in_array(($i / 2), array(1, 2, 3, 4, 5))) ? '-8' : '0';
		$rating_fancy .= '<div style="width: 8px;" class="'.$star_class.'"><a style="margin-left: '.$star_margin.'px;" title="'.$L['rat_choice'.$i].'">'.$i.'</a></div>';
	}
	if (!$display)
	{
		return array($rating_fancy, '');
	}

	$sep = (mb_strpos($url, '?') !== false) ? '&amp;' : '?';

	$inr = cot_import('inr', 'G', 'ALP');
	$newrate = cot_import('rate_'.$code,'P', 'INT');

	$newrate = (!empty($newrate)) ? $newrate : 0;

	if (!$cfg['ratings_allowchange'])
	{
		$alr_rated = cot_db_result(cot_db_query("SELECT COUNT(*) FROM ".$db_rated." WHERE rated_userid=".$usr['id']." AND rated_code = '".cot_db_prep($code)."'"), 0, 'COUNT(*)');
	}
	else
	{
		$alr_rated = 0;
	}

	if ($inr == 'send' && $newrate >= 0 && $newrate <= 10 && $usr['auth_write_rat'] && $alr_rated <= 0)
	{
		/* == Hook for the plugins == */
		foreach (cot_getextplugins('ratings.send.first') as $pl)
		{
			include $pl;
		}
		/* ===== */

		$sql = cot_db_query("DELETE FROM $db_rated WHERE rated_code='".cot_db_prep($code)."' AND rated_userid='".$usr['id']."' ");

		if (!$yetrated)
		{
			$sql = cot_db_query("INSERT INTO $db_ratings (rating_code, rating_state, rating_average, rating_creationdate, rating_text) VALUES ('".cot_db_prep($code)."', 0, ".(int)$newrate.", ".(int)$sys['now_offset'].", '') ");
		}

		$sql = ($newrate) ? cot_db_query("INSERT INTO $db_rated (rated_code, rated_userid, rated_value) VALUES ('".cot_db_prep($code)."', ".(int)$usr['id'].", ".(int)$newrate.")") : '';
		$sql = cot_db_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code'");
		$rating_voters = cot_db_result($sql, 0, "COUNT(*)");
		if ($rating_voters > 0)
		{
			$ratingnewaverage = cot_db_result(cot_db_query("SELECT AVG(rated_value) FROM $db_rated WHERE rated_code='$code'"), 0, "AVG(rated_value)");
			$sql = cot_db_query("UPDATE $db_ratings SET rating_average='$ratingnewaverage' WHERE rating_code='$code'");
		}
		else
		{
			$sql = cot_db_query("DELETE FROM $db_ratings WHERE rating_code='$code' ");
		}

		/* == Hook for the plugins == */
		foreach (cot_getextplugins('ratings.send.done') as $pl)
		{
			include $pl;
		}
		/* ===== */

		cot_redirect($url);
	}

	if ($usr['id'] > 0)
	{
		$sql1 = cot_db_query("SELECT rated_value FROM $db_rated WHERE rated_code='$code' AND rated_userid='".$usr['id']."' LIMIT 1");

		if ($row1 = cot_db_fetcharray($sql1))
		{
			$alreadyvoted = ($cfg['ratings_allowchange']) ? FALSE : TRUE;
			$rating_uservote = $L['rat_alreadyvoted']." (".$row1['rated_value'].")";
		}
	}

	$t = new XTemplate(cot_skinfile('ratings'));

	if (!$called && $usr['id'] > 0 && !$alreadyvoted)
	{
		// Link JS and CSS
		$sep = (mb_strpos($url, '?') !== false) ? '&' : '?';
		$t->assign('RATINGS_AJAX_REQUEST', $url.$sep.'ajax=1');
		$t->parse('RATINGS.RATINGS_INCLUDES');
		$called = true;
	}
	/* == Hook for the plugins == */
	foreach (cot_getextplugins('ratings.main') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$sep = (mb_strpos($url, '?') !== false) ? '&amp;' : '?';

	if ($yetrated)
	{
		$sql = cot_db_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code' ");
		$rating_voters = cot_db_result($sql, 0, "COUNT(*)");
		$rating_average = $row['rating_average'];
		$rating_since = $L['rat_since']." ".date($cfg['dateformat'], $row['rating_creationdate'] + $usr['timezone'] * 3600);
		if ($rating_average<1)
		{
			$rating_average = 1;
		}
		elseif ($ratingaverage > 10)
		{
			$rating_average = 10;
		}

		$rating = round($rating_average,0);
		$rating_averageimg = cot_rc('icon_rating_stars', array('val' => $rating));
		$sql = cot_db_query("SELECT COUNT(*) FROM $db_rated WHERE rated_code='$code' ");
		$rating_voters = cot_db_result($sql, 0, "COUNT(*)");
	}
	else
	{
		$rating_voters = 0;
		$rating_since = '';
		$rating_average = 0;
		$rating_averageimg = '';
	}

	$t->assign(array(
		'RATINGS_CODE' => $code,
		'RATINGS_AVERAGE' => $rating_average,
		'RATINGS_RATING' => $rating,
		'RATINGS_AVERAGEIMG' => $rating_averageimg,
		'RATINGS_VOTERS' => $rating_voters,
		'RATINGS_SINCE' => $rating_since,
		'RATINGS_FANCYIMG' => $rating_fancy,
		'RATINGS_USERVOTE' => $rating_uservote
	));

	/* == Hook for the plugins == */
	foreach (cot_getextplugins('ratings.tags') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$vote_block = ($usr['id'] > 0 && !$alreadyvoted) ? 'NOTVOTED.' : 'VOTED.';
	for ($i = 1; $i <= 10; $i++)
	{
		$checked = ($i == $rating_cntround) ? 'checked="checked"' : '';
		$t->assign(array(
			'RATINGS_ROW_VALUE' => $i,
			'RATINGS_ROW_TITLE' => $L['rat_choice'.$i],
			'RATINGS_ROW_CHECKED' => $checked,
		));
		$t->parse('RATINGS.'.$vote_block.'RATINGS_ROW');
	}
	if ($vote_block == 'NOTVOTED.')
	{
		$t->assign("RATINGS_FORM_SEND", $url.$sep.'inr=send');
		$t->parse('RATINGS.NOTVOTED');
	}
	else
	{
		$t->parse('RATINGS.VOTED');
	}
	$t->parse('RATINGS');
	$res = $t->text('RATINGS');

	return array($res, '', $rating_average);
}

/**
 * Returns stars image for user level
 *
 * @param int $level User level
 * @return unknown
 */
function cot_build_stars($level)
{
	global $theme, $R;

	if($level>0 and $level<100)
	{
		$stars = floor($level / 10) + 1;
		return cot_rc('icon_stars', array('val' => $stars));
	}
	else
	{
		return '';
	}
}

/**
 * Returns time gap between 2 dates
 *
 * @param int $t1 Stamp 1
 * @param int $t2 Stamp2
 * @return string
 */
function cot_build_timegap($t1,$t2)
{
	global $Ls;

	$gap = $t2 - $t1;

	if ($gap<=0 || !$t2 || $gap>94608000)
	{
		$result = '';
	}
	elseif ($gap<60)
	{
		$result = cot_declension($gap,$Ls['Seconds']);
	}
	elseif ($gap<3600)
	{
		$gap = floor($gap/60);
		$result = cot_declension($gap,$Ls['Minutes']);
	}
	elseif ($gap<86400)
	{
		$gap1 = floor($gap/3600);
		$gap2 = floor(($gap-$gap1*3600)/60);
		$result = cot_declension($gap1,$Ls['Hours']).' ';
		if ($gap2>0)
		{
			$result .= cot_declension($gap2,$Ls['Minutes']);
		}
	}
	else
	{
		$gap = floor($gap/86400);
		$result = cot_declension($gap,$Ls['Days']);
	}

	return $result;
}

/**
 * Returns user timezone offset
 *
 * @param int $tz Timezone
 * @return string
 */
function cot_build_timezone($tz)
{
	global $L;

	$result = 'GMT';

	$result .= cot_declension($tz,$Ls['Hours']);

	return $result;
}

/**
 * Returns link for URL
 *
 * @param string $text URL
 * @param int $maxlen Max. allowed length
 * @return unknown
 */
function cot_build_url($text, $maxlen=64)
{
	global $cfg;

	if (!empty($text))
	{
		if (mb_strpos($text, 'http://') !== 0)
		{
			$text='http://'. $text;
		}
		$text = htmlspecialchars($text);
		$text = cot_rc_link($text, cot_cutstring($text, $maxlen));
	}
	return $text;
}

/**
 * Returns link to user profile
 *
 * @param int $id User ID
 * @param string $user User name
 * @return string
 */
function cot_build_user($id, $user)
{
	global $cfg;

	if ($id == 0 && !empty($user))
	{
		return $user;
	}
	elseif ($id == 0)
	{
		return '';
	}
	else
	{
		return (!empty($user)) ? cot_rc_link(cot_url('users', 'm=details&id='.$id.'&u='.$user), $user) : '?';
	}
}

/**
 * Returns user avatar image
 *
 * @param string $image Image src
 * @return string
 */
function cot_build_userimage($image, $type = 'none')
{
	global $R;
	if (empty($image) && $type == 'avatar')
	{
		return $R['img_avatar_default'];
	}
	if (empty($type))
	{
		$type = 'none';
	}
	if (!empty($image))
	{
		return cot_rc("img_$type", array('src' => $image));
	}
	return '';
}

/**
 * Renders user signature text
 *
 * @param string $text Signature text
 * @return string
 */
function cot_build_usertext($text)
{
	global $cfg;
	if (!$cfg['usertextimg'])
	{
		$bbcodes_img = array(
			'\[img\]([^\[]*)\[/img\]' => '',
			'\[thumb=([^\[]*)\[/thumb\]' => '',
			'\[t=([^\[]*)\[/t\]' => '',
			'\[list\]' => '',
			'\[style=([^\[]*)\]' => '',
			'\[quote' => '',
			'\[code' => ''
		);

		foreach($bbcodes_img as $bbcode => $bbcodehtml)
		{
			$text = preg_replace("#$bbcode#i", $bbcodehtml, $text);
		}
	}
	return cot_parse($text, $cfg['parsebbcodeusertext'], $cfg['parsesmiliesusertext'], 1);
}

/**
 * Creates image thumbnail
 *
 * @param string $img_big Original image path
 * @param string $img_small Thumbnail path
 * @param int $small_x Thumbnail width
 * @param int $small_y Thumbnail height
 * @param bool $keepratio Keep original ratio
 * @param string $extension Image type
 * @param string $filen Original file name
 * @param int $fsize File size in kB
 * @param string $textcolor Text color
 * @param int $textsize Text size
 * @param string $bgcolor Background color
 * @param int $bordersize Border thickness
 * @param int $jpegquality JPEG quality in %
 * @param string $dim_priority Resize priority dimension
 */
function cot_createthumb($img_big, $img_small, $small_x, $small_y, $keepratio, $extension, $filen, $fsize, $textcolor, $textsize, $bgcolor, $bordersize, $jpegquality, $dim_priority="Width")
{
	if (!function_exists('gd_info'))
	{
		return;
	}

	global $cfg;

	$gd_supported = array('jpg', 'jpeg', 'png', 'gif');

	switch($extension)
	{
		case 'gif':
			$source = imagecreatefromgif ($img_big);
			break;

		case 'png':
			$source = imagecreatefrompng($img_big);
			break;

		default:
			$source = imagecreatefromjpeg($img_big);
			break;
	}

	$big_x = imagesx($source);
	$big_y = imagesy($source);

	if (!$keepratio)
	{
		$thumb_x = $small_x;
		$thumb_y = $small_y;
	}
	elseif ($dim_priority=="Width")
	{
		$thumb_x = $small_x;
		$thumb_y = floor($big_y * ($small_x / $big_x));
	}
	else
	{
		$thumb_x = floor($big_x * ($small_y / $big_y));
		$thumb_y = $small_y;
	}

	if ($textsize==0)
	{
		if ($cfg['th_amode']=='GD1')
		{
			$new = imagecreate($thumb_x+$bordersize*2, $thumb_y+$bordersize*2);
		}
		else
		{
			$new = imagecreatetruecolor($thumb_x+$bordersize*2, $thumb_y+$bordersize*2);
		}

		$background_color = imagecolorallocate ($new, $bgcolor[0], $bgcolor[1] ,$bgcolor[2]);
		imagefilledrectangle ($new, 0,0, $thumb_x+$bordersize*2, $thumb_y+$bordersize*2, $background_color);

		if ($cfg['th_amode']=='GD1')
		{
			imagecopyresized($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y);
		}
		else
		{
			imagecopyresampled($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y);
		}

	}
	else
	{
		if ($cfg['th_amode']=='GD1')
		{
			$new = imagecreate($thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*3.5+6);
		}
		else
		{
			$new = imagecreatetruecolor($thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*3.5+6);
		}

		$background_color = imagecolorallocate($new, $bgcolor[0], $bgcolor[1] ,$bgcolor[2]);
		imagefilledrectangle ($new, 0,0, $thumb_x+$bordersize*2, $thumb_y+$bordersize*2+$textsize*4+14, $background_color);
		$text_color = imagecolorallocate($new, $textcolor[0],$textcolor[1],$textcolor[2]);

		if ($cfg['th_amode']=='GD1')
		{
			imagecopyresized($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y);
		}
		else
		{
			imagecopyresampled($new, $source, $bordersize, $bordersize, 0, 0, $thumb_x, $thumb_y, $big_x, $big_y);
		}

		imagestring ($new, $textsize, $bordersize, $thumb_y+$bordersize+$textsize+1, $big_x."x".$big_y." ".$fsize."kb", $text_color);
	}

	switch($extension)
	{
		case 'gif':
			imagegif($new, $img_small);
			break;

		case 'png':
			imagepng($new, $img_small);
			break;

		default:
			imagejpeg($new, $img_small, $jpegquality);
			break;
	}

	imagedestroy($new);
	imagedestroy($source);
}

/**
 * Outputs standard javascript
 *
 * @param string $more Extra javascript
 * @return string
 */
function cot_javascript($more='')
{
	// TODO replace this function with JS/CSS proxy
	global $cfg, $lang;
	if ($cfg['jquery'])
	{
		$result .= '<script type="text/javascript" src="js/jquery.js"></script>';
		if ($cfg['turnajax'])
		{
			$result .= '<script type="text/javascript" src="js/jquery.history.js"></script>';
			$more .= empty($more) ? 'ajaxEnabled = true;' : "\najaxEnabled = true;";
		}
	}
	$result .= '<script type="text/javascript" src="js/base.js"></script>';
	if (!empty($more))
	{
		$result .= '<script type="text/javascript">
//<![CDATA[
'.$more.'
//]]>
</script>';
	}
	return $result;
}

/**
 * Returns Theme/Scheme selection dropdown
 *
 * @param string $selected_theme Seleced theme
 * @param string $selected_scheme Seleced color scheme
 * @param string $name Dropdown name
 * @return string
 */
function cot_selectbox_theme($selected_theme, $selected_scheme, $input_name)
{
	cot_require_api('extensions');
	$handle = opendir('./themes/');
	while ($f = readdir($handle))
	{
		if (mb_strpos($f, '.') === FALSE && is_dir('./themes/'.$f))
		{
			$themelist[] = $f;
		}
	}
	closedir($handle);
	sort($themelist);

	$values = array();
	$titles = array();
	foreach ($themelist as $i => $x)
	{
		$themeinfo = "./themes/$x/$x.php";
		if (file_exists($themeinfo))
		{
			$info = cot_infoget($themeinfo, 'COT_THEME');
			if ($info)
			{
				if (empty($info['Schemes']))
				{
					$values[] = "$x:default";
					$titles[] = $info['Name'];
				}
				else
				{
					$schemes = explode(',', $info['Schemes']);
					sort($schemes);
					foreach ($schemes as $sc)
					{
						$sc = explode(':', $sc);
						$values[] = $x . ':' . $sc[0];
						$titles[] = count($schemes) > 1 ? $info['Name'] .  ' (' . $sc[1] . ')' : $info['Name'];
					}
				}
			}
			else
			{
				$values[] = "$x:default";
				$titles[] = $x;
			}
		}
		else
		{
			$values[] = "$x:default";
			$titles[] = $x;
		}
	}

	return cot_selectbox("$selected_theme:$selected_scheme", $input_name, $values, $titles, false);
}

/*
 * ======================== Error & Message + Logs API ========================
*/

/**
 * Checks if there are messages to display
 *
 * @param string $src If non-emtpy, check messages in this specific source only
 * @param string $class If non-empty, check messages of this specific class only
 * @return bool
 */
function cot_check_messages($src = '', $class = '')
{
	global $error_string;

	if (empty($src) && empty($class))
	{
		return (is_array($_SESSION['cot_messages']) && count($_SESSION['cot_messages']) > 0)
			|| !empty($error_string);
	}

	if (!is_array($_SESSION['cot_messages']))
	{
		return false;
	}

	if (empty($src))
	{
		foreach ($_SESSION['cot_messages'] as $src => $grp)
		{
			foreach ($grp as $msg)
			{
				if ($msg['class'] == $class)
				{
					return true;
				}
			}
		}
	}
	elseif (empty($class))
	{
		return count($_SESSION['cot_messages'][$src]) > 0;
	}
	else
	{
		foreach ($_SESSION['cot_messages'][$src] as $msg)
		{
			if ($msg['class'] == $class)
			{
				return true;
			}
		}
	}

	return false;
}

/**
 * Clears error and other messages after they have bin displayed
 * @param string $src If non-emtpy, clear messages in this specific source only
 * @param string $class If non-empty, clear messages of this specific class only
 * @see cot_error()
 * @see cot_message()
 */
function cot_clear_messages($src = '', $class = '')
{
	global $error_string;

	if (empty($src) && empty($class))
	{
		unset($_SESSION['cot_messages']);
		unset($error_string);
	}

	if (!is_array($_SESSION['cot_messages']))
	{
		return;
	}

	if (empty($src))
	{
		foreach ($_SESSION['cot_messages'] as $src => $grp)
		{
			$new_grp = array();
			foreach ($grp as $msg)
			{
				if ($msg['class'] != $class)
				{
					$new_grp[] = $msg;
				}
			}
			if (count($new_grp) > 0)
			{
				$_SESSION['cot_messages'][$src] = $new_grp;
			}
			else
			{
				unset($_SESSION['cot_messages'][$src]);
			}
		}
	}
	elseif (empty($class))
	{
		unset($_SESSION['cot_messages'][$src]);
	}
	else
	{
		$new_grp = array();
		foreach ($_SESSION['cot_messages'][$src] as $msg)
		{
			if ($msg['class'] != $class)
			{
				$new_grp[] = $msg;
			}
		}
		if (count($new_grp) > 0)
		{
			$_SESSION['cot_messages'][$src] = $new_grp;
		}
		else
		{
			unset($_SESSION['cot_messages'][$src]);
		}
	}
}

/**
 * Terminates script execution and performs redirect
 *
 * @param bool $cond Really die?
 * @return bool
 */
function cot_die($cond=TRUE)
{
	if ($cond)
	{
		cot_redirect(cot_url('message', "msg=950", '', true));
	}
	return FALSE;
}

/**
 * Terminates script execution with fatal error
 *
 * @param string $text Reason
 * @param string $title Message title
 */
function cot_diefatal($text='Reason is unknown.', $title='Fatal error')
{
	global $cfg;

	if (defined('COT_DEBUG') && COT_DEBUG)
	{
		echo '<br /><pre>';
		debug_print_backtrace();
		echo '</pre>';
	}

	$disp = "<strong><a href=\"".$cfg['mainurl']."\">".$cfg['maintitle']."</a></strong><br />";
	$disp .= @date('Y-m-d H:i').'<br />'.$title.' : '.$text;
	die($disp);
}

/**
 * Terminates with "disabled" error
 *
 * @param bool $disabled
 */
function cot_dieifdisabled($disabled)
{
	if ($disabled)
	{
		cot_redirect(cot_url('message', "msg=940", '', true));
	}
}

/**
 * Renders different messages on page
 *
 * @param XTemplate $tpl Current template object reference
 */
function cot_display_messages($tpl)
{
	global $L;
	if (!cot_check_messages())
	{
		return;
	}
	$errors = cot_get_messages('', 'error');
	if (count($errors) > 0)
	{
		foreach ($errors as $msg)
		{
			$text = isset($L[$msg['text']]) ? $L[$msg['text']] : $msg['text'];
			$tpl->assign('ERROR_ROW_MSG', $text);
			$tpl->parse('MAIN.ERROR.ERROR_ROW');
		}
		$tpl->parse('MAIN.ERROR');
	}
	$warnings = cot_get_messages('', 'warning');
	if (count($warnings) > 0)
	{
		foreach ($warnings as $msg)
		{
			$text = isset($L[$msg['text']]) ? $L[$msg['text']] : $msg['text'];
			$tpl->assign('WARNING_ROW_MSG', $text);
			$tpl->parse('MAIN.WARNING.WARNING_ROW');
		}
		$tpl->parse('MAIN.WARNING');
	}
	$okays = cot_get_messages('', 'ok');
	if (count($okays) > 0)
	{
		foreach ($okays as $msg)
		{
			$text = isset($L[$msg['text']]) ? $L[$msg['text']] : $msg['text'];
			$tpl->assign('DONE_ROW_MSG', $text);
			$tpl->parse('MAIN.DONE.DONE_ROW');
		}
		$tpl->parse('MAIN.DONE');
	}
	cot_clear_messages();
}

/**
 * Records an error message to be displayed on results page
 * @param string $message Message lang string code or full text
 * @param string $src Error source identifier, such as field name for invalid input
 * @see cot_message()
 */
function cot_error($message, $src = 'default')
{
	global $cot_error;
	$cot_error ? $cot_error++ : $cot_error = 1;
	cot_message($message, 'error', $src);
}

/**
 * Returns an array of messages for a specific source and/or class
 *
 * @param string $src Message source identifier. Search in all sources if empty
 * @param string $class Message class. Search for all classes if empty
 * @return array Array of message strings
 */
function cot_get_messages($src = 'default', $class = '')
{
	$messages = array();
	if (empty($src) && empty($class))
	{
		return $_SESSION['cot_messages'];
	}

	if (!is_array($_SESSION['cot_messages']))
	{
		return $messages;
	}

	if (empty($src))
	{
		foreach ($_SESSION['cot_messages'] as $src => $grp)
		{
			foreach ($grp as $msg)
			{
				if (!empty($class) && $msg['class'] != $class)
				{
					continue;
				}
				$messages[] = $msg;
			}
		}
	}
	elseif (is_array($_SESSION['cot_messages'][$src]))
	{
		if (empty($class))
		{
			return $_SESSION['cot_messages'][$src];
		}
		else
		{
			foreach ($_SESSION['cot_messages'][$src] as $msg)
			{
				if ($msg['class'] != $class)
				{
					continue;
				}
				$messages[] = $msg;
			}
		}
	}
	return $messages;
}

/**
 * Collects all messages and implodes them into a single string
 * @param string $src Origin of the target messages
 * @param string $class Group messages of selected class only. Empty to group all
 * @return string Composite HTML string
 * @see cot_error()
 * @see cot_get_messages()
 * @see cot_message()
 */
function cot_implode_messages($src = 'default', $class = '')
{
	global $R, $L, $error_string;
	$res = '';

	if (!is_array($_SESSION['cot_messages']))
	{
		return;
	}

	$messages = cot_get_messages($src, $class);
	foreach ($messages as $msg)
	{
		$text = isset($L[$msg['text']]) ? $L[$msg['text']] : $msg['text'];
		$res .= cot_rc('code_msg_line', array('class' => $msg['class'], 'text' => $text));
	}

	if (!empty($error_string) && (empty($class) || $class == 'error'))
	{
		$res .= cot_rc('code_msg_line', array('class' => 'error', 'text' => $error_string));
	}
	return empty($res) ? '' : cot_rc('code_msg_begin', array('class' => empty($class) ? 'message' : $class))
		. $res . $R['code_msg_end'];
}

/**
 * Logs an event
 *
 * @param string $text Event description
 * @param string $group Event group
 */
function cot_log($text, $group='def')
{
	global $db_logger, $sys, $usr, $_SERVER;

	$sql = cot_db_query("INSERT INTO $db_logger (log_date, log_ip, log_name, log_group, log_text) VALUES (".(int)$sys['now_offset'].", '".$usr['ip']."', '".cot_db_prep($usr['name'])."', '$group', '".cot_db_prep($text.' - '.$_SERVER['REQUEST_URI'])."')");
}

/**
 * Logs wrong input
 *
 * @param string $s Source type
 * @param string $e Filter type
 * @param string $v Variable name
 * @param string $o Value
 */
function cot_log_import($s, $e, $v, $o)
{
	$text = "A variable type check failed, expecting ".$s."/".$e." for '".$v."' : ".$o;
	cot_log($text, 'sec');
}

/**
 * Records a generic message to be displayed on results page
 * @param string $text Message lang string code or full text
 * @param string $class Message class: 'status', 'error', 'ok', 'notice', etc.
 * @param string $src Message source identifier
 * @see cot_error()
 */
function cot_message($text, $class = 'ok', $src = 'default')
{
	global $cfg;
	if (!$cfg['msg_separate'])
	{
		// Force the src to default if all errors are displayed in the same place
		$src = 'default';
	}
	$_SESSION['cot_messages'][$src][] = array(
		'text' => $text,
		'class' => $class
	);
}

/*
 * =============================== File Path Functions ========================
*/

/**
 * Returns path to include file
 *
 * @param string $extension Extension name
 * @param string $part Name of the extension part
 * @param bool $is_plugin TRUE if extension is a plugin, FALSE if it is a module
 * @return string File path
 */
function cot_incfile($extension, $part, $is_plugin = false)
{
	global $cfg;
	if ($is_plugin)
	{
		return $cfg['plugins_dir']."/$extension/inc/$extension.$part.php";
	}
	elseif ($extension == 'admin' || $extension == 'users' || $extension == 'message')
	{
		return $cfg['system_dir']."/$extension/$extension.$part.php";
	}
	else
	{
		return $cfg['modules_dir']."/$extension/inc/$extension.$part.php";
	}
}

/**
 * Returns a language file path for a plugin or FALSE on error.
 *
 * @param string $name Plugin name
 * @param bool $type Langfile type: 'plug', 'module' or 'core'
 * @param mixed $default Default (fallback) language code
 * @return bool
 */
function cot_langfile($name, $type = 'plug', $default = 'en')
{
	global $cfg, $lang;
	if ($type == 'module')
	{
		if (@file_exists($cfg['modules_dir']."/$name/lang/$name.$lang.lang.php"))
		{
			return $cfg['modules_dir']."/$name/lang/$name.$lang.lang.php";
		}
		else
		{
			return $cfg['modules_dir']."/$name/lang/$name.$default.lang.php";
		}
	}
	elseif ($type == 'core')
	{
		if (@file_exists($cfg['lang_dir']."/$lang/$name.$lang.lang.php"))
		{
			return $cfg['lang_dir']."/$lang/$name.$lang.lang.php";
		}
		else
		{
			return $cfg['lang_dir']."/$default/$name.$default.lang.php";
		}
	}
	else
	{
		if (@file_exists($cfg['plugins_dir']."/$name/lang/$name.$lang.lang.php"))
		{
			return $cfg['plugins_dir']."/$name/lang/$name.$lang.lang.php";
		}
		else
		{
			return $cfg['plugins_dir']."/$name/lang/$name.$default.lang.php";
		}
	}
}

/**
 * Requires an extension API and its attendant files
 *
 * @param string $name Extension name
 * @param bool $is_plugin TRUE if extension is a plugin, FALSE if it is a module
 * @param string $part Extension part
 */
function cot_require($name, $is_plugin = false, $part = 'functions')
{
	require_once cot_incfile($name, $part, $is_plugin);
}

/**
 * Requires a core API
 *
 * @param string $api_name API name
 */
function cot_require_api($api_name)
{
	global $cfg;
	require_once $cfg['system_dir'] . "/$api_name.php";
}

/**
 * Loads a requested language file into global $L array if it is not already there.
 *
 * @param string $name Extension name
 * @param bool $type Langfile type: 'plug', 'module' or 'core'
 * @param mixed $default Default (fallback) language code
 * @see cot_langfile()
 */
function cot_require_lang($name, $type = 'plug', $default = 'en')
{
	global $cfg, $L, $Ls, $R, $themelang;
	require_once cot_langfile($name, $type, $default);
}

/**
 * Loads a requested resource file into global $L array if it is not already there.
 *
 * @param string $name Extension name
 * @param bool $is_plugin TRUE if extension is a plugin, FALSE if it is a module
 */
function cot_require_rc($name, $is_plugin = false)
{
	global $cfg, $L, $Ls, $R, $themelang;
	require_once cot_incfile($name, 'resources', $is_plugin);
}

/**
 * Tries to detect and fetch a user scheme or returns FALSE on error.
 *
 * @global array $usr User object
 * @global array $cfg Configuration
 * @global array $out Output vars
 * @return mixed
 */
function cot_schemefile()
{
	global $usr, $cfg, $out;

	if (file_exists('./themes/'.$usr['theme'].'/'.$usr['scheme'].'.css'))
	{
		return './themes/'.$usr['theme'].'/'.$usr['scheme'].'.css';
	}
	elseif (file_exists('./themes/'.$usr['theme'].'/css/'))
	{
		if (file_exists('./themes/'.$usr['theme'].'/css/'.$usr['scheme'].'.css'))
		{
			return './themes/'.$usr['theme'].'/css/'.$usr['scheme'].'.css';
		}
		elseif (file_exists('./themes/'.$usr['theme'].'/css/'.$cfg['defaultscheme'].'.css'))
		{
			$out['notices'] .= $L['com_schemefail'];
			$usr['scheme'] = $cfg['defaultscheme'];
			return './themes/'.$usr['theme'].'/css/'.$cfg['defaultscheme'].'.css';
		}
	}
	elseif (file_exists('./themes/'.$usr['theme']))
	{
		if (file_exists('./themes/'.$usr['theme'].'/'.$cfg['defaultscheme'].'.css'))
		{
			$out['notices'] .= $L['com_schemefail'];
			$usr['scheme'] = $cfg['defaultscheme'];
			return './themes/'.$usr['theme'].'/'.$cfg['defaultscheme'].'.css';
		}
		elseif (file_exists('./themes/'.$usr['theme'].'/'.$usr['theme'].'.css'))
		{
			$out['notices'] .= $L['com_schemefail'];
			$usr['scheme'] = $usr['theme'];
			return './themes/'.$usr['theme'].'/'.$usr['theme'].'.css';
		}
		elseif (file_exists('./themes/'.$usr['theme'].'/style.css'))
		{
			$out['notices'] .= $L['com_schemefail'];
			$usr['scheme'] = 'style';
			return './themes/'.$usr['theme'].'/style.css';
		}
	}

	$out['notices'] .= $L['com_schemefail'];
	if (file_exists('./themes/'.$cfg['defaulttheme'].'/'.$cfg['defaultscheme'].'.css'))
	{
		$usr['theme'] = $cfg['defaulttheme'];
		$usr['scheme'] = $cfg['defaultscheme'];
		return './themes/'.$cfg['defaulttheme'].'/'.$cfg['defaultscheme'].'.css';
	}
	elseif (file_exists('./themes/'.$cfg['defaulttheme'].'/css/'.$cfg['defaultscheme'].'.css'))
	{
		$usr['theme'] = $cfg['defaulttheme'];
		$usr['scheme'] = $cfg['defaultscheme'];
		return './themes/'.$cfg['defaulttheme'].'/css/'.$cfg['defaultscheme'].'.css';
	}
	else
	{
		return false;
	}
}

/**
 * Returns skin file path
 *
 * @param mixed $base Item name (string), or base names (array)
 * @param mixed $plug Plugin flag (bool), or '+' (string) to probe plugin
 * @return string
 */
function cot_skinfile($base, $plug = false)
{
	global $usr, $cfg;

	if (is_string($base) && mb_strpos($base, '.') !== false)
	{
		$base = explode('.', $base);
	}
	if (!is_array($base))
	{
		$base = array($base);
	}

	$basename = $base[0];

	if ((defined('COT_ADMIN')
		|| defined('COT_MESSAGE') && $_SESSION['s_run_admin']))
	{
		$admn = true;
	}

	if ($plug === '+')
	{
		$plug = false;
		if (defined('COT_PLUG'))
		{
			global $e;

			if (!empty($e))
			{
				$plug = true;
				$basename = $e;
				if ($cfg['enablecustomhf'])
				{
					$base[] = $e;
				}
			}
		}
	}

	if ($plug === true)
	{
		$scan_prefix[] = './themes/'.$usr['theme'].'/plugins/';
		if ($usr['theme'] != $cfg['defaulttheme'])
		{
			$scan_prefix[] = './themes/'.$cfg['defaulttheme'].'/plugins/';
		}
		$scan_prefix[] = $cfg['plugins_dir'].'/'.$basename.'/tpl/';
	}
	else
	{
		$scan_prefix[] = './themes/'.$usr['theme'].'/'.$basename.'/';
		if ($usr['theme'] != $cfg['defaulttheme'])
		{
			$scan_prefix[] = './themes/'.$cfg['defaulttheme'].'/'.$basename.'/';
		}
		if ((defined('COT_ADMIN') && $plug !== 'module'
			|| defined('COT_MESSAGE') && $_SESSION['s_run_admin']))
		{
			$scan_prefix[] = $cfg['system_dir'].'/admin/tpl/';
		}
		elseif (defined('COT_USERS'))
		{
			$scan_prefix[] = $cfg['system_dir'].'/users/tpl/';
		}
		else
		{
			$scan_prefix[] = $cfg['modules_dir'].'/'.$basename.'/tpl/';
		}
	}
	$scan_prefix[] = './themes/'.$usr['theme'].'/';
	if ($usr['theme'] != $cfg['defaulttheme'])
	{
		$scan_prefix[] = './themes/'.$cfg['defaulttheme'].'/';
	}

	$base_depth = count($base);
	for ($i = $base_depth; $i > 0; $i--)
	{
		$levels = array_slice($base, 0, $i);
		$themefile = implode('.', $levels).'.tpl';
		foreach ($scan_prefix as $pfx)
		{
			if (file_exists($pfx.$themefile))
			{
				return $pfx.$themefile;
			}
		}
	}

//	throw new Exception('Template file <em>'.implode('.', $base).'.tpl</em> was not found. Please check your theme.');
	return '';
}

/*
 * ============================ Date and Time Functions =======================
*/

/**
 * Creates UNIX timestamp out of a date
 *
 * @param int $hour Hours
 * @param int $minute Minutes
 * @param int $second Seconds
 * @param int $month Month
 * @param int $date Day of the month
 * @param int $year Year
 * @return int
 */
function cot_mktime($hour = false, $minute = false, $second = false, $month = false, $date = false, $year = false)
{
	if ($hour === false)  $hour  = date ('G');
	if ($minute === false) $minute = date ('i');
	if ($second === false) $second = date ('s');
	if ($month === false)  $month  = date ('n');
	if ($date === false)  $date  = date ('j');
	if ($year === false)  $year  = date ('Y');

	return mktime ((int) $hour, (int) $minute, (int) $second, (int) $month, (int) $date, (int) $year);
}

/**
 * Converts MySQL date into UNIX timestamp
 *
 * @param string $date Date in MySQL format
 * @return int UNIX timestamp
 */
function cot_date2stamp($date)
{
	if ($date == '0000-00-00') return 0;
	preg_match('#(\d{4})-(\d{2})-(\d{2})#', $date, $m);
	return mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * Converts UNIX timestamp into MySQL date
 *
 * @param int $stamp UNIX timestamp
 * @return string MySQL date
 */
function cot_stamp2date($stamp)
{
	return date('Y-m-d', $stamp);
}

/*
 * ================================== Pagination ==============================
*/

/**
 * Page navigation (pagination) builder. Uses URL transformation and resource strings,
 * returns an associative array, containing:
 * ['prev'] - first and previous page buttons
 * ['main'] - buttons with page numbers, including current
 * ['next'] - next and last page buttons
 * ['last'] - last page with number
 *
 * @param string $module Site area or script name
 * @param mixed $params URL parameters as array or parameter string
 * @param int $current Current page number
 * @param int $entries Total rows
 * @param int $perpage Rows per page
 * @param string $characters It is symbol for parametre which transfer pagination
 * @param string $hash Hash part of the url (including #)
 * @param bool $ajax Add AJAX support
 * @param string $target_div Target div ID if $ajax is true
 * @param string $ajax_module Site area name for ajax if different from $module
 * @param string $ajax_params URL parameters for ajax if $ajax_module is not empty
 * @return array
 */
function cot_pagenav($module, $params, $current, $entries, $perpage, $characters = 'd', $hash = '',
	$ajax = false, $target_div = '', $ajax_module = '', $ajax_params = array())
{
	if (function_exists('cot_pagenav_custom'))
	{
		// For custom pagination functions in plugins
		return cot_pagenav_custom($module, $params, $current, $entries, $perpage, $characters, $hash,
			$ajax, $target_div, $ajax_module, $ajax_params);
	}

	if ($entries <= $perpage)
	{
		return '';
	}

	global $L, $R;

	$each_side = 3; // Links each side

	is_array($params) ? $args = $params : parse_str($params, $args);
	if ($ajax)
	{
		$ajax_rel = !empty($ajax_module);
		$ajax_rel && is_string($ajax_params) ? parse_str($ajax_params, $ajax_args) : $ajax_args = $ajax_params;
		$event = ' class="ajax"';
		if (empty($target_div))
		{
			$base_rel = $ajax_rel ? ' rel="get;' : '';
		}
		else
		{
			$base_rel = $ajax_rel ? ' rel="get-'.$target_div.';' : ' rel="get-'.$target_div.'"';
		}
	}
	else
	{
		$ajax_rel = false;
		$event = '';
	}
	$rel = '';

	$totalpages = ceil($entries / $perpage);
	$currentpage = floor($current / $perpage) + 1;
	$cur_left = $currentpage - $each_side;
	if ($cur_left < 1) $cur_left = 1;
	$cur_right = $currentpage + $each_side;
	if ($cur_right > $totalpages) $cur_right = $totalpages;

	// Main block

	$before = '';
	$pages = '';
	$after = '';
	$i = 1;
	$n = 0;
	while ($i < $cur_left)
	{
		$args[$characters] = ($i - 1) * $perpage;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$before .= cot_rc('link_pagenav_main', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $i
		));
		if ($i < $cur_left - 2)
		{
			$before .= $R['link_pagenav_gap'];
		}
		elseif ($i == $cur_left - 2)
		{
			$args[$characters] = $i * $perpage;
			if ($ajax_rel)
			{
				$ajax_args[$characters] = $args[$characters];
				$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
			}
			else
			{
				$rel = $base_rel;
			}
			$before .= cot_rc('link_pagenav_main', array(
				'url' => cot_url($module, $args, $hash),
				'event' => $event,
				'rel' => $rel,
				'num' => $i + 1
			));
		}
		$i *= ($n % 2) ? 2 : 5;
		$n++;
	}
	for ($j = $cur_left; $j <= $cur_right; $j++)
	{
		$args[$characters] = ($j - 1) * $perpage;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$rc = $j == $currentpage ? 'current' : 'main';
		$pages .= cot_rc('link_pagenav_'.$rc, array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $j
		));
	}
	while ($i <= $cur_right)
	{
		$i *= ($n % 2) ? 2 : 5;
		$n++;
	}
	while ($i < $totalpages)
	{
		if ($i > $cur_right + 2)
		{
			$after .= $R['link_pagenav_gap'];
		}
		elseif ($i == $cur_right + 2)
		{
			$args[$characters] = ($i - 2 ) * $perpage;
			if ($ajax_rel)
			{
				$ajax_args[$characters] = $args[$characters];
				$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
			}
			else
			{
				$rel = $base_rel;
			}
			$after .= cot_rc('link_pagenav_main', array(
				'url' => cot_url($module, $args, $hash),
				'event' => $event,
				'rel' => $rel,
				'num' => $i - 1
			));
		}
		$args[$characters] = ($i - 1) * $perpage;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$after .= cot_rc('link_pagenav_main', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $i
		));
		$i *= ($n % 2) ? 2 : 5;
		$n++;
	}
	$pages = $before.$pages.$after;

	// Previous/next

	if ($current > 0)
	{
		$prev_n = $current - $perpage;
		if ($prev_n < 0)
		{
			$prev_n = 0;
		}
		$args[$characters] = $prev_n;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$prev = cot_rc('link_pagenav_prev', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $prev_n + 1
		));
		$args[$characters] = 0;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$first = cot_rc('link_pagenav_first', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => 1
		));
	}

	if (($current + $perpage) < $entries)
	{
		$next_n = $current + $perpage;
		$args[$characters] = $next_n;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$next = cot_rc('link_pagenav_next', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $next_n + 1
		));
		$last_n = ($totalpages - 1) * $perpage;
		$args[$characters] = $last_n;
		if ($ajax_rel)
		{
			$ajax_args[$characters] = $args[$characters];
			$rel = $base_rel.str_replace('?', ';', cot_url($ajax_module, $ajax_args)).'"';
		}
		else
		{
			$rel = $base_rel;
		}
		$last = cot_rc('link_pagenav_last', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $last_n + 1
		));
		$lastn  = (($last +  $perpage)<$totalpages) ?
			cot_rc('link_pagenav_main', array(
			'url' => cot_url($module, $args, $hash),
			'event' => $event,
			'rel' => $rel,
			'num' => $last_n / $perpage + 1
			)): FALSE;
	}

	return array(
		'prev' => $first.$prev,
		'main' => $pages,
		'next' => $next.$last,
		'last' => $lastn
	);
}

/*
 * ============================== Resource Strings ============================
*/

/**
 * Resource string formatter function. Takes a string with predefined variable substitution, e.g.
 * 'My {$pet} likes {$food}. And {$pet} is hungry!' and an assotiative array of substitution values, e.g.
 * array('pet' => 'rabbit', 'food' => 'carrots') and assembles a formatted result. If {$var} cannot be found
 * in $args, it will be taken from global scope. You can also use parameter strings instead of arrays, e.g.
 * 'pet=rabbit&food=carrots'. Or omit the second parameter in case all substitutions are globals.
 *
 * @global array $R Resource strings
 * @global array $L Language strings, support resource sequences too
 * @param string $name Name of the $R item or a resource string itself
 * @param array $params Associative array of arguments or a parameter string
 * @return string Assembled resource string
 */
function cot_rc($name, $params = array())
{
	global $R, $L;
	$res = isset($R[$name]) ? $R[$name]
		: (isset($L[$name]) ? $L[$name] : $name);
	is_array($params) ? $args = $params : mb_parse_str($params, $args);
	if (preg_match_all('#\{\$(.+?)\}#', $res, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $m)
		{
			$var = $m[1];
			$res = str_replace($m[0], (isset($args[$var]) ? $args[$var] : $GLOBALS[$var]), $res);
		}
	}
	return $res;
}

/**
 * Converts custom attributes to a string if necessary
 *
 * @param mixed $attrs A string or associative array
 * @return string
 */
function cot_rc_attr_string($attrs)
{
	$attr_str = '';
	if (is_array($attrs))
	{
		foreach ($attrs as $key => $val)
		{
			$attr_str .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
		}
	}
	else
	{
		$attr_str = $attrs;
	}
	return $attr_str;
}

/**
 * Quick link resource pattern
 *
 * @param string $url Link href
 * @param string $text Tag contents
 * @param mixed $attrs Additional attributes as a string or an associative array
 * @return string HTML link
 */
function cot_rc_link($url, $text, $attrs = '')
{
	$link_attrs = cot_rc_attr_string($attrs);
	return '<a href="'.$url.'"'.$link_attrs.'>'.$text.'</a>';
}

/*
 * ========================== Security Shield =================================
*/

/**
 * Checks GET anti-XSS parameter
 *
 * @return bool
 */
function cot_check_xg()
{
	global $sys;
	$x = cot_import('x', 'G', 'ALP');
	if ($x != $sys['xk'] && (empty($sys['xk_prev']) || $x != $sys['xk_prev']))
	{
		cot_redirect(cot_url('message', 'msg=950', '', true));
		return false;
	}
	return true;
}

/**
 * Checks POST anti-XSS parameter
 *
 * @return bool
 */
function cot_check_xp()
{
	return (defined('COT_NO_ANTIXSS') || defined('COT_AUTH')) ?
		($_SERVER['REQUEST_METHOD'] == 'POST') : isset($_POST['x']);
}

/**
 * Clears current user action in Who's online.
 *
 */
function cot_shield_clearaction()
{
	global  $db_online, $usr;

	$sql = cot_db_query("UPDATE $db_online SET online_action='' WHERE online_ip='".$usr['ip']."'");
}

/**
 * Anti-hammer protection
 *
 * @param int $hammer Hammer rate
 * @param string $action Action type
 * @param int $lastseen User last seen timestamp
 * @return int
 */
function cot_shield_hammer($hammer,$action, $lastseen)
{
	global $cfg, $sys, $usr;

	if ($action=='Hammering')
	{
		cot_shield_protect();
		cot_shield_clearaction();
		cot_stat_inc('totalantihammer');
	}

	if (($sys['now']-$lastseen)<4)
	{
		$hammer++;
		if ($hammer>$cfg['shieldzhammer'])
		{
			cot_shield_update(180, 'Hammering');
			cot_log('IP banned 3 mins, was hammering', 'sec');
			$hammer = 0;
		}
	}
	else
	{
		if ($hammer>0)
		{
			$hammer--;
		}
	}
	return($hammer);
}

/**
 * Warn user of shield protection
 *
 */
function cot_shield_protect()
{
	global $cfg, $sys, $online_count, $shield_limit, $shield_action;

	if ($cfg['shieldenabled'] && $online_count>0 && $shield_limit>$sys['now'])
	{
		cot_diefatal('Shield protection activated, please retry in '.($shield_limit-$sys['now']).' seconds...<br />After this duration, you can refresh the current page to continue.<br />Last action was : '.$shield_action);
	}
}

/**
 * Updates shield state
 *
 * @param int $shield_add Hammer
 * @param string $shield_newaction New action type
 */
function cot_shield_update($shield_add, $shield_newaction)
{
	global $cfg, $usr, $sys, $db_online;
	if ($cfg['shieldenabled'])
	{
		$shield_newlimit = $sys['now'] + floor($shield_add * $cfg['shieldtadjust'] /100);
		$sql = cot_db_query("UPDATE $db_online SET online_shield='$shield_newlimit', online_action='$shield_newaction' WHERE online_ip='".$usr['ip']."'");
	}
}

/**
 * Returns XSS protection variable for GET URLs
 *
 * @return unknown
 */
function cot_xg()
{
	global $sys;
	return ('x='.$sys['xk']);
}

/**
 * Returns XSS protection field for POST forms
 *
 * @return string
 */
function cot_xp()
{
	global $sys;
	return '<div style="display:inline;margin:0;padding:0"><input type="hidden" name="x" value="'.$sys['xk'].'" /></div>';
}


/*
 * =============================== Statistics API =============================
*/

/**
 * Creates new stats parameter
 *
 * @param string $name Parameter name
 */
function cot_stat_create($name)
{
	global $db_stats;

	cot_db_query("INSERT INTO $db_stats (stat_name, stat_value) VALUES ('".cot_db_prep($name)."', 1)");
}

/**
 * Returns statistics parameter
 *
 * @param string $name Parameter name
 * @return int
 */
function cot_stat_get($name)
{
	global $db_stats;

	$sql = cot_db_query("SELECT stat_value FROM $db_stats where stat_name='$name' LIMIT 1");
	return (cot_db_numrows($sql) > 0) ? (int) cot_db_result($sql, 0, 'stat_value') : FALSE;
}

/**
 * Increments stats
 *
 * @param string $name Parameter name
 * @param int $value Increment step
 */
function cot_stat_inc($name, $value = 1)
{
	global $db_stats;
	cot_db_query("UPDATE $db_stats SET stat_value=stat_value+$value WHERE stat_name='$name'");
}

/**
 * Inserts new stat or increments value if it already exists
 *
 * @param string $name Parameter name
 * @param int $value Increment step
 */
function cot_stat_update($name, $value = 1)
{
	global $db_stats;
	cot_db_query("INSERT INTO $db_stats (stat_name, stat_value)
		VALUES ('".cot_db_prep($name)."', 1)
		ON DUPLICATE KEY UPDATE stat_value=stat_value+$value");
}

/*
 * ============================ URL and URI ===================================
*/

/**
 * Loads URL Transformation Rules
 */
function cot_load_urltrans()
{
	global $cot_urltrans;
	$cot_urltrans = array();
	$fp = fopen('./datas/urltrans.dat', 'r');
	// Rules
	while ($line = trim(fgets($fp), " \t\r\n"))
	{
		$parts = explode("\t", $line);
		$rule = array();
		$rule['trans'] = $parts[2];
		$parts[1] == '*' ? $rule['params'] = array() : mb_parse_str($parts[1], $rule['params']);
		foreach($rule['params'] as $key => $val)
		{
			if (mb_strpos($val, '|') !== false)
			{
				$rule['params'][$key] = explode('|', $val);
			}
		}
		$cot_urltrans[$parts[0]][] = $rule;
	}
	fclose($fp);
}

/**
 * Displays redirect page
 *
 * @param string $url Target URI
 */
function cot_redirect($url)
{
	global $cfg, $cot_error;

	if ($cot_error && $_SERVER['REQUEST_METHOD'] == 'POST')
	{
		// Save the POST data
		cot_import_buffer_save();
	}

	if (!cot_url_check($url))
	{
		$url = COT_ABSOLUTE_URL . $url;
	}

	if ($cfg['redirmode'])
	{
		$output = $cfg['doctype'].<<<HTM
		<html>
		<head>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<meta http-equiv="refresh" content="0; url=$url" />
		<title>Redirecting...</title></head>
		<body>Redirecting to <a href="$url">$url</a>
		</body>
		</html>
HTM;
		header('Refresh: 0; URL='.$url);
		echo $output;
		exit;
	}
	else
	{
		header('Location: '.$url);
		exit;
	}
}

/**
 * Transforms parameters into URL by following user-defined rules
 *
 * @param string $name Site area or script name
 * @param mixed $params URL parameters as array or parameter string
 * @param string $tail URL postfix, e.g. anchor
 * @param bool $header Set this TRUE if the url will be used in HTTP header rather than body output
 * @return string
 */
function cot_url($name, $params = '', $tail = '', $header = false)
{
	global $cfg, $cot_urltrans;
	// Preprocess arguments
	is_array($params) ? $args = $params : mb_parse_str($params, $args);
	$area = empty($cot_urltrans[$name]) ? '*' : $name;
	// Find first matching rule
	$url = $cot_urltrans['*'][0]['trans']; // default rule
	$rule = array();
	if (!empty($cot_urltrans[$area]))
	{
		foreach($cot_urltrans[$area] as $rule)
		{
			$matched = true;
			foreach($rule['params'] as $key => $val)
			{
				if (empty($args[$key])
					|| (is_array($val) && !in_array($args[$key], $val))
					|| ($val != '*' && $args[$key] != $val))
				{
					$matched = false;
					break;
				}
			}
			if ($matched)
			{
				$url = $rule['trans'];
				break;
			}
		}
	}
	// Some special substitutions
	$mainurl = parse_url($cfg['mainurl']);
	$spec['_area'] = $name;
	$spec['_zone'] = $name;
	$spec['_host'] = $mainurl['host'];
	$spec['_rhost'] = $_SERVER['HTTP_HOST'];
	$spec['_path'] = COT_SITE_URI;
	// Transform the data into URL
	if (preg_match_all('#\{(.+?)\}#', $url, $matches, PREG_SET_ORDER))
	{
		foreach($matches as $m)
		{
			if ($p = mb_strpos($m[1], '('))
			{
				// Callback
				$func = mb_substr($m[1], 0, $p);
				$url = str_replace($m[0], $func($args, $spec), $url);
			}
			elseif (mb_strpos($m[1], '!$') === 0)
			{
				// Unset
				$var = mb_substr($m[1], 2);
				$url = str_replace($m[0], '', $url);
				unset($args[$var]);
			}
			else
			{
				// Substitute
				$var = mb_substr($m[1], 1);
				if (isset($spec[$var]))
				{
					$url = str_replace($m[0], urlencode($spec[$var]), $url);
				}
				elseif (isset($args[$var]))
				{
					$url = str_replace($m[0], urlencode($args[$var]), $url);
					unset($args[$var]);
				}
				else
				{
					$url = str_replace($m[0], urlencode($GLOBALS[$var]), $url);
				}
			}
		}
	}
	// Append query string if needed
	if (!empty($args))
	{
		$sep = $header ? '&' : '&amp;';
		$sep_len = strlen($sep);
		$qs = mb_strpos($url, '?') !== false ? $sep : '?';
		foreach($args as $key => $val)
		{
			// Exclude static parameters that are not used in format,
			// they should be passed by rewrite rule (htaccess)
			if ($rule['params'][$key] != $val)
			{
				$qs .= $key .'='.urlencode($val).$sep;
			}
		}
		$qs = substr($qs, 0, -$sep_len);
		$url .= $qs;
	}
	// Almost done
	$url .= $tail;
	$url = str_replace('&amp;amp;', '&amp;', $url);
	return $url;
}

/**
 * Checks if an absolute URL belongs to current site or its subdomains
 *
 * @param string $url Absolute URL
 * @return bool
 */
function cot_url_check($url)
{
	global $sys;
	return preg_match('`^'.preg_quote($sys['scheme'].'://').'([^/]+\.)?'.preg_quote($sys['domain']).'`i', $url);
}

/**
 * Encodes a string for use in URLs
 *
 * @param string $str Source string
 * @param bool $translit Transliterate non-English characters
 * @return string
 */

function cot_urlencode($str, $translit = false)
{
	global $lang, $cot_translit;
	if ($translit && $lang != 'en' && is_array($cot_translit))
	{
		// Apply transliteration
		$str = strtr($str, $cot_translit);
	}
	return urlencode($str);
}

/**
 * Decodes a string that has been previously encoded with cot_urlencode()
 *
 * @param string $str Encoded string
 * @param bool $translit Transliteration of non-English characters was used
 * @return string
 */
function cot_urldecode($str, $translit = false)
{
	global $lang, $cot_translitb;
	if ($translit && $lang != 'en' && is_array($cot_translitb))
	{
		// Apply transliteration
		$str = strtr($str, $cot_translitb);
	}
	return urldecode($str);
}

/**
 * Store URI-redir to session
 *
 * @global $sys
 */
function cot_uriredir_store()
{
	global $sys;

	$script = basename($_SERVER['SCRIPT_NAME']);

	if ($_SERVER['REQUEST_METHOD'] != 'POST' // not form action/POST
		&& empty($_GET['x']) // not xg, hence not form action/GET and not command from GET
		&& !empty($script)
		&& $script != 'message.php' // not message location
		&& ($script != 'users.php' // not login/logout location
			|| empty($_GET['m'])
			|| !in_array($_GET['m'], array('auth', 'logout', 'register'))
	)
	)
	{
		$_SESSION['s_uri_redir'] = $sys['uri_redir'];
	}
}

/**
 * Apply URI-redir that stored in session
 *
 * @param bool $cfg_redir Configuration of redirect back
 * @global $redirect
 */
function cot_uriredir_apply($cfg_redir = true)
{
	global $redirect;

	if ($cfg_redir && empty($redirect) && !empty($_SESSION['s_uri_redir']))
	{
		$redirect = $_SESSION['s_uri_redir'];
	}
}

/**
 * Checks URI-redir for xg before redirect
 *
 * @param string $uri Target URI
 */
function cot_uriredir_redirect($uri)
{
	if (mb_strpos($uri, '&x=') !== false || mb_strpos($uri, '?x=') !== false)
	{
		$uri = cot_url('index'); // xg, not redirect to form action/GET or to command from GET
	}
	cot_redirect($uri);
}

/*
 * ========================= Internationalization (i18n) ======================
*/

$cot_languages['cn']= '中文';
$cot_languages['de']= 'Deutsch';
$cot_languages['dk']= 'Dansk';
$cot_languages['en']= 'English';
$cot_languages['es']= 'Español';
$cot_languages['fi']= 'Suomi';
$cot_languages['fr']= 'Français';
$cot_languages['gr']= 'Greek';
$cot_languages['hu']= 'Hungarian';
$cot_languages['it']= 'Italiano';
$cot_languages['jp']= '日本語';
$cot_languages['kr']= '한국어';
$cot_languages['nl']= 'Dutch';
$cot_languages['pl']= 'Polski';
$cot_languages['pt']= 'Portugese';
$cot_languages['ru']= 'Русский';
$cot_languages['se']= 'Svenska';
$cot_languages['uk'] = 'Українська';

/**
 * Makes correct plural forms of words
 *
 * @global string $lang Current language
 * @param int $digit Numeric value
 * @param string $expr Word or expression
 * @param bool $onlyword Return only words, without numbers
 * @param bool $canfrac - Numeric value can be Decimal Fraction
 * @return string
 */
function cot_declension($digit, $expr, $onlyword = false, $canfrac = false)
{
	global $lang;

	if (!is_array($expr))
	{
		return trim(($onlyword ? '' : "$digit ").$expr);
	}

	if ($canfrac)
	{
		$is_frac = floor($digit) != $digit;
		$i = $digit;
	}
	else
	{
		$is_frac = false;
		$i = preg_replace('#\D+#', '', $digit);
	}

	$plural = cot_get_plural($i, $lang, $is_frac);
	$cnt = count($expr);
	return trim(($onlyword ? '' : "$digit ").(($cnt > 0 && $plural < $cnt) ? $expr[$plural] : ''));
}

/**
 * Used in cot_declension to get rules for concrete languages
 *
 * @param int $plural Numeric value
 * @param string $lang Target language code
 * @param bool $is_frac true if numeric value is fraction, otherwise false
 * @return int
 */
function cot_get_plural($plural, $lang, $is_frac = false)
{
	switch ($lang)
	{
		case 'en':
		case 'de':
		case 'nl':
		case 'se':
		case 'us':
			return ($plural == 1) ? 1 : 0;

		case 'fr':
			return ($plural > 1) ? 0 : 1;

		case 'ru':
		case 'ua':
			if ($is_frac)
			{
				return 1;
			}
			$plural %= 100;
			return (5 <= $plural && $plural <= 20) ? 2 : ((1 == ($plural %= 10)) ? 0 : ((2 <= $plural && $plural <= 4) ? 1 : 2));

		default:
			return 0;
	}
}

/*
 * ============================================================================
*/

if ($cfg['customfuncs'])
{
	require_once($cfg['system_dir'].'/functions.custom.php');
}

?>