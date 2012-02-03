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
add_filter( 'plugin_action_links', 'wso_plugin_action_links', 10, 2 );
function wso_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/wordsocial.php' ) ) {
		$links[] = '<a href="options-general.php?page=wordsocial">'.__('Settings').'</a>';
	}
	return $links;
}

add_action( 'admin_init', 'wso_admin_init' );
function wso_admin_init()
{
	wp_register_style( 'wso_admin_styles', plugins_url('medias/admin.css', __FILE__) );
	wp_register_script( 'wso_admin_scripts', plugins_url('medias/admin.js', __FILE__));
	
	wp_register_style( 'wso_admin_posts_styles', plugins_url('medias/admin-posts.css', __FILE__) );
	
	$wsoOpts = get_option(WSO_OPTIONS);
	wso_admin_page_connect($wsoOpts);
}


add_action('admin_menu', 'wso_admin_menu');
function wso_admin_menu()
{
	$allowedGroup = 'manage_options'; //admin		
	$page = add_submenu_page('options-general.php', "WordSocial", "WordSocial", $allowedGroup, 'wordsocial', 'wso_admin_page');
	
	add_action( 'admin_print_styles-' . $page, 'wso_admin_print_styles' );
	add_action( 'admin_print_scripts-' . $page, 'wso_admin_print_scripts' );
	
	add_action( 'admin_print_styles-post.php', 'wso_admin_posts_print_styles' );
	add_action( 'admin_print_styles-post-new.php', 'wso_admin_posts_print_styles' );

}

add_action('admin_head', 'wso_admin_head');
function wso_admin_head()
{
}

function wso_admin_print_styles()
{
	wp_enqueue_style( 'wso_admin_styles' );
}

function wso_admin_print_scripts()
{
	wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-tabs');
	
	wp_enqueue_script( 'wso_admin_scripts' );
}

function wso_admin_posts_print_styles()
{
	wp_enqueue_style( 'wso_admin_posts_styles' );
}

function wso_admin_posts_print_scripts()
{
	wp_enqueue_script( 'wso_admin_scripts' );
}


add_action('admin_notices', 'wso_admin_notices');
function wso_admin_notices()
{
	global $wpdb;
	
	if(isset($_GET['wso_clear']) && $_GET['wso_clear'] == 1)
	{
		$wpdb->query('TRUNCATE TABLE ' . $wpdb->prefix . WSO_DB_LOG);
	}
	
	$results = $wpdb->get_results("SELECT time, message FROM " . $wpdb->prefix . WSO_DB_LOG . " ORDER BY id DESC");

	if(!empty($results)) :
		$wsoInfo = __("WordSocial Information", 'wordsocial');
		$clear = __("<a href='options-general.php?page=wordsocial&wso_clear=1'>Clear all messages</a>", 'wordsocial');
		
		$content = "";
		foreach($results as $res)
		{
			$content .= '<li>'. $res->time .' - '. $res->message .'</li>';
		}
	
		echo <<<PAGE
<div style="background: #EAEAAE; border: 1px solid #DBDB70; padding: 5px; margin: 25px auto auto auto; width: 85%;">
<h3>$wsoInfo</h3>
<ul>
	$content
</ul>
$clear
</div>
PAGE;
	endif;
}

add_action('add_meta_boxes', 'wso_add_meta_boxes');
function wso_add_meta_boxes()
{
	$pp = wso_get_post_types();
	foreach($pp as $p)
	{
		add_meta_box( 
			'wso_wordsocial',
			__( 'WordSocial', 'wordsocial' ),
			'wso_inner_meta_boxes',
			$p,
			'side',
			'core'
		);
	}
}

function wso_inner_meta_boxes()
{
	global $wso_services;
	
	$wsoOpts = get_option(WSO_OPTIONS);
	
	$services = array();
	
	foreach($wso_services as $servAbbr => $serv)
	{
		if(isset($wsoOpts[$servAbbr.'at']))
		{
			array_push($services, array($servAbbr, $serv));
		}
	}
	
	if(count($services) == 0)
	{
		echo "<p><a href='./options-general.php?page=wordsocial'>Please configure WordSocial</a>.</p>";
		return;
	}
	
	echo "<div id='post-wso'>";
	echo "\t<p>Publish on:</p>";
	echo "\t<table>";
	
	$servicesContent = '';
	$showComment = false;
	foreach($services as $serv)
	{
		$autoPublish = $serv[1]->instance->isAutoPublish(get_post_type());
		$msg = sprintf(__('Publish on %s', 'wordsocial'), $serv[1]->name);
		
		$checked = ($autoPublish) ? ' checked="checked"' : '';
		echo "<tr>";
		echo "<td><input type='checkbox' value='{$autoPublish}' name='post_wso_{$serv[0]}' id='wso_id_input_{$serv[0]}'{$checked} /></td>";
		echo "<td><label for='wso_id_input_{$serv[0]}'>{$serv[1]->name}</label></td>";
		echo "<td style='width: 100%;'><input type='text' name='wso_comment_{$serv[0]}' style='width: 100%;' /></td>";
		echo "</tr>";
	}
	echo "</table>";

	echo '</div>';
}

