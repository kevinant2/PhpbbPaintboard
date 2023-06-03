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

if(($request->server("REQUEST_METHOD")) !== "POST"){
	return header( "Location: ./ ") ;
}
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
//trigger_error($user-&gt;lang['NO_FORUM']);
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
/*
$lang = array(
'PaintBBS_Error'	=> 'Error\n',
'PaintBBS_Error0'	=> 'Wrong Starting Point',
'PaintBBS_Error1'	=> 'No Data',
'PaintBBS_Error2'	=> 'Picture Size Exceeded',
'PaintBBS_Error3'	=> 'Please change to a new picture',
'PaintBBS_Error4'	=> 'Please add more drawing steps',
'PaintBBS_Error5'	=> 'Step',
'PaintBBS_Error6'	=> 'Document is not saved',
'PaintBBS_Error7'	=> 'Incorrect Format',
);
*/
// Configure style, language, etc.
$user->setup('viewforum', $forum_data['forum_style']);

$errorstr=$user->lang['PaintBBS_Error'];
$error[0]=$user->lang['PaintBBS_Error0'];
$error[1]=$user->lang['PaintBBS_Error1'];
$error[2]=$user->lang['PaintBBS_Error2'];
$error[3]=$user->lang['PaintBBS_Error3'];
$error[4]=$user->lang['PaintBBS_Error4'];
$error[5]=$user->lang['PaintBBS_Error5'];
$error[6]=$user->lang['PaintBBS_Error6'];
$error[7]=$user->lang['PaintBBS_Error7'];

$FTP=False;
$ftp_addr='';
$ftp_user='';
$ftp_pwd='';

header('Content-type: text/plain');

$url_scheme=$request->server('HTTP_ORIGIN') ? parse_url($request->server('HTTP_ORIGIN'), PHP_URL_SCHEME).'://':'';
if($url_scheme && $request->server('HTTP_HOST') &&
str_replace($url_scheme,'',$request->server('HTTP_ORIGIN')) !== $request->server('HTTP_HOST')){
	die($errorstr.$error[0]);
}
/* Defined Constants */
defined('PERMISSION_FOR_LOG') or define('PERMISSION_FOR_LOG', 0600); //config.phpで未定義なら0600
defined('PERMISSION_FOR_DEST') or define('PERMISSION_FOR_DEST', 0606); //config.phpで未定義なら0606
defined('SECURITY_TIMER') or define('SECURITY_TIMER', 0); //config.phpで未定義なら0
defined('SECURITY_CLICK') or define('SECURITY_CLICK', 0); //config.phpで未定義なら0
define('SIZE_CHECK', '1');
define('PICPOST_MAX_KB', '8192');
$TEMP_DIR='./'.$config['upload_path'].'/';	//File Path

$time = time();

/*$u_ip = get_uip();
$u_host = $u_ip ? gethostbyaddr($u_ip) : '';
$u_agent = $request->server("HTTP_USER_AGENT");
$u_agent = str_replace("\t", "", $u_agent);
*/

//Get Raw Post Data
$buffer = file_get_contents('php://input');
if(!$buffer){
	die($errorstr.$error[1]);
}

//Determine Header
$headerLength = substr($buffer, 1, 8);
$imgLength = substr($buffer, 1 + 8 + $headerLength, 8);
if(SIZE_CHECK && ($imgLength > PICPOST_MAX_KB * 1024)){
	die($errorstr.$error[2]);
}
$imgdata = substr($buffer, 1 + 8 + $headerLength + 8 + 2, $imgLength);
$imgh = substr($imgdata, 1, 5);
if($imgh=="PNG\r\n"){
	$imgext = '.png';	// PNG
}else{
	$imgext = '.jpg';	// JPEG
}

/*-- Painter Info --*/

