<?php
/*
 * Tommy Leunen (t@tommyleunen.com) http://www.tommyleunen.com
 */

require_once(dirname(__FILE__) .'/service.php');
require_once(dirname(__FILE__) .'/../twitteroauth.php');

class WSO_LinkedIn extends WSO_Service
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
	
	private $m_linkedIn;
	private $m_options = array(
		'access_token' => 'liat',
		'access_token_secret' => 'liats',
		'options' => 'liopts'
	);
	const WSO_OPT_PICTURE			= 0;
	const WSO_OPT_START_CUSTOM_TYPE = 1;
	// custom types are 1,2,3,...
	
	private function __construct()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$this->m_linkedIn = new LinkedIn(array(
			'appKey'      => '-Nc0w1VO2MA-J-EBxTrTdhMUNyxD6wPV1G72BDRazoetRIBwCKjhZBrLQR90bWmX',
			'appSecret'   => 'I6CrJdaldRzFnhkwf4_IzDbN8T0yDZvuTC4izFLotoM6_EfqxDl-fh6hRJA1sXQt',
			'callbackUrl' => NULL 
		));
		
		if(isset($wsoOpts[$this->m_options['access_token']]) && isset($wsoOpts[$this->m_options['access_token_secret']]))
		{
			$this->m_linkedIn->setTokenAccess(array(
				'oauth_token' => $wsoOpts[$this->m_options['access_token']],
				'oauth_token_secret' => $wsoOpts[$this->m_options['access_token_secret']]
			));
		}
	}
	
	protected function authenticate()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		$me = "error";
		
		$response = $this->m_linkedIn->profile('~:(id,first-name,last-name,picture-url)');
		if($response['success'] === TRUE)
		{
			$response['linkedin'] = json_decode($response['linkedin'], true);
			$me = $response['linkedin']['firstName'] . " " . $response['linkedin']['lastName'];
		}
		else
		{
			wso_log_msg(__("LinkedIn : Error retrieving profile information",'wordsocial'));
		}
			
		return $me;
	}
	
	public function isAutoPublish($type)
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		$opts = explode('|', $wsoOpts[$this->m_options['options']]);

		$types = wso_get_post_types();
		$idx = 1;
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
		
		$lnk = get_permalink($postid) . "?utm_source=LinkedIn&utm_medium=social&utm_campaign=WordSocial";
		$opts = explode("|", $wsoOpts[$this->m_options['options']]);
		
		$content = array();
		$content['title'] = $this->qTrans($post->post_title);
		$content['comment'] = stripslashes($comment);
		$content['submitted-url'] = $lnk;
		$content['description'] = substr($this->strip_tags($this->qTrans($post->post_content)), 0, 400)."...";
		if((int)$liopts[WSO_LIOPT_IMAGE] == 1)
		{
			$content['submitted-image-url'] = $this->getImage($postid, $wsoOpts);
		}
	
		// Publish
		$response = $this->m_linkedIn->share('new', $content, TRUE);
		if($response['success'] !== TRUE)
		{
			wso_log_msg(sprintf(__('LinkedIn: WordSocial was unable to publish <a href="%s">this post</a> on LinkedIn','wordsocial'),
				get_bloginfo('wpurl')."/wp-admin/post.php?post=".$postid."&action=edit"));
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

		$this->m_linkedIn->setCallbackUrl(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial&connect=li&resp=1");
		
		// Request
		if(!isset($_GET['resp']))
		{
			$response = $this->m_linkedIn->retrieveTokenRequest();
			if($response['success'] === TRUE)
			{
				$_SESSION['oauth']['linkedin']['request'] = $response['linkedin'];
				wp_redirect(LINKEDIN::_URL_AUTH . $response['linkedin']['oauth_token']);
			} 
			else
			{
				wso_log_msg(__('LinkedIn : Request token retrieval failed','wordsocial'));
			}
		}
		// Response
		else
		{
			$response = $this->m_linkedIn->retrieveTokenAccess($_GET['oauth_token'], $_SESSION['oauth']['linkedin']['request']['oauth_token_secret'], $_GET['oauth_verifier']);
			if($response['success'] === TRUE)
			{
				$wsoOpts[$this->m_options['access_token']] = $response['linkedin']['oauth_token'];
				$wsoOpts[$this->m_options['access_token_secret']] = $response['linkedin']['oauth_token_secret'];
				$wsoOpts[$this->m_options['options']] = "1|1|0";
				
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
				session_unset();
				wp_redirect(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
			}
			else
			{
				wso_log_msg(__('LinkedIn : Access token retrieval failed','wordsocial'));
			}
		}
	}
	
	public function disconnect()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		$response = $this->m_linkedIn->revoke();
		if($response['success'] === TRUE)
		{
			unset($wsoOpts[$this->m_options['access_token']]);
			unset($wsoOpts[$this->m_options['access_token_secret']]);
			unset($wsoOpts[$this->m_options['options']]);
			update_option(WSO_OPTIONS, $wsoOpts);
			wp_redirect(get_bloginfo('wpurl')."/wp-admin/options-general.php?page=wordsocial");
		} 
		else
		{
			wso_log_msg(__("LinkedIn : Error revoking user's token",'wordsocial'));
		}
	}
	
	public function savePage()
	{
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(isset($wsoOpts[$this->m_options['access_token']]))
		{
			$types = wso_get_post_types();
			$idx = self::WSO_OPT_PICTURE;
			$opts[$idx++] = $_POST['wsoli_showpicture'];
			
			foreach($types as $type => $label)
			{
				$opts[$idx++] = $_POST['wsolipublish'][$type];
			}
			$wsoOpts[$this->m_options['options']] = implode('|', $opts);	
			
			update_option(WSO_OPTIONS, $wsoOpts);
		}
	}
	
	public function showPage()
	{	
		$wsoOpts = get_option(WSO_OPTIONS);
		
		if(!isset($wsoOpts[$this->m_options['access_token']]))
		{	
			echo '<p>'. _e('If you want to auto-publish your blog posts/pages on your LinkedIn, you have to connect to your account.', 'wordsocial') .'</p>';
			echo '<p><a href="options-general.php?page=wordsocial&connect=li"><img height="21" width="133" src="'. WSO_IMAGES_FOLDER .'signin-linkedin.png" alt="signin linkedin" /></a></p>';
		}
		else
		{
			$user = $this->authenticate();

			$opts = explode("|", $wsoOpts[$this->m_options['options']]);	
			
?>
<p><?php _e('Hello', 'wordsocial'); ?> <strong><?php echo $user; ?></strong>. <a href="options-general.php?page=wordsocial&disconnect=li">[<?php _e('Disconnect', 'wordsocial'); ?>]</a></p>
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
		echo "\t\t<input type='radio' value='1' name='wsolipublish[{$type}]' id='wsolienable{$i}'". ( ((int)$opts[$i] == 1) ? ' checked="checked"' : '' ) ." /> <label for='wsolienable{$i}'>". __('Yes', 'wordsocial') ."</label> <input type='radio' value='0' name='wsolipublish[{$type}]' id='wsolidisable{$i}'". ( ((int)$opts[$i] == 0) ? ' checked="checked"' : '' ) ." /> <label for='wsolidisable{$i}'>". __('No', 'wordsocial') ."</label>";
		echo "\t</td>";
		echo "</tr>";
		++$i;
	} ?>
		<tr>
			<th><?php _e('Show an image ?', 'wordsocial'); ?></th>
			<td>
				<input type="radio" value="1" name="wsoli_showpicture" id="wsoli_show_picture"<?php echo ( ((int)$opts[self::WSO_OPT_PICTURE] == 1) ? ' checked="checked"' : '' ); ?> /> <label for="wsoli_show_picture"><?php _e('Yes', 'wordsocial'); ?></label> <input type="radio" value="0" name="wsoli_showpicture" id="wsoli_hide_picture"<?php echo ( ((int)$opts[self::WSO_OPT_PICTURE] == 0) ? ' checked="checked"' : '' ); ?> /> <label for="wsoli_hide_picture"><?php _e('No', 'wordsocial'); ?></label>
			</td>
		</tr>
	</tbody>
</table>
<?php
		}
	}
}

wso_add_service("LinkedIn", "li", WSO_LinkedIn::get());