function wso_admin_page_connect(&$wsoOpts)
{
	global $wso_services;
	
	foreach($wso_services as $servAbbr => $serv)
	{
		if(isset($_GET['connect']) && $_GET['connect'] == $servAbbr)
		{
			$serv->instance->connect();
		}
		if(isset($_GET['disconnect']) && $_GET['disconnect'] == $servAbbr)
		{
			$serv->instance->disconnect();
		}
	}
}

function wso_admin_page_save()
{
	global $wso_services;
	
	//var_dump($_POST);
	if(isset($_POST['submit']))
	{
		$wsoOpts = get_option(WSO_OPTIONS);
				
		//pict
		$wsoOpts['pictret'] = $_POST['wsoretpict'];
		$pict = (!empty($_POST['wsopicture'])) ? $_POST['wsopicture'] : WSO_PLUGIN_URL . "medias/wso.jpg";
		$wsoOpts['pict'] = $pict;
		
		update_option(WSO_OPTIONS, $wsoOpts);
				
		foreach($wso_services as $servAbbr => $serv)
		{
			$serv->instance->savePage();
		}
	}
}

function wso_admin_page()
{
	global $wso_services;
	
	foreach($wso_services as $servAbbr => $serv)
	{
		$serv->instance->beforeShowingPage();
	}
	
	wso_admin_page_save();
	
	$wsoOpts = get_option(WSO_OPTIONS);
	
	if(WSO_DEBUG)
	{
		echo "<pre>" . print_r($wsoOpts, true) . "</pre>";
	}
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"><br></div>
	<h2>WordSocial</h2>
		
	<form action="options-general.php?page=wordsocial" method="post" name="form" id="wso_leftCont">
		<div id="wso-page">
			<ul>
				<li><a href="#wso-page-general">General</a></li>
			<?php
				foreach($wso_services as $servAbbr => $serv)
				{
					echo '<li><a href="#wso-page-'.$servAbbr.'">'.$serv->name.'</a></li>';
				}
			?>
			</ul>
		
			<div id="wso-page-general" class="wso_page"><?php wso_show_page_general(); ?></div>
			<?php
				foreach($wso_services as $servAbbr => $serv)
				{
					echo '<div id="wso-page-'.$servAbbr.'" class="wso_page">';
					$serv->instance->showPage();
					echo '</div>';
					
				}
			?>
		</div>
		<p class="submit">
			<input id="submit" class="button-primary" type="submit" value="<?php _e('Save changes', 'wordsocial') ?>" name="submit">
		</p>
	</form>
	<div id="wso_sidebar"><?php wso_show_sidebar(); ?></div>
</div>
<?php
}

