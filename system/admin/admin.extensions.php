<?php
/**
 * Extension administration
 *
 * @package Cotonti
 * @version 0.7.0
 * @author Neocrome, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2010
 * @license BSD
 */

(defined('SED_CODE') && defined('SED_ADMIN')) or die('Wrong URL.');

list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = sed_auth('admin', 'a');
sed_block($usr['isadmin']);

sed_require_api('auth');
sed_require_api('parser');

$t = new XTemplate(sed_skinfile('admin.extensions'));

$adminpath[] = array (sed_url('admin', 'm=extensions'), $L['Extensions']);

$pl = sed_import('pl', 'G', 'ALP');
$mod = sed_import('mod', 'G', 'ALP');
$part = sed_import('part', 'G', 'ALP');

if (empty($mod))
{
	if (empty($pl))
	{
		if (!empty($a))
		{
			sed_print($m, $a, $mod, $pl, $part);
			sed_die();
		}
	}
	else
	{
		$is_module = false;
		$code = $pl;
		$arg = 'pl';
		$dir = $cfg['plugins_dir'];
		$type = 'plug';
	}
}
else
{
	$is_module = true;
	$code = $mod;
	$arg = 'mod';
	$dir = $cfg['modules_dir'];
	$type = 'module';
}

$status[0] = $R['admin_code_paused'];
$status[1] = $R['admin_code_running'];
$status[2] = $R['admin_code_partrunning'];
$status[3] = $R['admin_code_notinstalled'];
$found_txt[0] = $R['admin_code_missing'];
$found_txt[1] = $R['admin_code_present'];
unset($disp_errors);

/* === Hook === */
foreach (sed_getextplugins('admin.extensions.first') as $pl)
{
	include $pl;
}
/* ===== */

