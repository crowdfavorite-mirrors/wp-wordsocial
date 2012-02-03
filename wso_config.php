<?php
/*  Copyright 2011  Tommy Leunen (t@tommyleunen.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
$sessid = session_id();
if(empty($sessid)) session_start();

define('WSO_PLUGIN_URL', WP_PLUGIN_URL . '/wordsocial/');
define('WSO_ABS_PLUGIN_URL', ABSPATH . 'wp-content/plugins/wordsocial/');
define('WSO_IMAGES_FOLDER', WP_PLUGIN_URL . '/wordsocial/medias/');
define('WSO_OPTIONS', 'wso_opt');
define('WSO_VERSION', '0.5.2');
define('WSO_DEBUG', false);
define('WSO_DB_LOG', 'wso_logs');

require_once('inc/facebook/facebook.php');
require_once('inc/twitteroauth.php');
//require_once('inc/tumblroauth.php');
require_once('inc/linkedin.php');
require_once('inc/wsourl.php');


class ConfigService
{
	public function __construct($name, $instance)
	{
		$this->name = $name;
		$this->instance = $instance;
	}
	public function __get($name)
	{
		if($name == 'name') return $this->name;
		elseif($name == 'instance') return $this->instance;
		return null;
	}
}
	
$wso_services = array();
function wso_add_service($name, $abbr, $instance)
{
	global $wso_services;
	
	if(isset($wso_services[$abbr]))
	{
		// error!!!
		$msg = "WordSocial Critical Error: A service already exist with the same abbreviation";
		
		$msg .= "<pre>Array<br/>(";
		foreach($wso_services as $key => $val)
		{
			$msg .= "\t[".$key."] => ".$val->name;
		}
		
		$msg .= "<br/>)</pre>";
		
		$msg .= "And you try to add: [".$abbr."] => ".$name;
		
		die($msg);
	}
	$wso_services[$abbr] = new ConfigService($name, $instance);
}

require_once('inc/services/facebook.php');
require_once('inc/services/twitter.php');
require_once('inc/services/linkedin.php');
//require_once('inc/services/tumblr.php');
//require_once('inc/services/googleplus.php');

function wso_get_post_types()
{
	$types = get_post_types();
	unset($types['attachment']);
	unset($types['revision']);
	unset($types['nav_menu_item']);
	
	// forum bbpress 2.0
	if(isset($types['forum'])) unset($types['forum']);
	if(isset($types['topic'])) unset($types['topic']);
	if(isset($types['reply'])) unset($types['reply']);
	
	return $types;
}

function wso_log_msg($msg)
{
	global $wpdb, $blog_id;
	
	$data = array(
		'blog_id' => $blog_id,
		'time' => date("Y-m-d H:i:s", time()),
		'message' => $msg
	);
	$format = array('%d', '%s', '%s');
	$wpdb->insert($wpdb->prefix . WSO_DB_LOG, $data, $format);
}