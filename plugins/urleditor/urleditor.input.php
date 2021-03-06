<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=input
[END_COT_EXT]
==================== */

/**
 * Overloads standard cot_url() function and loads URL
 * transformation rules
 *
 * @package urleditor
 * @version 0.9.3
 * @author Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2010-2012
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL');

if (!is_array($cot_urltrans))
{
	$cot_urltrans = array();
	$urltrans_preset = $cfg['plugin']['urleditor']['preset'] == 'custom' ? './datas/urltrans.dat' : $cfg['plugins_dir'] . '/urleditor/presets/' . $cfg['plugin']['urleditor']['preset'] . '.dat';
	if ($cfg['plugin']['urleditor']['preset'] != 'none' && file_exists($urltrans_preset))
	{
		$fp = fopen($urltrans_preset, 'r');
		while ($line = trim(fgets($fp), " \t\r\n"))
		{
			$parts = preg_split('#\s+#', $line);
			$rule = array();
			$rule['trans'] = $parts[2];
			$parts[1] == '*' ? $rule['params'] = array() : parse_str($parts[1], $rule['params']);
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
	// Fallback rules for standard PHP URLs
	$cot_urltrans_fallback = array(
		'params' => array(),
		'trans' => '{$_area}.php'
	);
	$cot_urltrans['admin'][] = $cot_urltrans_fallback;
	$cot_urltrans['index'][] = $cot_urltrans_fallback;
	$cot_urltrans['login'][] = $cot_urltrans_fallback;
	$cot_urltrans['message'][] = $cot_urltrans_fallback;
	$cot_urltrans['plug'][] = array(
		'params' => array(),
		'trans' => 'index.php'
	);
	$cot_urltrans['*'][] = array(
		'params' => array(),
		'trans' => 'index.php?e={$_area}'
	);
	$cache && $cache->db->store('cot_urltrans', $cot_urltrans, 'system', 1200);
}

require_once cot_incfile('urleditor', 'plug');

cot_apply_rwr();

?>