$userdata = "$u_ip\t$u_host\t$u_agent\t$imgext";
$sendheader = substr($buffer, 1 + 8, $headerLength);
$usercode='';
if($sendheader){
	$sendheader = str_replace("&amp;", "&", $sendheader);
	parse_str($sendheader, $u);
	$usercode = isset($u['usercode']) ? $u['usercode'] : '';
	$resto = isset($u['resto']) ? $u['resto'] : '';
	$repcode = isset($u['repcode']) ? $u['repcode'] : '';
	$stime = isset($u['stime']) ? $u['stime'] : '';
	$count = isset($u['count']) ? $u['count'] : 0;
	$timer = isset($u['timer']) ? ($u['timer']/1000) : 0;
	//Usercode String
	$userdata .= "\t$usercode\t$repcode\t$stime\t$time\t$resto";
}
$userdata .= "\n";


if(((bool)SECURITY_TIMER && !$repcode && (bool)$timer) && ((int)$timer<(int)SECURITY_TIMER)){

	$psec=(int)SECURITY_TIMER-(int)$timer;
	$waiting_time=calcPtime ($psec);
	die($errorstr.$error[3]." {$waiting_time}.");
}
if(((int)SECURITY_CLICK && !$repcode && $count) && ($count<(int)SECURITY_CLICK)){
	$nokori=(int)SECURITY_CLICK-$count;
	die($errorstr.$error[4]." {$nokori} ".$error[5]);
}

if ((time()-$request->variable('posttime', ''))<$forum_data['MinDrawingTime'])
{
	trigger_error('NO_FORUM');
}


$imgfile = time().substr(microtime(),2,6);//Picture Name

$imgfile = is_file($TEMP_DIR.$imgfile.$imgext) ? ((time()+1).substr(microtime(),2,6)) : $imgfile;
$realImgName=$imgfile.$imgext;


$full_imgfile = $TEMP_DIR.$request->variable('pname', '');
$full_pchfile = $TEMP_DIR.$request->variable('PchName', '');

if ($FTP==false){
	file_put_contents($full_imgfile,$imgdata,LOCK_EX);
	if(!is_file($full_imgfile)){
		die($errorstr.$error[6]);
	}
	//Check File
	$img_type=mime_content_type($full_imgfile);
	if(!in_array($img_type,["image/png","image/jpeg"])){
		unlink($full_imgfile);
		die($errorstr.$error[7]);
	}
	chmod($full_imgfile,PERMISSION_FOR_DEST);
}
else
{
	$ftp= ftp_connect($ftp_addr);
	$login_result = ftp_login($ftp, $ftp_user, $ftp_pwd);
	$temp = tmpfile();
	fwrite($temp,$imgdata);
	fseek($temp,0);$error=0;
	if (!ftp_fput($ftp,$full_imgfile,$temp,FTP_BINARY)) {
	        die($errorstr.$error[6]);
	}
}

// Animation Saving
$pchLength = substr($buffer, 1 + 8 + $headerLength + 8 + 2 + $imgLength, 8);
$h = substr($buffer, 0, 1);
if($h=='P'){
	$pchext = '.pch';
}elseif($h=='S'){
	$pchext = '.spch';
}
$realPchName=$imgfile.$pchext;
if($pchLength){
	$PCHdata = substr($buffer, 1 + 8 + $headerLength + 8 + 2 + $imgLength + 8, $pchLength);
	//$pch_type=mime_content_type($PCHdata);
}

if ($FTP==false){
	if($pchLength){
		$PCHdata = substr($buffer, 1 + 8 + $headerLength + 8 + 2 + $imgLength + 8, $pchLength);
		file_put_contents($full_pchfile,$PCHdata,LOCK_EX);
		if(is_file($full_pchfile)){
			chmod($full_pchfile,PERMISSION_FOR_DEST);
		}
	}
}
else
{
	if($pchLength){
		$ftp= ftp_connect($ftp_addr);
		$login_result = ftp_login($ftp, $ftp_user, $ftp_pwd);
		$temp = tmpfile();
		fwrite($temp,$PCHdata);
		fseek($temp,0);$error=0;
		if (!ftp_fput($ftp,$full_pchfile,$temp,FTP_BINARY)) {
	        die($errorstr.$error[6]);
			$error=1;
		}
	}
}

/*
// Save other data
file_put_contents(TEMP_DIR.$imgfile.".dat",$userdata,LOCK_EX);
if(!is_file(TEMP_DIR.$imgfile.'.dat')){
	die("error\n Can't Save Data");
}
chmod($TEMP_DIR.$imgfile.'.dat',PERMISSION_FOR_LOG);
*/

