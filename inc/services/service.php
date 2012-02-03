<?php
/*
 * Tommy Leunen (t@tommyleunen.com) http://www.tommyleunen.com
 */

abstract class WSO_Service
{	
	private function __construct()
	{
	}
	
	abstract protected function isAutoPublish($type);
	
	abstract protected function authenticate();
	abstract public function publish($postid, $comment = "");
	
	abstract public function connect();
	abstract public function disconnect();
	abstract public function savePage();
	
	public function beforeShowingPage() {}
	abstract public function showPage();
		
	protected function getSupportedFormats()
	{
		$supportedFormats = array('standard');
		if(current_theme_supports('post-formats'))
		{
			$formats = get_theme_support('post-formats');
			if(is_array($formats[0]))
				$supportedFormats = array_merge($supportedFormats, $formats[0]);
		}
		return $supportedFormats;
	}
	
	protected function getImage($postid, &$wsoOpts)
	{
		$img = '';
		
		// featured image
		if($wsoOpts['pictret'] == 'feat' && current_theme_supports('post-thumbnails'))
		{
			$img = wp_get_attachment_image_src(get_post_thumbnail_id($postid), 'full');
			if($img !== false) $img = $img[0];
		}
		// custom field image
		else if(!empty($wsoOpts['pictret']))
		{
			$img = get_post_meta($postid, $wsoOpts['pictret'], true);	
		}
		
		//image inside the post
		if(empty($img))
		{
			$attachments = get_children(array(
						'post_parent' => $postid,
						'numberposts' => 1,
						'post_type' => 'attachment',
						'post_mime_type' => 'image',
						'order' => 'ASC',
						'orderby' => 'menu_order date'));
								
			if(is_array($attachments) && !empty($attachments))
			{
				foreach($attachments as $att_id => $attachment)
				{
					$img = wp_get_attachment_image_src($att_id, 'full');
					if($img !== false) { $img = $img[0]; break; }
				}
			}
		}
		
		if(empty($img)) $img = $wsoOpts['pict'];
		
		return $img;
	}
	
	protected function qTrans($output)
	{
		if(function_exists('qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage')) 
		{
			$output = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($output);
		}
		return $output;
	}
	
	protected function strip_tags($output)
	{
		$search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
				   '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
				   '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
				   '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments including CDATA
		);
		return preg_replace($search, '', $output); 
	}
}