<?php
/*
 * Tommy Leunen (t@tommyleunen.com) http://www.tommyleunen.com
 */

require_once(dirname(__FILE__) .'/service.php');
require_once(dirname(__FILE__) .'/../twitteroauth.php');

class WSO_Twitter extends WSO_Service
{
	private static $m_instance;
	public static function get()
	{
		if(!isset(self::$m_instance))
		{	
			$c = __CLASS__;
			self::$m_instance = new $c();
		}
		return self::$m_instance;
	}
	
	private $m_OAuth;
	private $m_options = array(
		'access_token' => 'twat',
		'access_token_secret' => 'twats',
		'options' => 'twopts',
		'format' => 'twfmt',
		'short_method' => 'wsourl',
		'short_method_id' => 'wsourlp'
	);
	const WSO_OPT_CATEGORIES_AS_HASHTAGS = 0;
	const WSO_OPT_TAGS_AS_HASHTAGS = 1;
	const WSO_OPT_START_CUSTOM_TYPE = 2;
	// custom types are 2,3,...
	
	private function __construct()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$at = NULL;
		$ats = NULL;
		if(isset($_SESSION['oauth']['twitter']['oauth_token'])) $at = $_SESSION['oauth']['twitter']['oauth_token'];
		if(isset($_SESSION['oauth']['twitter']['oauth_token'])) $ats = $_SESSION['oauth']['twitter']['oauth_token_secret'];
		if(isset($wsoOpts[$this->m_options['access_token']])) $at = $wsoOpts[$this->m_options['access_token']];
		if(isset($wsoOpts[$this->m_options['access_token_secret']])) $ats = $wsoOpts[$this->m_options['access_token_secret']];
		
