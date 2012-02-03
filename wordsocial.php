<?php
/*
	Plugin Name: WordSocial
	Plugin URI: http://wso.li/
	Description: Allows you to publish your posts and pages on Social Networks.
	Version: 0.5.3
	Author: Tommy Leunen
	Author URI: http://www.tommyleunen.com
	License: GPLv2
*/

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

require_once('wso_config.php');
require_once('wso_admin.php');

add_action('plugins_loaded', 'wso_plugins_loaded');
function wso_plugins_loaded()
{
	global $wpdb;
	//load_plugin_textdomain('wordsocial', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/');
	
	$wsoOpts = get_option(WSO_OPTIONS);
	
	if ($wsoOpts['version'] < 0.5)
	{
		$wpdb->query('DROP TABLE '. $wpdb->prefix . WSO_DB_LOG);
		wso_create_db();
		
		unset($wsoOpts['time']);
		$wsoOpts['fbpat'] = array($wsoOpts['fbaid'] => $wsoOpts['fbpat']);
		unset($wsoOpts['fbaid']);
		$wsoOpts['version'] = WSO_VERSION;
		update_option(WSO_OPTIONS, $wsoOpts);
	}
	
	if ($wsoOpts['version'] < WSO_VERSION) // 0.5.1
	{		
		$wsoOpts['version'] = WSO_VERSION;
		update_option(WSO_OPTIONS, $wsoOpts);
	}
}

register_activation_hook(__FILE__, 'wso_activation');
function wso_activation()
{
	$opts = array(
		'version' => WSO_VERSION,
		'pict' => WSO_PLUGIN_URL . "medias/wso.jpg",
		'pictret' => ""
	);
	// add the configuration options
	add_option(WSO_OPTIONS, $opts);
	
	wso_create_db();
}

register_uninstall_hook(__FILE__, 'wso_deactivation');
function wso_deactivation()
{
	global $wpdb;
	
	$wpdb->query('DROP TABLE '. $wpdb->prefix . WSO_DB_LOG);
	
	delete_option(WSO_OPTIONS);
}

function wso_create_db()
{
	global $wpdb;
	
	$tableExist = false;
		
	$tables = $wpdb->get_results("show tables");
	foreach($tables as $table)
	{
		foreach($table as $value)
		{
			if($value == $wpdb->prefix . WSO_DB_LOG)
			{
				$tableExist = true;
				break;
			}
		}
	}
	
	if(!$tableExist)
	{
		$sql = "CREATE TABLE " . $wpdb->prefix . WSO_DB_LOG . " (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`blog_id` INT NOT NULL ,
			`time` DATETIME NOT NULL ,
			`message` text NOT NULL
			) ENGINE = MYISAM ;";
		$wpdb->query($sql);
	}
}

add_action('save_post', 'wso_save_post', 1);
function wso_save_post($postid)
{
	global $wso_services;
	
	//only save the content when it's a revision ?
	$postParent = wp_is_post_revision($postid);
	if($postParent)
	{		
		$_wso_opts = unserialize(get_post_meta($postParent, '_wso_opts', true));
		if($_wso_opts === false)
		{
			$_wso_opts = array();
			$_wso_opts['lastPublished'] = 0;
			add_post_meta($postParent, '_wso_opts', serialize($_wso_opts), true);
		}
		
		$_wso_opts['publishTo'] = array();
		foreach($wso_services as $servAbbr => $serv)
		{
			$_wso_opts['publishTo'][$servAbbr] = isset($_POST['post_wso_'.$servAbbr]) ? 1 : 0;
			if($_wso_opts['publishTo'][$servAbbr])
			{
				$_wso_opts['publishTo'][$servAbbr.'_com'] = isset($_POST['wso_comment_'.$servAbbr]) ? $_POST['wso_comment_'.$servAbbr] : '';
			}
		}
		
		update_post_meta($postParent, '_wso_opts', serialize($_wso_opts));
	}
	else
	{
		//clear all postmeta
		//wso_clear_postmeta($postid);
	}

	// ONLY FOR Press This !!
	if ( isset($_POST['press-this']) && wp_verify_nonce($_POST['press-this'], 'press-this') )
	{
		wso_publish_post($postid);
	}
}

add_action('init', 'wso_init');
function wso_init()
{
	// need to do this in the init function, otherwise it's not working with network sites
	$types = wso_get_post_types();
	foreach($types as $type)
	{
		add_action('publish_'.$type, 'wso_publish_post', 99);
	}
}

function wso_publish_post($postid)
{
	global $wso_services;

	$wsoOpts = get_option(WSO_OPTIONS);
	$_wso_opts = unserialize(get_post_meta($postid, '_wso_opts', true));
	
	//wso_log_msg('publish!');
	
	$countMustPublish = array_count_values($_wso_opts['publishTo']);	
	if(isset($countMustPublish[1]) && $countMustPublish[1] > 0)
	{		
		$publishargs['postid'] = $postid;
		$publishargs['servEnable'] = array();
		foreach($wso_services as $servAbbr => $serv)
		{
			if(isset($_wso_opts['publishTo'][$servAbbr]) && $_wso_opts['publishTo'][$servAbbr])
			{
				$publishargs['servEnable'][$servAbbr] = $_wso_opts['publishTo'][$servAbbr];
				if($_wso_opts['publishTo'][$servAbbr])
				{
					$publishargs['comments'][$servAbbr] = $_wso_opts['publishTo'][$servAbbr.'_com'];
				}
			}
		}
		
		//wso_log_msg('wso_publish_post publish post');
		wso_publish($publishargs);
	}
}

function wso_publish($arr)
{
	global $wpdb, $wso_services;
	extract($arr);
	
	//var_dump($arr);	
	$_wso_opts = get_post_meta($postid, '_wso_opts', true);
	$_wso_opts = unserialize($_wso_opts);
			
	if(/*1 || */time() - $_wso_opts['lastPublished'] > 60)
	{
		$_wso_opts['lastPublished'] = time();
		
		foreach($wso_services as $servAbbr => $serv)
		{
			if(isset($servEnable[$servAbbr]) && $servEnable[$servAbbr])
			{
				$serv->instance->publish($postid, $comments[$servAbbr]);
			}
		}
	}
	update_post_meta($postid, '_wso_opts', serialize($_wso_opts));
}

function wso_clear_postmeta($postid)
{
	$_wso_opts = get_post_meta($postid, '_wso_opts', true);
	$_wso_opts = unserialize($_wso_opts);
	
	foreach($wso_services as $servAbbr => $serv)
	{
		unset($_wso_opts['publishTo'][$servAbbr]);
		unset($_wso_opts['publishTo'][$servAbbr.'_com']);
	}
	unset($_wso_opts['publishTo']);
	update_post_meta($postid, '_wso_opts', serialize($_wso_opts));
}