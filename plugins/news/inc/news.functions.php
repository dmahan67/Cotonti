<?php
/**
 * Pick up pages from a category and display the newest in the home page
 *
 * @package news
 * @version 0.7.0
 * @author esclkm, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2010
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL');

cot_require_api('extrafields');
cot_require('page');
cot_require_lang('news', 'plug');

function cot_get_news($cat, $themefile = "news", $limit = false, $d = 0, $textlength = 0, $deftag = false)
{
	global $db, $cot_cat, $db_pages, $db_users, $sys, $cfg, $L, $pag, $cot_extrafields, $usr, $cot_dbc, $cot_urltrans, $c;
	static $news_extp, $news_tags_extp, $news_first_extp;
	/* === Hook - Part1 : Set === FIRST === */
	if (!$news_first_extp)
	{
		$news_first_extp = cot_getextplugins('news.first');
	}
	/* === Hook - Part1 : Set === LOOP === */
	if (!$news_extp)
	{
		$news_extp = cot_getextplugins('news.loop');
	}
	/* === Hook - Part1 : Set === TAGS === */
	if (!$news_tags_extp)
	{
		$news_tags_extp = cot_getextplugins('news.tags');
	}
	/* ===== */
	$jj = 0;
	$mtch = $cot_cat[$cat]['path'].".";
	$mtchlen = mb_strlen($mtch);
	$catsub = array();
	$catsub[] = $cat;
	foreach ($cot_cat as $i => $x)
	{
		if (mb_substr($x['path'], 0, $mtchlen) == $mtch && cot_auth('page', $i, 'R'))
		{
			$catsub[] = $i;
		}
	}

	if (!$limit)
	{
		$limit = $cfg['plugin']['news']['maxpages'];
	}
	$order = $cfg['page'][$cat]['order'];
	$way = $cfg['page'][$cat]['way'];

	$where = "page_state=0 AND page_cat <> 'system' AND page_begin<'".$sys['now_offset']."'
		AND page_expire>'".$sys['now_offset']."' AND page_date <= ".(int)$sys['now_offset']." AND page_cat IN ('".implode("','", $catsub)."')";
	/* === Hook - Part2 : Include === FIRST === */
	foreach ($news_first_extp as $pl)
	{
		include $pl;
	}
	/* ===== */
	$sql = $db->query("SELECT p.*, u.* FROM $db_pages AS p
		LEFT JOIN $db_users AS u ON u.user_id=p.page_ownerid
		WHERE ".$where."
		ORDER BY page_".$order." ".$way." LIMIT $d, ".$limit);

	$sql2 = $db->query("SELECT COUNT(*) FROM $db_pages
		WHERE ".$where);

	$totalnews = $sql2->fetchColumn();
	$news_link_params = cot_news_link($cat, $deftag, true);
    $news_link = cot_url('index', $news_link_params);
	$catd = ((!$deftag || $c != $cat) && !$cfg['plugin']['news']['syncpagination']) ? $cat."d" : "d";

	$pagenav = cot_pagenav('index', $news_link_params, $d, $totalnews, $limit, $catd);

	if (file_exists(cot_skinfile($themefile, true)))
	{
		$news = new XTemplate(cot_skinfile($themefile, true));
	}
	else
	{
		$news = new XTemplate(cot_skinfile('news', true));
	}

	while ($pag = $sql->fetch())
	{
		$jj++;
		$catpath = cot_build_catpath('page', $pag['page_cat']);
		$news->assign(cot_generate_pagetags($pag, "PAGE_ROW_", $textlength));
		$news->assign(array(
			"PAGE_ROW_NEWSPATH" => cot_rc_link(cot_url('index', 'c='.$pag['page_cat']), htmlspecialchars($cot_cat[$row['page_cat']]['title'])),
			"PAGE_ROW_CATDESC" => htmlspecialchars($cot_cat[$pag['page_cat']]['desc']),
			"PAGE_ROW_OWNER" => cot_build_user($pag['page_ownerid'], htmlspecialchars($pag['user_name'])),
			"PAGE_ROW_ODDEVEN" => cot_build_oddeven($jj),
			"PAGE_ROW_NUM" => $jj
		));
		$news->assign(cot_generate_usertags($pag, "PAGE_ROW_OWNER_"));

		/* === Hook - Part2 : Include === LOOP === */
		foreach ($news_extp as $pl)
		{
			include $pl;
		}
		/* ===== */

		if ($cfg['plugin']['tags']['pages'])
		{
			cot_require('tags', true);
			$item_id = $pag['page_id'];
			$tags = cot_tag_list($item_id);
			if (count($tags) > 0)
			{
				$tag_ii = 0;
				foreach ($tags as $tag)
				{
					$tag_u = cot_urlencode($tag, $cfg['plugin']['tags']['translit']);
					$tl = $lang != 'en' && $tag_u != urlencode($tag) ? '&tl=1' : '';
					$news->assign(array(
						'PAGE_TAGS_ROW_TAG' => $cfg['plugin']['tags']['title'] ? htmlspecialchars(cot_tag_title($tag)) : htmlspecialchars($tag),
						'PAGE_TAGS_ROW_TAG_COUNT' => $tag_ii,
						'PAGE_TAGS_ROW_URL' => cot_url('plug', 'e=tags&a=pages&t='.$tag_u.$tl)
					));
					$news->parse('NEWS.PAGE_ROW.PAGE_TAGS.PAGE_TAGS_ROW');
					$tag_ii++;
				}
				$news->parse('NEWS.PAGE_ROW.PAGE_TAGS');
			}
			else
			{
				$news->assign(array(
					'PAGE_NO_TAGS' => $L['tags_Tag_cloud_none']
				));
				$news->parse('NEWS.PAGE_ROW.PAGE_NO_TAGS');
			}
		}

		$news->parse("NEWS.PAGE_ROW");
	}

	$catpath = cot_build_catpath('page', $cat);
	$news->assign(array(
		"PAGE_PAGENAV" => $pagenav['main'],
		"PAGE_PAGEPREV" => $pagenav['prev'],
		"PAGE_PAGENEXT" => $pagenav['next'],
		"PAGE_PAGELAST" => $pagenav['last'],
		"PAGE_PAGENUM" => $pagenav['current'],
		"PAGE_PAGECOUNT" => $pagenav['total'],
		"PAGE_ENTRIES_ONPAGE" => $pagenav['onpage'],
		"PAGE_ENTRIES_TOTAL" => $pagenav['entries'],
		"PAGE_SUBMITNEWPOST" => (cot_auth('page', $cat, 'W')) ? cot_rc_link(cot_url('page', 'm=add&c='.$cat), $L['Submitnew']) : '',
		"PAGE_CATTITLE" =>$cot_cat[$cat]['title'],
		"PAGE_CATPATH" =>$catpath,
		"PAGE_CAT" => $cat
	));

	/* === Hook - Part2 : Include === TAGS === */
	foreach ($news_tags_extp as $pl)
	{
		include $pl;
	}
	/* ===== */

	$news->parse("NEWS");
	return ($news->text("NEWS"));
}

function cot_news_link($maincat, $tag, $ret_params = false)
{
	global $c, $cats, $indexcat, $d, $cfg;
	if ($c != $indexcat)
	{
		$valtext = "c=".$c;
	}
	if (!$cfg['plugin']['news']['syncpagination'] && !empty($cats))
	{
		if (($c != $maincat || !$tag) && $d != 0)
		{
			$valtext .= "&d=".$d;
		}
		foreach ($cats as $k => $v)
		{
			if (($k != $maincat || $tag) && $v[2] != 0)
			{
				$valtext .= "&".$k."d=".$v[2];
			}
		}
	}
	return $ret_params ? $valtext : cot_url('index', $valtext);
}

?>