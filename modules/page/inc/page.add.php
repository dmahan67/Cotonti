<?php
/**
 * Add page.
 *
 * @package page
 * @version 0.9.6
 * @author Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2012
 * @license BSD License
 */

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('forms');

$id = cot_import('id', 'G', 'INT');
$c = cot_import('c', 'G', 'TXT');

if (!empty($c) && !isset($structure['page'][$c]))
{
	$c = '';
}

list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = cot_auth('page', 'any');

/* === Hook === */
foreach (cot_getextplugins('page.add.first') as $pl)
{
	include $pl;
}
/* ===== */
cot_block($usr['auth_write']);

if ($structure['page'][$c]['locked'])
{
	cot_die_message(602, TRUE);
}

$sys['parser'] = $cfg['page']['parser'];
$parser_list = cot_get_parsers();

if ($a == 'add')
{
	cot_shield_protect();

	/* === Hook === */
	foreach (cot_getextplugins('page.add.add.first') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$rpage['page_cat'] = cot_import('rpagecat', 'P', 'TXT');
	$rpage['page_keywords'] = cot_import('rpagekeywords', 'P', 'TXT');
	$rpage['page_alias'] = cot_import('rpagealias', 'P', 'TXT');
	$rpage['page_title'] = cot_import('rpagetitle', 'P', 'TXT');
	$rpage['page_desc'] = cot_import('rpagedesc', 'P', 'TXT');
	$rpage['page_text'] = cot_import('rpagetext', 'P', 'HTM');
	$rpage['page_parser'] = cot_import('rpageparser', 'P', 'ALP');
	$rpage['page_author'] = cot_import('rpageauthor', 'P', 'TXT');
	$rpage['page_file'] = intval(cot_import('rpagefile', 'P', 'INT'));
	$rpage['page_url'] = cot_import('rpageurl', 'P', 'TXT');
	$rpage['page_size'] = cot_import('rpagesize', 'P', 'TXT');
	$rpage['page_file'] = ($rpage['page_file'] == 0 && !empty($rpage['page_url'])) ? 1 : $rpage['page_file'];
	$rpage['page_ownerid'] = (int)$usr['id'];

	$rpage['page_date'] = (int)$sys['now'];
	$rpage['page_begin'] = (int)cot_import_date('rpagebegin');
	$rpage['page_expire'] = (int)cot_import_date('rpageexpire');
	$rpage['page_expire'] = ($rpage['page_expire'] <= $rpage['page_begin']) ? 0 : $rpage['page_expire'];
	$rpage['page_updated'] = $sys['now'];

	$rpublish = cot_import('rpublish', 'P', 'ALP'); // For backwards compatibility
	$rpage['page_state'] = ($rpublish == 'OK') ? 0 : cot_import('rpagestate', 'P', 'INT');

	// Extra fields
	foreach ($cot_extrafields[$db_pages] as $exfld)
	{
		$rpage['page_'.$exfld['field_name']] = cot_import_extrafields('rpage'.$exfld['field_name'], $exfld);
	}

	list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = cot_auth('page', $rpage['page_cat']);
	cot_block($usr['auth_write']);

	cot_check(empty($rpage['page_cat']), 'page_catmissing', 'rpagecat');
	if ($structure['page'][$rpage['page_cat']]['locked'])
	{
		require_once cot_langfile('message', 'core');
		cot_error('msg602_body', 'rpagecat');
	}
	cot_check(mb_strlen($rpage['page_title']) < 2, 'page_titletooshort', 'rpagetitle');

	cot_check(!empty($rpage['page_alias']) && preg_match('`[+/?%#&]`', $rpage['page_alias']), 'page_aliascharacters', 'rpagealias');

	$allowemptytext = isset($cfg['page']['cat_' . $rpage['page_cat']]['allowemptytext']) ?
							$cfg['page']['cat_' . $rpage['page_cat']]['allowemptytext'] : $cfg['page']['cat___default']['allowemptytext'];
	$allowemptytext || cot_check(empty($rpage['page_text']), 'page_textmissing', 'rpagetext');

	if (empty($rpage['page_parser']) || !in_array($rpage['page_parser'], $parser_list) || $rpage['page_parser'] != 'none' && !cot_auth('plug', $rpage['page_parser'], 'W'))
	{
		$rpage['page_parser'] = $cfg['page']['parser'];
	}

	/* === Hook === */
	foreach (cot_getextplugins('page.add.add.error') as $pl)
	{
		include $pl;
	}
	/* ===== */

	if (!cot_error_found())
	{
		if (!empty($rpage['page_alias']))
		{
			$sql_page = $db->query("SELECT page_id FROM $db_pages WHERE page_alias='".$db->prep($rpage['page_alias'])."'");
			$rpage['page_alias'] = ($sql_page->rowCount() > 0) ? $rpage['page_alias'].rand(1000, 9999) : $rpage['page_alias'];
		}

		if ($rpage['page_state'] == 0)
		{
			if ($usr['isadmin'] && $cfg['page']['autovalidate'])
			{
				$db->query("UPDATE $db_structure SET structure_count=structure_count+1 WHERE structure_code='".$db->prep($rpage['page_cat'])."' ");
				$cache && $cache->db->remove('structure', 'system');
			}
			else
			{
				$rpage['page_state'] = 1;
			}
		}

		/* === Hook === */
		foreach (cot_getextplugins('page.add.add.query') as $pl)
		{
			include $pl;
		}
		/* ===== */

		$sql_page_insert = $db->insert($db_pages, $rpage);
		$id = $db->lastInsertId();

		switch ($rpage['page_state'])
		{
			case 0:
				$urlparams = empty($rpage['page_alias']) ?
					array('c' => $rpage['page_cat'], 'id' => $id) :
					array('c' => $rpage['page_cat'], 'al' => $rpage['page_alias']);
				$r_url = cot_url('page', $urlparams, '', true);
				break;
			case 1:
				$r_url = cot_url('message', 'msg=300', '', true);
				break;
			case 2:
				cot_message($L['page_savedasdraft']);
				$r_url = cot_url('page', 'm=edit&id='.$id, '', true);
				break;
		}

		cot_extrafield_movefiles();

		/* === Hook === */
		foreach (cot_getextplugins('page.add.add.done') as $pl)
		{
			include $pl;
		}
		/* ===== */

		if ($rpage['page_state'] == 0 && $cache)
		{
			if ($cfg['cache_page'])
			{
				$cache->page->clear('page/' . str_replace('.', '/', $structure['page'][$rpage['page_cat']]['path']));
			}
			if ($cfg['cache_index'])
			{
				$cache->page->clear('index');
			}
		}
		cot_shield_update(30, "r page");
		cot_log("Add page #".$id, 'adm');
		cot_redirect($r_url);
	}
	else
	{
		$c = ($c != $rpage['page_cat']) ? $rpage['page_cat'] : $c;
		cot_redirect(cot_url('page', 'm=add&c='.$c, '', true));
	}
}

if (empty($rpage['page_cat']) && !empty($c))
{
	$rpage['page_cat'] = $c;
	$usr['isadmin'] = cot_auth('page', $rpage['page_cat'], 'A');
}

$out['subtitle'] = $L['page_addsubtitle'];
$out['head'] .= $R['code_noindex'];
$sys['sublocation'] = $structure['page'][$c]['title'];

$mskin = cot_tplfile(array('page', 'add', $structure['page'][$rpage['page_cat']]['tpl']));

/* === Hook === */
foreach (cot_getextplugins('page.add.main') as $pl)
{
	include $pl;
}
/* ===== */

require_once $cfg['system_dir'].'/header.php';
$t = new XTemplate($mskin);

$pageadd_array = array(
	'PAGEADD_PAGETITLE' => $L['page_addtitle'],
	'PAGEADD_SUBTITLE' => $L['page_addsubtitle'],
	'PAGEADD_ADMINEMAIL' => "mailto:".$cfg['adminemail'],
	'PAGEADD_FORM_SEND' => cot_url('page', 'm=add&a=add&c='.$c),
	'PAGEADD_FORM_CAT' => cot_selectbox_categories($rpage['page_cat'], 'rpagecat'),
	'PAGEADD_FORM_CAT_SHORT' => cot_selectbox_categories($rpage['page_cat'], 'rpagecat', $c),
	'PAGEADD_FORM_KEYWORDS' => cot_inputbox('text', 'rpagekeywords', $rpage['page_keywords'], array('size' => '32', 'maxlength' => '255')),
	'PAGEADD_FORM_ALIAS' => cot_inputbox('text', 'rpagealias', $rpage['page_alias'], array('size' => '32', 'maxlength' => '255')),
	'PAGEADD_FORM_TITLE' => cot_inputbox('text', 'rpagetitle', $rpage['page_title'], array('size' => '64', 'maxlength' => '255')),
	'PAGEADD_FORM_DESC' => cot_inputbox('text', 'rpagedesc', $rpage['page_desc'], array('size' => '64', 'maxlength' => '255')),
	'PAGEADD_FORM_AUTHOR' => cot_inputbox('text', 'rpageauthor', $rpage['page_author'], array('size' => '24', 'maxlength' => '100')),
	'PAGEADD_FORM_OWNER' => cot_build_user($usr['id'], htmlspecialchars($usr['name'])),
	'PAGEADD_FORM_OWNERID' => $usr['id'],
	'PAGEADD_FORM_BEGIN' => cot_selectbox_date($sys['now'], 'long', 'rpagebegin'),
	'PAGEADD_FORM_EXPIRE' => cot_selectbox_date(0, 'long', 'rpageexpire'),
	'PAGEADD_FORM_FILE' => cot_selectbox($rpage['page_file'], 'rpagefile', range(0, 2), array($L['No'], $L['Yes'], $L['Members_only']), false),
	'PAGEADD_FORM_URL' => cot_inputbox('text', 'rpageurl', $rpage['page_url'], array('size' => '56', 'maxlength' => '255')),
	'PAGEADD_FORM_SIZE' => cot_inputbox('text', 'rpagesize', $rpage['page_size'], array('size' => '56', 'maxlength' => '255')),
	'PAGEADD_FORM_TEXT' => cot_textarea('rpagetext', $rpage['page_text'], 24, 120, '', 'input_textarea_editor'),
	'PAGEADD_FORM_PARSER' => cot_selectbox($cfg['page']['parser'], 'rpageparser', $parser_list, $parser_list, false)
);

$t->assign($pageadd_array);

// Extra fields
foreach($cot_extrafields[$db_pages] as $exfld)
{
	$uname = strtoupper($exfld['field_name']);
	$exfld_val = cot_build_extrafields('rpage'.$exfld['field_name'], $exfld, $rpage[$exfld['field_name']]);
	$exfld_title = isset($L['page_'.$exfld['field_name'].'_title']) ?  $L['page_'.$exfld['field_name'].'_title'] : $exfld['field_description'];
	$t->assign(array(
		'PAGEADD_FORM_'.$uname => $exfld_val,
		'PAGEADD_FORM_'.$uname.'_TITLE' => $exfld_title,
		'PAGEADD_FORM_EXTRAFLD' => $exfld_val,
		'PAGEADD_FORM_EXTRAFLD_TITLE' => $exfld_title
		));
	$t->parse('MAIN.EXTRAFLD');
}

// Error and message handling
cot_display_messages($t);

/* === Hook === */
foreach (cot_getextplugins('page.add.tags') as $pl)
{
	include $pl;
}
/* ===== */

if ($usr['isadmin'])
{
	if ($cfg['page']['autovalidate']) $usr_can_publish = TRUE;
	$t->parse('MAIN.ADMIN');
}

$t->parse('MAIN');
$t->out('MAIN');

require_once $cfg['system_dir'].'/footer.php';

?>