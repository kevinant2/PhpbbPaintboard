<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
// Start session
$user->session_begin();
$auth->acl($user->data);

// Start initial var setup
$forum_id	= $request->variable('f', 0);
$mark_read	= $request->variable('mark', '');
$start		= $request->variable('start', 0);

$default_sort_days	= (!empty($user->data['user_topic_show_days'])) ? $user->data['user_topic_show_days'] : 0;
$default_sort_key	= (!empty($user->data['user_topic_sortby_type'])) ? $user->data['user_topic_sortby_type'] : 't';
$default_sort_dir	= (!empty($user->data['user_topic_sortby_dir'])) ? $user->data['user_topic_sortby_dir'] : 'd';

$sort_days	= $request->variable('st', $default_sort_days);
$sort_key	= $request->variable('sk', $default_sort_key);
$sort_dir	= $request->variable('sd', $default_sort_dir);

/* @var $pagination \phpbb\pagination */
$pagination = $phpbb_container->get('pagination');

// Check if the user has actually sent a forum ID with his/her request
// If not give them a nice error page.
if (!$forum_id)
{
	trigger_error('NO_FORUM');
}

$sql_from = FORUMS_TABLE . ' f';
$lastread_select = '';

// Grab appropriate forum data
if ($config['load_db_lastread'] && $user->data['is_registered'])
{
	$sql_from .= ' LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
		AND ft.forum_id = f.forum_id)';
	$lastread_select .= ', ft.mark_time';
}

if ($user->data['is_registered'])
{
	$sql_from .= ' LEFT JOIN ' . FORUMS_WATCH_TABLE . ' fw ON (fw.forum_id = f.forum_id AND fw.user_id = ' . $user->data['user_id'] . ')';
	$lastread_select .= ', fw.notify_status';
}

$sql = "SELECT f.* $lastread_select
	FROM $sql_from
	WHERE f.forum_id = $forum_id";
$result = $db->sql_query($sql);
$forum_data = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$forum_data)
{
	trigger_error('NO_FORUM');
}

if (!$auth->acl_get('f_post', $forum_id) && !$request->variable('animid', '', true))
{
	$user->setup('posting');
	trigger_error('USER_CANNOT_FORUM_POST');
}

// Configure style, language, etc.
$user->setup('viewforum', $forum_data['forum_style']);



// Build navigation links
generate_forum_nav($forum_data);

// Do we have subforums?
$active_forum_ary = $moderators = array();

if ($forum_data['left_id'] != $forum_data['right_id'] - 1)
{
	list($active_forum_ary, $moderators) = display_forums($forum_data, $config['load_moderators'], $config['load_moderators']);
}
else
{
	$template->assign_var('S_HAS_SUBFORUM', false);
	if ($config['load_moderators'])
	{
		get_moderators($moderators, $forum_id);
	}
}

// Is a forum specific topic count required?
if ($forum_data['forum_topics_per_page'])
{
	$config['topics_per_page'] = $forum_data['forum_topics_per_page'];
}

/* @var $phpbb_content_visibility \phpbb\content_visibility */
$phpbb_content_visibility = $phpbb_container->get('content.visibility');

// Dump out the page header and load viewforum template
$topics_count = $phpbb_content_visibility->get_count('forum_topics', $forum_data, $forum_id);
$start = $pagination->validate_start($start, $config['topics_per_page'], $topics_count);

$page_title = $forum_data['forum_name'] . ($start ? ' - ' . $user->lang('PAGE_TITLE_NUMBER', $pagination->get_on_page($config['topics_per_page'], $start)) : '');

/**
* You can use this event to modify the page title of the viewforum page
*
* @event core.viewforum_modify_page_title
* @var	string	page_title		Title of the viewforum page
* @var	array	forum_data		Array with forum data
* @var	int		forum_id		The forum ID
* @var	int		start			Start offset used to calculate the page
* @since 3.2.2-RC1
*/
$vars = array('page_title', 'forum_data', 'forum_id', 'start');
extract($phpbb_dispatcher->trigger_event('core.viewforum_modify_page_title', compact($vars)));

page_header($page_title, true, $forum_id);

$template->set_filenames(array(
	'body' => 'paintbbs.html')
);

if ($request->server("HTTPS"))
{
	$ppath='https://'.$request->server("HTTP_HOST").substr($request->server("PHP_SELF"),0,strrpos($request->server("PHP_SELF"),'/'))  ."/download/file.$phpEx"."?id=".$request->variable('picid', '', true);
}
else
{
	$ppath='http://'.$request->server("HTTP_HOST").substr($request->server("PHP_SELF"),0,strrpos($request->server("PHP_SELF"),'/'))  ."/download/file.$phpEx"."?id=".$request->variable('picid', '', true);
}