$sql_ary = array(
	'physical_filename'	=> $request->variable('pname', ''),
	'attach_comment'	=> time()-$request->variable('posttime', ''),
	'real_filename'		=> $realImgName,
	'extension'			=> ltrim($imgext,'.'),
	'mimetype'			=> $img_type,
	'filesize'			=> strlen($imgdata),
	'filetime'			=> time(),
	'thumbnail'			=> 0,
	'is_orphan'			=> 1,
	'in_message'		=> ($is_message) ? 1 : 0,
	'poster_id'			=> $user->data['user_id'],
);

/**
* Modify attachment sql array on submit
*
* @event core.modify_attachment_sql_ary_on_submit
* @var	array	sql_ary		Array containing SQL data
* @since 3.2.6-RC1
*/
$vars = array('sql_ary');
extract($phpbb_dispatcher->trigger_event('core.modify_attachment_sql_ary_on_submit', compact($vars)));
if ($request->variable('mode', '')!='edit')
{
	$db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
}
else
{
	$NewTime=$request->variable('dtime', '')+time()-$request->variable('posttime', '');
	$db->sql_query('UPDATE ' . ATTACHMENTS_TABLE . ' SET attach_comment='.$NewTime." where physical_filename='".$request->variable('pname', '')."'");
}

if($pchLength){
	$sql_ary = array(
		'physical_filename'	=> $request->variable('PchName', ''),
		'attach_comment'	=> time()-$request->variable('posttime', ''),
		'real_filename'		=> $realPchName,
		'extension'			=> ltrim($pchext,'.'),
		'mimetype'			=> 'application/octet-stream',
		'filesize'			=> strlen($PCHdata),
		'filetime'			=> time(),
		'thumbnail'			=> 0,
		'is_orphan'			=> 1,
		'in_message'		=> ($is_message) ? 1 : 0,
		'poster_id'			=> $user->data['user_id'],
	);

	/**
	* Modify attachment sql array on submit
	*
	* @event core.modify_attachment_sql_ary_on_submit
	* @var	array	sql_ary		Array containing SQL data
	* @since 3.2.6-RC1
	*/
	$vars = array('sql_ary');
	extract($phpbb_dispatcher->trigger_event('core.modify_attachment_sql_ary_on_submit', compact($vars)));
if ($request->variable('mode', '')!='edit')
{
	$db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
}else
{
	$NewTime=$request->variable('dtime', '')+time()-$request->variable('posttime', '');
	$db->sql_query('UPDATE ' . ATTACHMENTS_TABLE . ' SET attach_comment='.$NewTime." where physical_filename='".$request->variable('PchName', '')."'");
}
	

}
die("ok");
/**
 * Calculate drawing time
 * @param $starttime
 * @return string
 */
function calcPtime ($psec) {
	global $en;

	$D = floor($psec / 86400);
	$H = floor($psec % 86400 / 3600);
	$M = floor($psec % 3600 / 60);
	$S = $psec % 60;

	return
		($D ? $D.'day '  : '')
		. ($H ? $H.'hr ' : '')
		. ($M ? $M.'min ' : '')
		. ($S ? $S.'sec' : '')
		. ((!$D&&!$H&&!$M&&!$S) ? '0sec':'');


}
/*
//Get IP Address
function get_uip(){
	$ip = $request->server("HTTP_CLIENT_IP") ? $request->server("HTTP_CLIENT_IP") :'';
	$ip = $ip ? $ip : ($request->server("HTTP_INCAP_CLIENT_IP") ? $request->server("HTTP_INCAP_CLIENT_IP") : '');
	$ip = $ip ? $ip : ($request->server("HTTP_X_FORWARDED_FOR") ? $request->server("HTTP_X_FORWARDED_FOR") : '');
	$ip = $ip ? $ip : ($request->server("REMOTE_ADDR") ? $request->server("REMOTE_ADDR") : '');
	if (strstr($ip, ', ')) {
		$ips = explode(', ', $ip);
		$ip = $ips[0];
	}
	return $ip;
}
*/
?>