function wso_show_sidebar()
{
?>
<div class="postbox-container">
	<div class="metabox-holder">
		<div class="postbox">
			<h3 style="cursor: default"><?php _e("Do you like WordSocial ?", "wordsocial"); ?></h3>
			<div class="inside">
				<p><?php printf(__("This plugin is developed by %s. Any contribution would be greatly apprecieted. Thank you very much!", 'wordsocial'), "<a href='http://www.tommyleunen.com'>Tommy Leunen</a>"); ?></p>
				<ul>
					<li style="padding-left: 38px; background: url('<?php echo plugins_url('medias/rate.png', __FILE__);?>') no-repeat scroll 16px 50% transparent; text-decoration: none;">
						<a href="http://wordpress.org/extend/plugins/wordsocial/" target="_blank"><?php _e("Rate the plugin on WordPress.org", 'wordsocial'); ?></a>
					</li>
					<li style="padding-left: 38px; background: url('<?php echo plugins_url('medias/fb.png', __FILE__);?>') no-repeat scroll 16px 50% transparent; text-decoration: none;">
						<a href="http://www.facebook.com/WordSocial?sk=reviews" target="_blank"><?php _e("Rate the plugin on Facebook App", 'wordsocial'); ?></a>
					</li>
					<li style="padding-left: 38px; background: url('<?php echo plugins_url('medias/paypal.png', __FILE__);?>') no-repeat scroll 16px 50% transparent; text-decoration: none;">
						<a href="http://wso.li/donation" target="_blank"><?php _e("Buy me a coffee (Donate with Paypal)", 'wordsocial'); ?></a>
					</li>
				</ul>
				<?php _e("Don't forget to send me an email with your blog url and your email (paypal) if you send me a donation, so I could add you in the list of donators on the wso.li website and into the plugin.", 'wordsocial'); ?>
				<iframe src="http://www.facebook.com/plugins/likebox.php?href=https%3A%2F%2Fwww.facebook.com%2Fapps%2Fapplication.php%3Fid%3D198517920178057&amp;width=250&amp;colorscheme=light&amp;show_faces=false&amp;stream=false&amp;header=false&amp;height=62" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:250px; height:62px;" allowTransparency="false"></iframe>
			</div>
		</div>
	</div>
</div>
<div class="postbox-container">
	<div class="metabox-holder">
		<div class="postbox">
			<h3 style="cursor: default"><?php _e("From the same author...", "wordsocial"); ?></h3>
			<div class="inside">
				<ul>
					<li>
						<a href="http://wordpress.org/extend/plugins/simple-countdown-timer/" target="_blank">Simple Countdown Timer</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>
<div class="postbox-container">
	<div class="metabox-holder">
		<div class="postbox">
			<h3 style="cursor: default"><?php _e("Any problems ?", "wordsocial"); ?></h3>
			<div class="inside">
				<p><?php printf(__("If you've some difficulties to use the plugin (error, asking, or anything), feel free to post your inquiry in %s, on %s, with %s or by %s."), 
				'<a href="http://wordpress.org/tags/wordsocial?forum_id=10">the WP forum</a>', '<a href="http://www.facebook.com/apps/application.php?id=198517920178057">Facebook</a>', '<a href="http://twitter.com/tommy">@Tommy</a>', '<a href="mailto:tom@tommyleunen.com?subject=WordSocial">email</a>'); ?></p>
				<p><?php _e('Thanks again for using WordSocial.'); ?></p>
			</div>
		</div>
	</div>
</div>
<?php
// donators
$filec = "";
$filec = @file_get_contents("http://wso.li/donators.txt");
if(!empty($filec)) :
?>
<div class="postbox-container">
	<div class="metabox-holder">
		<div class="postbox">
			<h3 style="cursor: default"><?php _e("Donators", "wordsocial"); ?></h3>
			<div class="inside">
				<p><?php echo nl2br($filec); ?></p>
			</div>
		</div>
	</div>
</div>
<?php
endif;
}

function wso_show_page_general()
{
	$wsoOpts = get_option(WSO_OPTIONS);
		
	// retrieve image
	$hideField_wsoretpict = ($wsoOpts['pictret'] == 'feat') ? "jQuery('#wsoretpict').hide();" : '';
?>
<table class="form-table">
	<tbody>
		<tr>
			<th><?php _e("When it's possible to publish the content with an image, how do you like WordSocial get the image ?", 'wordsocial'); ?></th>
			<td>
				<input type="radio" value="feat" name="wsoretpicture" id="wsoretpicture1" <?php echo ( ($wsoOpts['pictret'] == 'feat') ? ' checked="checked"' : '' ); ?> /> <label for="wsoretpicture1"><?php _e('Featured Image', 'wordsocial'); ?></label>
				<input type="radio" value="custom" name="wsoretpicture" id="wsoretpicture2" <?php echo ( ($wsoOpts['pictret'] != 'feat' && $wsoOpts['pictret'] != 'none') ? ' checked="checked"' : '' ); ?> /> <label for="wsoretpicture2"><?php _e('Custom field', 'wordsocial'); ?></label>
				<input type="text" name="wsoretpict" id="wsoretpict" value="<?php echo $wsoOpts['pictret']; ?>" />
				<script type="text/javascript">
				<?php echo $hideField_wsoretpict; ?>
				jQuery('#wsoretpicture1').change(function(){jQuery('#wsoretpict').val("feat");jQuery('#wsoretpict').hide();});
				jQuery('#wsoretpicture2').change(function(){if(jQuery('#wsoretpict').val() == "feat"){jQuery('#wsoretpict').val("");}jQuery('#wsoretpict').show();});
				</script>
		</tr>
		<tr>
			<th>
				<label for="wsopict"><?php _e('Default image', 'wordsocial'); ?></label>
			</th>
			<td>
				<input type="text" style="width: 100%" value="<?php echo $wsoOpts['pict']; ?>" name="wsopicture" id="wsopict" />
			</td>
		</tr>
	</tbody>
</table>
<?php
}