$anim=NULL;
$fsize=0;
$pwid=$request->variable('picw', '', true);
$phei=$request->variable('pich', '', true);
if ($request->variable('animid', '', true))
{
	$anim=true;
	$dim=getimagesize($ppath);
	if ($request->server("HTTPS"))
	{
		$apath='https://'.$request->server("HTTP_HOST").substr($request->server("PHP_SELF"),0,strrpos($request->server("PHP_SELF"),'/'))  ."/download/file.$phpEx"."?id=".$request->variable('animid', '', true);
	}
	else
	{
		$apath='http://'.$request->server("HTTP_HOST").substr($request->server("PHP_SELF"),0,strrpos($request->server("PHP_SELF"),'/'))  ."/download/file.$phpEx"."?id=".$request->variable('animid', '', true);
	}
	$fsize=strlen(implode('',file($apath)));	
	if ($dim!=false)
	{	
		$pwid=$dim[0];
		$phei=$dim[1];
	}
}
$editm=false;
$pchfile='';
$imgfile='';
$animode=false;
$importm=false;

if ($request->variable('mode', '', true)=='edit')
{
	$editm=true;
	$imgfile=append_sid("{$phpbb_root_path}download/file.$phpEx", "id=".$request->variable('picid', '', true));
	$pchfile=append_sid("{$phpbb_root_path}download/file.$phpEx", "id=".$request->variable('anid', '', true));

	$PicName=$request->variable('epname', '', true);
	$PchName=$request->variable('eaname', '', true);
	if (strlen($PchName)>1)
	{
		$animode=true;
	}
	 
	$dim=getimagesize($ppath);
	if ($dim!=false)
	{	
		$pwid=$dim[0];
		$phei=$dim[1];
	}
	$ActionURL = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id);
	$mode='edit';
}
else
{
	if ($request->variable('modea', '', true)=='import'){
		$imgfile=$request->variable('importpic', '', true);
		$pchfile=$request->variable('importanim', '', true);
		$dim=getimagesize($imgfile);
		if ($dim!=false)
		{	
			$pwid=$dim[0];
			$phei=$dim[1];
		}
		$importm=true;
		//Check Image Size
		if ($pwid>$forum_data['MaxPicWidth']||$pwid<$forum_data['MinPicWidth']||$phei>$forum_data['MaxPicHeight']||$phei<$forum_data['MinPicHeight']){
			$importm=false;
			$imgfile='';
			$pchfile='';
			$pwid=$forum_data['DefaultPicWidth'];
			$phei=$forum_data['DefaultPicHeight'];
		}
	}
	$PicName=$user->data['user_id'] . '_' . md5(unique_id());
	$PchName=$user->data['user_id'] . '_' . md5(unique_id());
	$animode=$request->variable('anime', '', true);
	$mode='hello';
	$ActionURL=append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=post&amp;Paintbbs=1&amp;pname='.$PicName.'&amp;PchName='.$PchName.'&amp;f=' . $forum_id);
}
$dtime=0;
if ($request->variable('dtime', '', true))
{
	$dtime=$request->variable('dtime', '', true);
}
$template->assign_vars(array(
	'U_VIEW_FORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id"/* . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : '') . (($start == 0) ? '' : "&amp;start=$start")*/),
	'S_PAINTBBSAction'		=> append_sid("{$phpbb_root_path}paintbook.$phpEx", "modea=import&amp;f=$forum_id"/* . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : '') . (($start == 0) ? '' : "&amp;start=$start")*/),
	'picw'	=> $pwid,
	'pich'	=> $phei,
	'shi'	=> $request->variable('shi', '', true),
	'SaveUrl'	=>append_sid("{$phpbb_root_path}picpost.$phpEx", 'mode='.$mode.'&amp;dtime='.$dtime.'&amp;pname='.$PicName.'&amp;PchName='.$PchName.'&amp;wid='.$request->variable('picw', '', true).'&amp;hei='.$request->variable('pich', '', true).'&amp;f=' . $forum_id.'&amp;posttime='.time()),
	'actionPost'	=>$ActionURL,
	'anime'	=>$animode,
	'picedit'=>$editm,
	'picedimport'=>$importm,
	'MaxPicWidth'=>$forum_data['MaxPicWidth'],
	'MaxPicHeight'=>$forum_data['MaxPicHeight'],
	'MinPicWidth'=>$forum_data['MinPicWidth'],
	'MinPicHeight'=>$forum_data['MinPicHeight'],
	'pchfile'=>$pchfile,
	'imgfile'=>$imgfile,
	'image_size'=>0,
	'appw'=>$forum_data['DrawAppletWidth'],
	'apph'=>$forum_data['DrawAppletHeight'],
	'aniappw'=>intval($pwid)+26,
	'aniapph'=>intval($phei)+26,
	'layerc'=>$forum_data['LayersAllowed'],
	'quality'=>$forum_data['PicQuality'],
	'image_jpeg'=>$forum_data['UseJPEG']?true:false,
	'compress_level'=>$forum_data['JPEGCompLv'],
	'undo'=>$forum_data['MaxUndo'],
	'undo_in_mg'=>$forum_data['UndoMG'],
	'Anim_Mode'=>$anim,
	'Anim_Speed'=>0,
	'Anim_ID'=>$request->variable('animid', '', true),
	'Anim_Location'=>append_sid("{$phpbb_root_path}download/file.$phpEx", "id=".$request->variable('animid', '', true)),
	'Filesize'=>round($fsize/1024,2),
	
	
	
));

page_footer();