switch($a)
{
	/* =============== */
	case 'details':
	/* =============== */
		$ext_info = $dir . '/' . $code . '/' . $code . '.setup.php';
		switch($b)
		{
			case 'pause':
				if ($is_module)
				{
					sed_module_pause($code);
				}
				sed_plugin_pause($code);
				$cot_cache && $cot_cache->db->remove('sed_plugins', 'system');
				sed_message('adm_paused');
			break;
			case 'unpause':
				if ($is_module)
				{
					sed_module_resume($code);
				}
				sed_plugin_resume($code);
				$cot_cache && $cot_cache->db->remove('sed_plugins', 'system');
				sed_message('adm_running');
			break;
			case 'pausepart':
				sed_plugin_pause($code, $part);
				$cot_cache && $cot_cache->db->remove('sed_plugins', 'system');
				sed_message('adm_partstopped');
			break;
			case 'unpausepart':
				sed_plugin_resume($code, $part);
				$cot_cache && $cot_cache->db->remove('sed_plugins', 'system');
				sed_message('adm_partrunning');
			break;
		}
		if(file_exists($ext_info))
		{
			$info = sed_infoget($ext_info, 'COT_EXT');
			$adminpath[] = array(sed_url('admin', "m=extensions&a=details&$arg=$code"), $info['Name']." ($code)");

			$parts = array();
			$handle = opendir($dir . '/' . $code);
			while($f = readdir($handle))
			{
				if (preg_match("#^$code(\.([\w\.]+))?.php$#", $f, $mt)
					&& !in_array($mt[2], $cot_ext_ignore_parts))
				{
					$parts[] = $f;
				}
			}
			closedir($handle);

			$isinstalled = $is_module ? sed_module_installed($code) : sed_plugin_installed($code);

			$sql = sed_sql_query("SELECT COUNT(*) FROM $db_config WHERE config_owner='$type' AND config_cat='$code'");
			$totalconfig = sed_sql_result($sql);

			$info['Auth_members'] = sed_auth_getvalue($info['Auth_members']);
			$info['Lock_members'] = sed_auth_getvalue($info['Lock_members']);
			$info['Auth_guests'] = sed_auth_getvalue($info['Auth_guests']);
			$info['Lock_guests'] = sed_auth_getvalue($info['Lock_guests']);

			if (count($parts) > 0)
			{
				sort($parts);
				/* === Hook - Part1 : Set === */
				$extp = sed_getextplugins('admin.extensions.details.part.loop');
				/* ===== */
				foreach ($parts as $i => $x)
				{
					$extplugin_file = $dir . '/' . $code . '/' . $x;
					$info_file = sed_infoget($extplugin_file, 'COT_EXT');
					$info_part = preg_match("#^$code\.([\w\.]+).php$#", $x, $mt) ? $mt[1] : 'main';

					if(!empty($info_file['Error']))
					{
						$t->assign(array(
							'ADMIN_EXTENSIONS_DETAILS_ROW_X' => $x,
							'ADMIN_EXTENSIONS_DETAILS_ROW_ERROR' => $info_file['Error']
						));
						$t->parse('MAIN.DETAILS.ROW_ERROR_PART');
					}
					else
					{
						$sql = sed_sql_query("SELECT pl_active, pl_id FROM $db_plugins
							WHERE pl_code='$code' AND pl_part='".$info_part."' LIMIT 1");

						if($row = sed_sql_fetcharray($sql))
						{
							$info_file['Status'] = $row['pl_active'];
						}
						else
						{
							$info_file['Status'] = 3;
						}

						if(empty($info_file['Tags']))
						{
							$t->assign(array(
								'ADMIN_EXTENSIONS_DETAILS_ROW_I_1' => $i+1,
								'ADMIN_EXTENSIONS_DETAILS_ROW_PART' => $info_part
							));
							$t->parse('MAIN.DETAILS.ROW_ERROR_TAGS');
						}
						else
						{
							$line = explode(':', $info_file['Tags']);
							$line[0] = trim($line[0]);
							$tags = explode(',', $line[1]);
							$listtags = $line[0].' :<br />';
							foreach($tags as $k => $v)
							{
								if(mb_substr(trim($v), 0, 1) == '{')
								{
									$listtags .= $v.' : ';
									$found = sed_stringinfile('./skins/'.$cfg['defaultskin'].'/'.$line[0], trim($v));
									$listtags .= $found_txt[$found].'<br />';
								}
								else
								{
									$listtags .= $v.'<br />';
								}
							}

							$t->assign(array(
								'ADMIN_EXTENSIONS_DETAILS_ROW_I_1' => $i+1,
								'ADMIN_EXTENSIONS_DETAILS_ROW_PART' => $info_part,
								'ADMIN_EXTENSIONS_DETAILS_ROW_FILE' => $line[0].' :<br />',
								'ADMIN_EXTENSIONS_DETAILS_ROW_LISTTAGS' => $listtags,
								//'ADMIN_EXTENSIONS_DETAILS_ROW_TAGS_ODDEVEN' => sed_build_oddeven($ii)
							));
							$t->parse('MAIN.DETAILS.ROW_TAGS');
						}

						$info_order = empty($info_file['Order']) ? COT_PLUGIN_DEFAULT_ORDER : $info_file['Order'];
						$t->assign(array(
							'ADMIN_EXTENSIONS_DETAILS_ROW_I_1' => $i+1,
							'ADMIN_EXTENSIONS_DETAILS_ROW_PART' => $info_part,
							'ADMIN_EXTENSIONS_DETAILS_ROW_FILE' => $x,
							'ADMIN_EXTENSIONS_DETAILS_ROW_HOOKS' => $info_file['Hooks'],
							'ADMIN_EXTENSIONS_DETAILS_ROW_ORDER' => $info_order,
							'ADMIN_EXTENSIONS_DETAILS_ROW_STATUS' => $status[$info_file['Status']],
							//'ADMIN_EXTENSIONS_DETAILS_ROW_PART_ODDEVEN' => sed_build_oddeven($ii)
						));

						if ($info_file['Status'] == 3)
						{
							$t->parse('MAIN.DETAILS.ROW_PART.ROW_PART_NOTINSTALLED');
						}
						if ($info_file['Status'] != 3 && $row['pl_active'] == 1)
						{
							$t->assign('ADMIN_EXTENSIONS_DETAILS_ROW_PAUSEPART_URL',
								sed_url('admin', "m=extensions&a=details&$arg=$code&b=pausepart&part=".$row['pl_id']));
							$t->parse('MAIN.DETAILS.ROW_PART.ROW_PART_PAUSE');
						}
						if ($info_file['Status'] != 3 && $row['pl_active'] == 0)
						{
							$t->assign('ADMIN_EXTENSIONS_DETAILS_ROW_UNPAUSEPART_URL',
								sed_url('admin', "m=extensions&a=details&$arg=$code&b=unpausepart&part=".$row['pl_id']));
							$t->parse('MAIN.DETAILS.ROW_PART.ROW_PARTUNPAUSE');
						}

						/* === Hook - Part2 : Include === */
						foreach ($extp as $pl)
						{
							include $pl;
						}
						/* ===== */
						$t->parse('MAIN.DETAILS.ROW_PART');
					}
				}
			}

			$t->assign(array(
				'ADMIN_EXTENSIONS_NAME' => $info['Name'],
				'ADMIN_EXTENSIONS_CODE' => $info['Code'],
				'ADMIN_EXTENSIONS_DESCRIPTION' => $info['Description'],
				'ADMIN_EXTENSIONS_VERSION' => $info['Version'],
				'ADMIN_EXTENSIONS_DATE' => $info['Date'],
				'ADMIN_EXTENSIONS_CONFIG_URL' => sed_url('admin', "m=config&n=edit&o=$type&p=$code"),
				'ADMIN_EXTENSIONS_TOTALCONFIG' => $totalconfig,
				'ADMIN_EXTENSIONS_RIGHTS' => sed_url('admin', "m=rightsbyitem&ic=$type&io=$code"),
				'ADMIN_EXTENSIONS_ADMRIGHTS_AUTH_GUESTS' => sed_auth_getmask($info['Auth_guests']),
				'ADMIN_EXTENSIONS_AUTH_GUESTS' => $info['Auth_guests'],
				'ADMIN_EXTENSIONS_ADMRIGHTS_LOCK_GUESTS' => sed_auth_getmask($info['Lock_guests']),
				'ADMIN_EXTENSIONS_LOCK_GUESTS' => $info['Lock_guests'],
				'ADMIN_EXTENSIONS_ADMRIGHTS_AUTH_MEMBERS' => sed_auth_getmask($info['Auth_members']),
				'ADMIN_EXTENSIONS_AUTH_MEMBERS' => $info['Auth_members'],
				'ADMIN_EXTENSIONS_ADMRIGHTS_LOCK_MEMBERS' => sed_auth_getmask($info['Lock_members']),
				'ADMIN_EXTENSIONS_LOCK_MEMBERS' => $info['Lock_members'],
				'ADMIN_EXTENSIONS_AUTHOR' => $info['Author'],
				'ADMIN_EXTENSIONS_COPYRIGHT' => $info['Copyright'],
				'ADMIN_EXTENSIONS_NOTES' => sed_parse($info['Notes'], 1, 0, 0),
				'ADMIN_EXTENSIONS_INSTALL_URL' => sed_url('admin', "m=extensions&a=edit&$arg=$code&b=install"),
				'ADMIN_EXTENSIONS_UPDATE_URL' => sed_url('admin', "m=extensions&a=edit&$arg=$code&b=update"),
				'ADMIN_EXTENSIONS_UNINSTALL_URL' => sed_url('admin', "m=extensions&a=edit&$arg=$code&b=uninstall"),
				'ADMIN_EXTENSIONS_PAUSE_URL' => sed_url('admin', "m=extensions&a=details&$arg=$code&b=pause"),
				'ADMIN_EXTENSIONS_UNPAUSE_URL' => sed_url('admin', "m=extensions&a=details&$arg=$code&b=unpause")
			));
			/* === Hook  === */
			foreach (sed_getextplugins('admin.extensions.details') as $pl)
			{
				include $pl;
			}
			/* ===== */
			$t->parse('MAIN.DETAILS');
		}
		else
		{
			sed_die();
		}
	break;
	/* =============== */
	case 'edit':
	/* =============== */
		switch($b)
		{
			case 'install':
				$result = sed_extension_install($code, $is_module);

				$t->assign(array(
					'ADMIN_EXTENSIONS_EDIT_TITLE' => sed_rc('ext_installing', array(
							'type' => $is_module ? $L['Module'] : $L['Plugin'],
							'name' => $code
						)),
					'ADMIN_EXTENSIONS_EDIT_RESULT' => $result && !$cot_error ? 'success' : 'error',
					'ADMIN_EXTENSIONS_EDIT_LOG' => sed_implode_messages(),
					'ADMIN_EXTENSIONS_EDIT_CONTINUE_URL' => sed_url('admin', "m=extensions&a=details&$arg=$code")
				));
				/* === Hook  === */
				foreach (sed_getextplugins('admin.extensions.install.tags') as $pl)
				{
					include $pl;
				}
				/* ===== */
			break;
			case 'update':
				$result = sed_extension_install($code, $is_module, true);

				$t->assign(array(
					'ADMIN_EXTENSIONS_EDIT_TITLE' => sed_rc('ext_updating', array(
							'type' => $is_module ? $L['Module'] : $L['Plugin'],
							'name' => $code
						)),
					'ADMIN_EXTENSIONS_EDIT_RESULT' => $result && !$cot_error ? 'success' : 'error',
					'ADMIN_EXTENSIONS_EDIT_LOG' => sed_implode_messages(),
					'ADMIN_EXTENSIONS_EDIT_CONTINUE_URL' => sed_url('admin', "m=extensions&a=details&$arg=$code")
				));
				/* === Hook  === */
				foreach (sed_getextplugins('admin.extensions.install.tags') as $pl)
				{
					include $pl;
				}
				/* ===== */
				break;
			case 'uninstall':
				$result = sed_extension_uninstall($code, $is_module);

				$t->assign(array(
					'ADMIN_EXTENSIONS_EDIT_TITLE' => sed_rc('ext_uninstalling', array(
							'type' => $is_module ? $L['Module'] : $L['Plugin'],
							'name' => $code
						)),
					'ADMIN_EXTENSIONS_EDIT_RESULT' => $result && !$cot_error ? 'success' : 'error',
					'ADMIN_EXTENSIONS_EDIT_LOG' => sed_implode_messages(),
					'ADMIN_EXTENSIONS_EDIT_CONTINUE_URL' => sed_url('admin', "m=extensions&a=details&$arg=$code")
				));
				/* === Hook  === */
				foreach (sed_getextplugins('admin.extensions.uninstall.tags') as $pl)
				{
					include $pl;
				}
				/* ===== */
			break;
			default:
				sed_die();
			break;

			sed_clear_messages();
			$t->parse('MAIN.EDIT');
		}
	break;
	default:
		foreach (array('module', 'plug') as $type)
		{
			$sql = sed_sql_query("SELECT DISTINCT(config_cat), COUNT(*) FROM $db_config
			WHERE config_owner='$type' GROUP BY config_cat");
			while ($row = sed_sql_fetchrow($sql))
			{
				$cfgentries[$row['config_cat']] = $row[0];
			}
			sed_sql_freeresult($sql);

			$dir = $type == 'module' ? $cfg['modules_dir'] : $cfg['plugins_dir'];
			$extensions = array();
			$handle = opendir($dir);
			while ($f = readdir($handle))
			{
				if (is_dir($dir . '/' . $f) && $f[0] != '.')
				{
					// Check for extension .php files, otherwise it's inconsistent
					$is_ext = false;
					$dp = opendir($dir . '/' . $f);
					while ($pf = readdir($dp))
					{
						if (preg_match("#^$f(\.([\w\.]+))?.php$#", $pf))
						{
							$is_ext = true;
							break;
						}
					}
					closedir($dp);
					if ($is_ext)
					{
						$extensions[] = $f;
					}
				}
			}
			closedir($handle);
			sort($extensions);
			$cnt_extp = count($extensions);
			$cnt_parts = 0;

			if ($type == 'plug')
			{
				$plg_standalone = array();
				$sql3 = sed_sql_query("SELECT pl_code FROM $db_plugins WHERE pl_hook='standalone'");
				while ($row3 = sed_sql_fetcharray($sql3))
				{
					$plg_standalone[$row3['pl_code']] = TRUE;
				}
				sed_sql_freeresult($sql3);

				$plg_tools = array();
				$sql3 = sed_sql_query("SELECT pl_code FROM $db_plugins WHERE pl_hook='tools'");
				while ($row3 = sed_sql_fetcharray($sql3))
				{
					$plg_tools[$row3['pl_code']] = TRUE;
				}
				sed_sql_freeresult($sql3);
			}
			/* === Hook - Part1 : Set === */
			$extp = sed_getextplugins("admin.extensions.$type.list.loop");
			/* ===== */
			foreach ($extensions as $i => $x)
			{
				$ext_info = $dir . '/' . $x . '/' . $x . '.setup.php';
				if (file_exists($ext_info))
				{
					$info = sed_infoget($ext_info, 'COT_EXT');

					if (!empty($info['Error']))
					{
						$t->assign(array(
							'ADMIN_EXTENSIONS_X_ERR' => $x,
							'ADMIN_EXTENSIONS_ERROR_MSG' => $info['Error']
						));
						$t->parse('MAIN.DEFAULT.ROW.ROW_ERROR_EXT');
						$t->parse('MAIN.DEFAULT.ROW');
					}
					else
					{
						$sql1 = sed_sql_query("SELECT SUM(pl_active) FROM $db_plugins WHERE pl_code='$x'");
						$sql2 = sed_sql_query("SELECT COUNT(*) FROM $db_plugins WHERE pl_code='$x'");
						$totalactive = sed_sql_result($sql1, 0, "SUM(pl_active)");
						$totalinstalled = sed_sql_result($sql2, 0, "COUNT(*)");
						$cnt_parts += $totalinstalled;

						if ($totalinstalled == 0)
						{
							$part_status = 3;
							$info['Partscount'] = '?';
						}
						else
						{
							$info['Partscount'] = $totalinstalled;
							if ($totalinstalled > $totalactive && $totalactive > 0)
							{
								$part_status = 2;
							}
							elseif ($totalactive == 0)
							{
								$part_status = 0;
							}
							else
							{
								$part_status = 1;
							}
						}

						$ifthistools = $plg_tools[$x];
						$ent_code = $cfgentries[$x];
						$if_plg_standalone = $plg_standalone[$x];

						if ($type == 'module')
						{
							$arg = 'mod';
							$ze = 'z';
						}
						else
						{
							$arg = 'pl';
							$ze = 'e';
						}

						$t->assign(array(
							'ADMIN_EXTENSIONS_DETAILS_URL' => sed_url('admin', "m=extensions&a=details&$arg=$x"),
							'ADMIN_EXTENSIONS_NAME' => $info['Name'],
							'ADMIN_EXTENSIONS_CODE_X' => $x,
							'ADMIN_EXTENSIONS_EDIT_URL' => sed_url('admin', "m=config&n=edit&o=$type&p=$x"),
							'ADMIN_EXTENSIONS_PARTSCOUNT' => $info['Partscount'],
							'ADMIN_EXTENSIONS_STATUS' => $status[$part_status],
							'ADMIN_EXTENSIONS_RIGHTS_URL' => sed_url('admin', "m=rightsbyitem&ic=$type&io=$x"),
							'ADMIN_EXTENSIONS_JUMPTO_URL_TOOLS' => sed_url('admin', "m=tools&p=$x"),
							'ADMIN_EXTENSIONS_JUMPTO_URL' => sed_url('index', "$ze=$x"),
							'ADMIN_EXTENSIONS_ODDEVEN' => sed_build_oddeven($i)
						));
						/* === Hook - Part2 : Include === */
						foreach ($extp as $pl)
						{
							include $pl;
						}
						/* ===== */
						$t->parse('MAIN.DEFAULT.SECTION.ROW');
					}
				}
				else
				{
					$t->assign(array(
						'ADMIN_EXTENSIONS_X' => $x
					));
					$t->parse('MAIN.DEFAULT.SECTION.ROW_ERROR');
				}
			}
			$t->assign(array(
				'ADMIN_EXTENSIONS_SECTION_TITLE' => $type == 'module' ? $L['Modules'] : $L['Plugins'],
				'ADMIN_EXTENSIONS_CNT_EXTP' => $cnt_extp
			));
			$t->parse('MAIN.DEFAULT.SECTION');
		}

		if($o == 'code')
		{
			$sql = sed_sql_query("SELECT * FROM $db_plugins ORDER BY pl_code ASC, pl_hook ASC, pl_order ASC");
		}
		else
		{
			$sql = sed_sql_query("SELECT * FROM $db_plugins ORDER BY pl_hook ASC, pl_code ASC, pl_order ASC");
		}

		while($row = sed_sql_fetcharray($sql))
		{
			$t->assign(array(
				'ADMIN_EXTENSIONS_HOOK' => $row['pl_hook'],
				'ADMIN_EXTENSIONS_CODE' => $row['pl_code'],
				'ADMIN_EXTENSIONS_ORDER' => $row['pl_order'],
				'ADMIN_EXTENSIONS_ACTIVE' => $sed_yesno[$row['pl_active']]
			));
			$t->parse('MAIN.DEFAULT.HOOKS');
		}

		$t->assign(array(
			'ADMIN_EXTENSIONS_CNT_HOOK' => sed_sql_numrows($sql)
		));
		$t->parse('MAIN.DEFAULT');
	break;
}

if (!empty($code) && $b == 'install' && $totalconfig > 0)
{
	$t->assign('ADMIN_EXTENSIONS_CONFIG_URL', sed_url('admin', 'm=config&n=edit&o=plug&p='.$code));
	$t->parse('MAIN.CONFIG_URL');
}

if (sed_check_messages())
{
	$t->assign('ADMIN_EXTENSIONS_ADMINWARNINGS', sed_implode_messages());
	$t->parse('MAIN.MESSAGE');
}
sed_clear_messages();

/* === Hook  === */
foreach (sed_getextplugins('admin.extensions.tags') as $pl)
{
	include $pl;
}
/* ===== */
$t->parse('MAIN');
if (SED_AJAX)
{
	$t->out('MAIN');
}
else
{
	$adminmain = $t->text('MAIN');
}

?>