		$this->m_OAuth = new TwitterOAuth("er4yQn8kiqGsvtDv5FgOA", "AHwFQFi4twWMYSagHAAUMaIPbsEXKq52KSyPNymBQo", $at, $ats);
	}
	
	protected function authenticate()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
			
		return $this->m_OAuth->get('account/verify_credentials');
	}
	
	public function isAutoPublish($type)
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		$opts = explode('|', $wsoOpts[$this->m_options['options']]);

		$types = wso_get_post_types();
		$idx = self::WSO_OPT_START_CUSTOM_TYPE;
		foreach($types as $t => $l)
		{
			if($t == $type) break;
			++$idx;
		}
		
		$publish = (int)$opts[$idx];
		$publish &= ( !isset($_GET['action']) || $_GET['action'] != 'edit' );
		
		return (int) $publish;
	}
		
	public function publish($postid, $comment = "")
	{
		$post = get_post($postid);
		$wsoOpts = get_option(WSO_OPTIONS);
		$twOpts = explode('|', $wsoOpts[$this->m_options['options']]);
		
		$shortenLink = $this->getShortenLink($postid);
		$maxLn = 140;
		
		$tweet = stripslashes($wsoOpts[$this->m_options['format']]);
		
		// atfer 0.5.1 there is no more %comment, %tags and %categories
		$tweet = str_replace(array('%comment', '%tags', '%categories'), '', $tweet);
		
		$tweetTitleLn = (strstr($tweet, '%title') === false) ? 0 : strlen("%title");
		$tweetLinkLn = (strstr($tweet, '%link') === false) ? 0 : strlen("%link");
		$tweetExcerptLn = (strstr($tweet, '%excerpt') === false) ? 0 : strlen("%excerpt");
		
		$tweetLn = strlen($tweet) - $tweetLinkLn - $tweetTitleLn - $tweetExcerptLn;
		
		// no enough space for the link -> Force the format
		if(strlen($shortenLink) > $maxLn-$tweetLn)
		{
			$tweet = "%title %link";
			$tweetTitleLn = strlen("%title");
			$tweetLinkLn = strlen("%link");
		}
		
		if($tweetLinkLn > 0)
		{
			$tweet = str_replace("%link", $shortenLink, $tweet);
			$tweetLn = strlen($tweet) - $tweetTitleLn - $tweetExcerptLn;
		}
		if($tweetTitleLn > 0)
		{
			$postTitle = $this->qTrans($post->post_title);
			$postTitle = (strlen($postTitle) > $maxLn-$tweetLn) ? substr($postTitle, 0, $maxLn-$tweetLn) : $postTitle;
			$tweet = str_replace("%title", $postTitle, $tweet);
			$tweetLn = strlen($tweet) - $tweetExcerptLn;
		}
		
		if($twOpts[self::WSO_OPT_CATEGORIES_AS_HASHTAGS] != 0)
		{
			$categories = get_the_category($postid);
			$catHashtags = ' ';
			foreach($categories as $category)
			{
				$catHashtags .= '#' . str_replace(' ', '', ucwords($category->cat_name));
				$catHashtags .= ' ';
			}
			$catHashtags = rtrim($catHashtags);
			while(strlen($catHashtags) > $maxLn-$tweetLn)
			{
				$catHashtags = substr($catHashtags, 0, strrpos($catHashtags, " "));
			}
			$tweet .= $catHashtags;
			$tweetLn = strlen($tweet) - $tweetExcerptLn;
		}
		if($twOpts[self::WSO_OPT_TAGS_AS_HASHTAGS] != 0)
		{
			$tags = get_the_tags($postid);
			$tagsHashtags = ' ';
			if(is_array($tags))
			{
				foreach($tags as $tag)
				{
					$tagsHashtags .= '#' . str_replace(' ', '', ucwords($tag->name));
					$tagsHashtags .= ' ';
				}
				$tagsHashtags = rtrim($tagsHashtags);
				while(strlen($tagsHashtags) > $maxLn-$tweetLn)
				{
					$tagsHashtags = substr($tagsHashtags, 0, strrpos($tagsHashtags, " "));
				}
				$tweet .= $tagsHashtags;
				$tweetLn = strlen($tweet) - $tweetExcerptLn;
			}
		}
		
		if($maxLn-$tweetLn > 0)
		{
			$comment = stripslashes($comment);
			$postComment = (strlen($comment) > $maxLn-$tweetLn) ? substr($comment, 0, $maxLn-$tweetLn) : $comment;
			$tweet .= ' ' . $postComment;
			$tweetLn = strlen($tweet) - $tweetExcerptLn;
		}
		
		if($tweetExcerptLn > 0)
		{
			$content = $post->post_excerpt;
			if(empty($content)) $content = $post->post_content;
			$postExcerpt = $this->qTrans($content);
			$postExcerpt = (strlen($postExcerpt) > $maxLn-$tweetLn) ? substr($postExcerpt, 0, $maxLn-$tweetLn) : $postExcerpt;
			$tweet = str_replace("%excerpt", $postExcerpt, $tweet);
			$tweetLn = strlen($tweet);
		}
			
		$response = $this->m_OAuth->post('statuses/update', array('status' => $tweet));
		if(isset($response->error))
		{
			wso_log_msg(sprintf(__('Error publishing on Twitter:') . ' <a href="%s">post id %d</a> ['.__('Reason:'). ' ' . $response->error . '] [tweet: %s] [tweetln: %d]',
				get_bloginfo('wpurl')."/wp-admin/post.php?post=".$postid."&action=edit", $postid, $tweet, strlen($tweet)));
		}
	}
	
	private function getShortenLink($postid)
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$lnk = get_permalink($postid) . "?utm_source=Twitter&utm_medium=social&utm_campaign=WordSocial";
		
		switch($wsoOpts[$this->m_options['short_method']])
		{
			case 'bitly' : return WSO_SURL::ShortenBitLy($lnk);
			case 'obitly' : return WSO_SURL::ShortenBitLy($lnk, $wsoOpts[$this->m_options['short_method_id']][0], $wsoOpts[$this->m_options['short_method_id']][1]);
			case 'yourls' : return WSO_SURL::Shorten($lnk, $wsoOpts[$this->m_options['short_method_id']][0], $wsoOpts[$this->m_options['short_method_id']][1]);
			case 'wp' : return wp_get_shortlink($postid);
		}
		return WSO_SURL::Shorten(get_permalink($postid));
	}
	
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////// Admin Page //////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////	
	public function connect()
	{
		$wsoOpts = get_option(WSO_OPTIONS);

		// Request
		if(!isset($_GET['resp']))
		{
			$token = $this->m_OAuth->getRequestToken(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial&connect=tw&resp=1");
			$loginUrl = $this->m_OAuth->getAuthorizeURL($token);
			
			$_SESSION['oauth']['twitter']['oauth_token'] = $token['oauth_token'];
			$_SESSION['oauth']['twitter']['oauth_token_secret'] = $token['oauth_token_secret'];
			
			wp_redirect($loginUrl);
		}
		// Response
		else
		{
			$token = $this->m_OAuth->getAccessToken($_GET['oauth_verifier']);
			$wsoOpts[$this->m_options['access_token']] = $token['oauth_token'];
			$wsoOpts[$this->m_options['access_token_secret']] = $token['oauth_token_secret'];
			$wsoOpts[$this->m_options['format']] = "%title %link";
			$wsoOpts[$this->m_options['short_method']] = "wso";
			$wsoOpts[$this->m_options['short_method_id']] = array('','');
			
			$opts = array();
			$opts[self::WSO_OPT_CATEGORIES_AS_HASHTAGS] = 0;
			$opts[self::WSO_OPT_TAGS_AS_HASHTAGS] = 0;
			$types = wso_get_post_types();
			$idx = self::WSO_OPT_START_CUSTOM_TYPE;
			foreach($types as $type => $label)
			{
				$opts[$idx++] = ($idx == self::WSO_OPT_START_CUSTOM_TYPE+1) ? 1 : 0;
			}
			$wsoOpts[$this->m_options['options']] = implode('|', $opts);
						
			update_option(WSO_OPTIONS, $wsoOpts);			
			session_unset();
			wp_redirect(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
		}
	}
	
	public function disconnect()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		unset($wsoOpts[$this->m_options['access_token']]);
		unset($wsoOpts[$this->m_options['access_token_secret']]);
		unset($wsoOpts[$this->m_options['options']]);
		unset($wsoOpts[$this->m_options['format']]);
		unset($wsoOpts[$this->m_options['short_method']]);
		unset($wsoOpts[$this->m_options['short_method_id']]);
				
		update_option(WSO_OPTIONS, $wsoOpts);
		wp_redirect(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
	}
	
	public function savePage()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(isset($wsoOpts[$this->m_options['access_token']]))
		{
			// post cat/tags as hashtags
			$opts[self::WSO_OPT_CATEGORIES_AS_HASHTAGS] = $_POST['wsotwhashtagscat'];
			$opts[self::WSO_OPT_TAGS_AS_HASHTAGS] = $_POST['wsotwhashtagstags'];
			
			// post type
			$types = wso_get_post_types();
			$idx = self::WSO_OPT_START_CUSTOM_TYPE;
			foreach($types as $type => $label)
			{
				$opts[$idx++] = $_POST['wsotwpublish'][$type];
			}
			$wsoOpts[$this->m_options['options']] = implode('|', $opts);
			
			// format
			$wsoOpts[$this->m_options['format']] = $_POST['wsotwfmt'];
			
			// shortening method
			$wsoOpts[$this->m_options['short_method']] = $_POST['wsotwwsourl'];
			if($wsoOpts[$this->m_options['short_method']] == 'yourls')
			{
				$wsoOpts[$this->m_options['short_method_id']][0] = $_POST['wso_yourls_url'];;
				$wsoOpts[$this->m_options['short_method_id']][1] = $_POST['wso_yourls_sign'];;
			}
			else if($wsoOpts[$this->m_options['short_method']] == 'obitly')
			{
				$wsoOpts[$this->m_options['short_method_id']][0] = $_POST['wso_obitly_login'];;
				$wsoOpts[$this->m_options['short_method_id']][1] = $_POST['wso_obitly_apikey'];;
			}
			else $wsoOpts[$this->m_options['short_method_id']] = array();
			
			update_option(WSO_OPTIONS, $wsoOpts);
		}
	}
	
	public function showPage()
	{	
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(!isset($wsoOpts[$this->m_options['access_token']]))
		{				
			echo '<p>'. _e('If you want to auto-publish your blog posts/pages on your Twitter account, you have to authorize WordSocial to publish on it', 'wordsocial') .'</p>';
			echo '<p><a href="options-general.php?page=wordsocial&connect=tw"><img height="16" width="136" src="'. WSO_IMAGES_FOLDER .'signin-twitter.png" alt="signin twitter" /></a></p>';
		}
		else
		{
			$user = $this->authenticate();

			$opts = explode("|", $wsoOpts[$this->m_options['options']]);	
			
?>
<p><?php _e('Hello', 'wordsocial'); ?> <strong><?php echo $user->screen_name; ?></strong>. <a href="options-general.php?page=wordsocial&disconnect=tw">[<?php _e('Disconnect', 'wordsocial'); ?>]</a></p>
<table class="form-table">
	<tbody>
	<?php
	$types = wso_get_post_types();
	$i = self::WSO_OPT_START_CUSTOM_TYPE;
	foreach($types as $type => $label)
	{
		echo "<tr>";
		echo "\t<th>". sprintf(__('Publish "%s" by default', 'wordsocial'), $label) ."</th>";
		echo "\t<td>";
		echo "\t\t<input type='radio' value='1' name='wsotwpublish[{$type}]' id='wsotwenable{$i}'". ( ((int)$opts[$i] == 1) ? ' checked="checked"' : '' ) ." /> <label for='wsotwenable{$i}'>". __('Yes', 'wordsocial') ."</label> <input type='radio' value='0' name='wsotwpublish[{$type}]' id='wsotwdisable{$i}'". ( ((int)$opts[$i] == 0) ? ' checked="checked"' : '' ) ." /> <label for='wsotwdisable{$i}'>". __('No', 'wordsocial') ."</label>";
		echo "\t</td>";
		echo "</tr>";
		++$i;
	} ?>
		<tr>
			<th><?php _e('Use post categories as #hashtags ?', 'wordsocial'); ?></th>
			<td>
				<input type='radio' value='1' name='wsotwhashtagscat' id='wsotwhashtagscatYes'<?php echo ( ((int)$opts[self::WSO_OPT_CATEGORIES_AS_HASHTAGS] == 1) ? ' checked="checked"' : '' ); ?> /> <label for='wsotwhashtagscatYes'><?php _e('Yes', 'wordsocial'); ?></label> <input type='radio' value='0' name='wsotwhashtagscat' id='wsotwhashtagscatNo'<?php echo ( ((int)$opts[self::WSO_OPT_CATEGORIES_AS_HASHTAGS] == 0) ? ' checked="checked"' : '' ); ?> /> <label for='wsotwhashtagscatNo'><?php _e('No', 'wordsocial'); ?></label>
			</td>
		</tr>
		<tr>
			<th><?php _e('Use post tags as #hashtags ?', 'wordsocial'); ?></th>
			<td>
				<input type='radio' value='1' name='wsotwhashtagstags' id='wsotwhashtagstagsYes'<?php echo ( ((int)$opts[self::WSO_OPT_TAGS_AS_HASHTAGS] == 1) ? ' checked="checked"' : '' ); ?> /> <label for='wsotwhashtagstagsYes'><?php _e('Yes', 'wordsocial'); ?></label> <input type='radio' value='0' name='wsotwhashtagstags' id='wsotwhashtagstagsNo'<?php echo ( ((int)$opts[self::WSO_OPT_TAGS_AS_HASHTAGS] == 0) ? ' checked="checked"' : '' ); ?> /> <label for='wsotwhashtagstagsNo'><?php _e('No', 'wordsocial'); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="wsotwfmt"><?php _e('Tweet format :', 'wordsocial'); ?></label></th>
			<td>
				<input type="text" style="width: 100%" value="<?php echo stripslashes($wsoOpts[$this->m_options['format']]); ?>" name="wsotwfmt" id="wsotwfmt" />
				<p>%title : <?php _e('Post title', 'wordsocial'); ?><br/>
				%link : <?php _e('Link to the post', 'wordsocial'); ?><br/>
				%excerpt : <?php _e('Excerpt from the post', 'wordsocial'); ?><br/>
			</td>
		</tr>
		<tr>
			<th><?php _e('Shortening method for the link:', 'wordsocial'); ?></th>
			<td>
				<select name="wsotwwsourl" id="wsotwwsourl">
					<option value="bitly"<?php echo ( ($wsoOpts[$this->m_options['short_method']] == "bitly") ? 'selected="selected"' : '' ); ?>>bit.ly</option>
					<option value="obitly"<?php echo ( ($wsoOpts[$this->m_options['short_method']] == "obitly") ? 'selected="selected"' : '' ); ?>><?php _e('Your own bit.ly configuration', 'wordsocial'); ?></option>
					<option value="wso"<?php echo ( ($wsoOpts[$this->m_options['short_method']] == "wso") ? 'selected="selected"' : '' ); ?>>wso.li</option>
					<option value="yourls"<?php echo ( ($wsoOpts[$this->m_options['short_method']] == "yourls") ? 'selected="selected"' : '' ); ?>><?php _e('Your own YOURLS configuration', 'wordsocial'); ?></option>
					<option value="wp"<?php echo ( ($wsoOpts[$this->m_options['short_method']] == "wp") ? 'selected="selected"' : '' ); ?>><?php _e('WordPress shortlink', 'wordsocial'); ?></option>
				</select><br />
				<div id="wso_yourls">
					<?php _e('URL Yourls:', 'wordsocial'); ?> <input type="text" name="wso_yourls_url" value="<?php echo $wsoOpts['wsourlp'][0]; ?>" /> (http://your-domain.com/yourls-api.php)<br />
					<?php _e('Signature:', 'wordsocial'); ?> <input type="text" name="wso_yourls_sign" value="<?php echo $wsoOpts['wsourlp'][1]; ?>" /><br />
				</div>
				<div id="wso_obitly">
					<?php _e('Login Bit.ly:', 'wordsocial'); ?> <input type="text" name="wso_obitly_login" value="<?php echo $wsoOpts['wsourlp'][0]; ?>" /><br />
					<?php _e('API Key:', 'wordsocial'); ?> <input type="text" name="wso_obitly_apikey" value="<?php echo $wsoOpts['wsourlp'][1]; ?>" />
				</div>
				<script type="text/javascript">
				<?php echo ( ($wsoOpts[$this->m_options['short_method']] != "yourls") ? "jQuery('#wso_yourls').hide();" : '' ); ?>
				<?php echo ( ($wsoOpts[$this->m_options['short_method']] != "obitly") ? "jQuery('#wso_obitly').hide();" : '' ); ?>
				jQuery('#wsotwwsourl').change(function(){
					var value = jQuery(this).val();
					if(value == "yourls") { jQuery('#wso_yourls').show(); jQuery('#wso_obitly').hide(); }
					else if(value == "obitly") { jQuery('#wso_obitly').show(); jQuery('#wso_yourls').hide(); }
					else { jQuery('#wso_yourls').hide(); jQuery('#wso_obitly').hide(); }
				});
				</script>
			</td>
		</tr>
	</tbody>
</table>
<?php
		}
	}
}

wso_add_service("Twitter", "tw", WSO_Twitter::get());