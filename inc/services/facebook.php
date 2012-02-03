<?php
/*
 * Tommy Leunen (t@tommyleunen.com) http://www.tommyleunen.com
 */

require_once(dirname(__FILE__) .'/service.php');
require_once(dirname(__FILE__) .'/../twitteroauth.php');

class WSO_Facebook extends WSO_Service
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
	
	private $m_fb;
	private $m_fbAccounts;
	private $m_options = array(
		'access_token' => 'fbat',
		'options' => 'fbopts',
		'pages_access_token' => 'fbpat'
	);
	const WSO_OPT_PICTURE	= 0;
	const WSO_OPT_START_CUSTOM_TYPE	= 1;
	// custom types are 1,2,3,...
	
	private function __construct()
	{		
		$this->m_fb = new Facebook(array(
			'appId'  => '198517920178057',
			'secret' => '52e29c0fd4f0e233db6120e4b0189a37'
		));
	}
		
	private function internalCallApi($page, $argsOrType = array(), $argsIfType = array(), $postid = null)
	{
		try
		{
			return $this->m_fb->api($page, $argsOrType, $argsIfType);
		}
		catch(FacebookApiException $e)
		{
			if($e->getType() == "OAuthException")
			{
				$this->disconnect();
			}
			wso_log_msg(sprintf(__('An error appeared with Facebook (Reason : %s)','wordsocial'),
				get_bloginfo('wpurl')."/wp-admin/post.php?post=".$postid."&action=edit", $e));
		}
		return 0;
	}
	
	protected function authenticate()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		return $this->internalCallApi('/me', array('access_token' => $wsoOpts[$this->m_options['access_token']]));
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
		
		$lnk = get_permalink($postid) . "?utm_source=Facebook&utm_medium=social&utm_campaign=WordSocial";
		$lnkReShares = get_permalink($postid) . "?utm_source=Facebook Resharer&utm_medium=social&utm_campaign=WordSocial";
		$opts = explode("|", $wsoOpts[$this->m_options['options']]);
		
		foreach($wsoOpts[$this->m_options['pages_access_token']] as $paid => $pat)
		{
			$args['access_token'] = $pat;
			$args['message'] = stripslashes($comment);
			$args['name'] = $this->qTrans($post->post_title);
			$args['caption'] = str_replace('http://', '', get_option('siteurl'));
			$args['link'] = $lnk;
			$args['description'] = substr($this->strip_tags($this->qTrans(do_shortcode($post->post_content))), 0, 400)."...";
			$args['actions'] = array('name' => __('Share', 'wordsocial'), 
									 'link' => 'http://www.facebook.com/share.php?u='.urlencode($lnkReShares)
									 );
			if((int)$opts[self::WSO_OPT_PICTURE] == 1)
			{
				$args['picture'] = $this->getImage($postid, $wsoOpts);
			}
					
			$fbpost = $this->internalCallApi('/'.$paid.'/feed/', 'post', $args, $postid);
		}
	}
	
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////// Admin Page //////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////	
	public function connect()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$wsoOpts[$this->m_options['access_token']] = $_GET['fbt'];
		$wsoOpts[$this->m_options['pages_access_token']] = array('me' => $_GET['fbt']);
		
		$opts = array();
		$opts[self::WSO_OPT_PICTURE] = 1;
		$types = wso_get_post_types();
		$idx = self::WSO_OPT_START_CUSTOM_TYPE;
		foreach($types as $type => $label)
		{
			$opts[$idx++] = ($idx == self::WSO_OPT_START_CUSTOM_TYPE+1) ? 1 : 0;
		}
		$wsoOpts[$this->m_options['options']] = implode('|', $opts);
				
		update_option(WSO_OPTIONS, $wsoOpts);
		wp_redirect(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
	}
	
	public function disconnect()
	{
		$wsoOpts = get_option(WSO_OPTIONS);

		unset($wsoOpts[$this->m_options['access_token']]);
		unset($wsoOpts[$this->m_options['pages_access_token']]);
		unset($wsoOpts[$this->m_options['options']]);

		update_option(WSO_OPTIONS, $wsoOpts);
		$redir = "http://wso.li/iconnectFb.php?logout&r=". urlencode(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
		wp_redirect($redir);
	}
	
	public function savePage()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(isset($wsoOpts[$this->m_options['access_token']]) && $this->m_fbAccounts != 0)
		{
			$pagesId = $_POST['wsofb_access_token'];
			$pat = array();
			foreach($pagesId as $id)
			{
				if($id > -1 && $id < count($this->m_fbAccounts['data']))
				{
					$pat[$this->m_fbAccounts['data'][$id]['id']] = $this->m_fbAccounts['data'][$id]['access_token'];
				}
				else
				{
					$pat['me'] = $wsoOpts[$this->m_options['access_token']];
				}
			}
			$wsoOpts[$this->m_options['pages_access_token']] = $pat;

			//fb opts
			$types = wso_get_post_types();
			$idx = self::WSO_OPT_PICTURE;
			$opts[$idx++] = $_POST['wsofb_showpicture'];
			
			foreach($types as $type => $label)
			{
				$opts[$idx++] = $_POST['wsofbpublish'][$type];
			}
			$wsoOpts[$this->m_options['options']] = implode('|', $opts);
				
			update_option(WSO_OPTIONS, $wsoOpts);
		}
	}
	
	public function beforeShowingPage()
	{		
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$this->m_fbAccounts = 0;
		if(isset($wsoOpts[$this->m_options['access_token']]))
		{
			$this->m_fbAccounts = $this->internalCallApi('/me/accounts', array('access_token' => $wsoOpts[$this->m_options['access_token']]));
			// return array(1) { ["data"]=> array(0) { } }  if no fanpages/application
		}
	}
	
	public function showPage()
	{	
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(!isset($wsoOpts[$this->m_options['access_token']]))
		{
			$urlPlugin = urlencode(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
			
			echo '<p>'. _e('If you want to auto-publish your blog posts/pages on your facebook wall (or on the wall of your fanpages), you have to connect to your FB account', 'wordsocial') .'</p>';
			echo '<p><a href="http://wso.li/iconnectFb.php?login&r='. $urlPlugin .'"><img height="21" width="169" src="'. WSO_IMAGES_FOLDER .'signin-facebook.gif" alt="signin facebook" /></a></p>';
		}
		else
		{
			$user = $this->authenticate();

			$opts = explode("|", $wsoOpts[$this->m_options['options']]);
			// select account
			$msgAccountsSelect =  __('Choose the page on which you would like to publish your posts', 'wordsocial');
			$accountsSelect = "<option value='-1' ". ( (in_array($wsoOpts[$this->m_options['access_token']], $wsoOpts[$this->m_options['pages_access_token']])) ? " selected='selected'" : '' ) .">". $user['name'] ." (My Wall)</option>";
			foreach($this->m_fbAccounts['data'] as $k => $v) :
				$sel = (in_array($v['access_token'], $wsoOpts[$this->m_options['pages_access_token']])) ? ' selected="selected"' : '';
				$accountsSelect .= "<option value='$k' $sel>{$v['name']} ({$v['category']})</option>";
			endforeach;
			
			$types = wso_get_post_types();
			
?>
<p><?php _e('Hello', 'wordsocial'); ?> <strong><?php echo $user['name']; ?></strong>. <a href="options-general.php?page=wordsocial&disconnect=fb">[<?php _e('Disconnect', 'wordsocial'); ?>]</a></p>
<table class="form-table">
	<tbody>
		<tr>
			<th><?php _e('Choose the page on which you would like to publish your posts', 'wordsocial'); ?></th>
			<td>
				<select name="wsofb_access_token[]" id="wsofbat" multiple="multiple" size="5" style="height: auto;"><?php echo $accountsSelect; ?></select>
			</td>
		</tr>
	<?php
	$types = wso_get_post_types();
	$i = self::WSO_OPT_START_CUSTOM_TYPE;
	foreach($types as $type => $label)
	{
		echo "<tr>";
		echo "\t<th>". sprintf(__('Publish "%s" by default', 'wordsocial'), $label) ."</th>";
		echo "\t<td>";
		echo "\t\t<input type='radio' value='1' name='wsofbpublish[{$type}]' id='wsofbenable{$i}'". ( ((int)$opts[$i] == 1) ? ' checked="checked"' : '' ) ." /> <label for='wsofbenable{$i}'>". __('Yes', 'wordsocial') ."</label> <input type='radio' value='0' name='wsofbpublish[{$type}]' id='wsofbdisable{$i}'". ( ((int)$opts[$i] == 0) ? ' checked="checked"' : '' ) ." /> <label for='wsofbdisable{$i}'>". __('No', 'wordsocial') ."</label>";
		echo "\t</td>";
		echo "</tr>";
		++$i;
	} ?>
		<tr>
			<th><?php _e('Show an image ?', 'wordsocial'); ?></th>
			<td>
				<input type="radio" value="1" name="wsofb_showpicture" id="wsofb_show_picture"<?php echo ( ((int)$opts[self::WSO_OPT_PICTURE] == 1) ? ' checked="checked"' : '' ); ?> /> <label for="wsofb_show_picture"><?php _e('Yes', 'wordsocial'); ?></label> <input type="radio" value="0" name="wsofb_showpicture" id="wsofb_hide_picture"<?php echo ( ((int)$opts[self::WSO_OPT_PICTURE] == 0) ? ' checked="checked"' : '' ); ?> /> <label for="wsofb_hide_picture"><?php _e('No', 'wordsocial'); ?></label>
			</td>
		</tr>
	</tbody>
</table>
<?php
		}
	}
}

wso_add_service("Facebook", "fb", WSO_Facebook::get());