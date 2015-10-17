<?php
/**
 * Plugin Name: MM Twitter Connection
 * Plugin URI: http://marsminds.com/
 * Description: Making simple the login and connection process with Twitter.
 * Version: 1
 * Author: Rodrigo Techera
 * Author URI: http://rodmind.com/
 * License: GPL2
 */

add_action('init', 'mm_twitter_connection_process_twitter_actions');
function mm_twitter_connection_process_twitter_actions() {
	global $user_ID;
	$current_url = mm_twitter_connection_get_current_url();
	
	if(mm_twitter_connection_is_conifgured()) {
		if(isset($_GET['mm_unlink_twitter']) && $_GET['mm_unlink_twitter']=='true') {
			update_user_meta($user_ID, 'mm_twitter_connection_twitter_id', '');

			return wp_redirect($current_url);
		}

		if(isset($_GET['mm_twitter_authorize']) && $_GET['mm_twitter_authorize']=='true') {
			require_once('sdk/tmhOAuth.php');
			require_once('sdk/tmhUtilities.php');

			$client_id = get_option('mm_twitter_connection_client_id');
			$client_secret = get_option('mm_twitter_connection_client_secret');

			$tmhOAuth = new tmhOAuth(array(
				'consumer_key' => $client_id,
				'consumer_secret' => $client_secret,
			));

			$params = array('oauth_callback' => $current_url.'?urlstatus='.$_GET['state']);
			
			$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/request_token', '') , $params);
			if($code==200) {
				$oauth = $tmhOAuth->extract_params($tmhOAuth->response['response']);

				if(session_status()==PHP_SESSION_NONE)
					session_start();
				$_SESSION['mm_twitter_connect_oauth'] = $oauth;
				$authurl = $tmhOAuth->url('oauth/authenticate', '').'?oauth_token='.$oauth['oauth_token'];
				return header('Location: '.$authurl);
			}
		}
		
		if(isset($_GET['oauth_token']) && $_GET['oauth_token'] && isset($_GET['oauth_verifier']) && $_GET['oauth_verifier'] && isset($_GET['urlstatus']) && $_GET['urlstatus']) {
			$state_param = json_decode(base64_decode(strtr($_GET['urlstatus'], '-_,', '+/=')), ARRAY_A);

			if(is_array($state_param) && count($state_param)>0) {
				$current_url = $state_param['current_url'];
				if(isset($state_param['mm_twitter_connection']) && $state_param['mm_twitter_connection']=='true') {
					$data_array = mm_twitter_connection_get_data($_GET['oauth_verifier'], $current_url);
					if(is_array($data_array) && count($data_array)>0) {
						update_user_meta($user_ID, 'mm_twitter_connection_twitter_id', $data_array['twitter_internal_id']);

						return wp_redirect($current_url);
					}
				} elseif(isset($state_param['mm_twitter_login']) && $state_param['mm_twitter_login']=='true') {
					$data_array = mm_twitter_connection_get_data($_GET['oauth_verifier'], $current_url);
					if(is_array($data_array) && count($data_array)>0) {
						$users_array = get_users(array('meta_key'=>'mm_twitter_connection_twitter_id', 'meta_value'=>$data_array['twitter_internal_id']));
				
						if(is_array($users_array) && count($users_array)>0) {
							$user_to_auth_obj = $users_array[0];

							if($user_to_auth_obj) {
								wp_set_current_user($user_to_auth_obj->ID, $user_to_auth_obj->user_login);
								wp_set_auth_cookie($user_to_auth_obj->ID);
								do_action('wp_login', $user_to_auth_obj->user_login);

								return wp_redirect(home_url('/'));
							}
						}
					}
				}
			}
		}
	}
}

add_action('admin_init', 'mm_twitter_connection_register_setting_fields');
function mm_twitter_connection_register_setting_fields() {
	add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'mm_twitter_connection_plugins_bar_links');

	add_settings_section('mm_connection_settings_section', '<a id="mm_connection_settings"></a>'.__('MM Connection Global Settings', 'marsminds'), 'mm_twitter_connection_global_settings_section', 'general');
	register_setting('general', 'mm_connection_global_css', 'esc_attr');
	add_settings_field('mm_connection_global_css', '<label for="mm_connection_global_css">'.__('MM Connection Global CSS', 'marsminds').'</label>' , 'mm_twitter_connection_print_global_css_field', 'mm_connection_settings_section');

	add_settings_section('mm_twitter_connection_settings_section', '<a id="mm_twitter_connection_settings"></a>'.__('MM Twitter Connection Settings', 'marsminds'), 'mm_twitter_connection_settings_section', 'general');
	register_setting('general', 'mm_twitter_connection_client_id', 'esc_attr');
	register_setting('general', 'mm_twitter_connection_client_secret', 'esc_attr');
	add_settings_field('mm_twitter_connection_client_id', '<label for="mm_twitter_connection_client_id">'.__('MM Twitter Client ID', 'marsminds').'</label>' , 'mm_twitter_connection_print_client_id_field', 'mm_twitter_connection_settings_section');
	add_settings_field('mm_twitter_connection_client_secret', '<label for="mm_twitter_connection_client_secret">'.__('MM Twitter Client Secret', 'marsminds').'</label>' , 'mm_twitter_connection_print_client_secret_field', 'mm_twitter_connection_settings_section');
}

function mm_twitter_connection_plugins_bar_links($links) {
	return array_merge(
		array(	'<a href="'.admin_url('options-general.php#mm_connection_settings').'">'.__('CSS Settings', 'marsminds').'</a>',
				'<a href="'.admin_url('options-general.php#mm_twitter_connection_settings').'">'.__('Twitter Connection Settings', 'marsminds').'</a>'),
		$links);
}

function mm_twitter_connection_print_global_css_field() {
	$global_css_default_value = '<link href="https://fonts.twitterapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css">'."\n".
								'<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">'."\n".
								'<style type="text/css">'."\n".
								'	a.mm-sl-btn{'."\n".
								'		background-color:#fa5b49;'."\n".
								'		color:#fff;'."\n".
								'		display:block;'."\n".
								'		font:14px/20px "Montserrat",helvetica;'."\n".
								'		margin:0 auto 10px;'."\n".
								'		max-width:180px;'."\n".
								'		padding:10px 18px 9px;'."\n".
								'		text-decoration:none;'."\n".
								'		transition:opacity 0.4s ease,padding 0.2s ease,box-shadow 0.2s ease;'."\n".
								'	}'."\n".
								'	a.mm-sl-btn:hover{'."\n".
								'		opacity:0.9;'."\n".
								'	}'."\n".
								'	a.mm-sl-btn:active{'."\n".
								'		padding:12px 18px 7px;'."\n".
								'		box-shadow:inset 0 2px 0 rgba(0,0,0,0.3);'."\n".
								'	}'."\n".
								'	a.mm-sl-btn i{'."\n".
								'		margin-right:8px;'."\n".
								'	}'."\n".
								'	a.mm-sl-fb{'."\n".
								'		background-color:#3b5998;'."\n".
								'	}'."\n".
								'	a.mm-sl-tw{'."\n".
								'		background-color:#4099FF;'."\n".
								'	}'."\n".
								'	a.mm-sl-go{'."\n".
								'		background-color:#d34836;'."\n".
								'	}'."\n".
								'	a.mm-sl-li{'."\n".
								'		background-color:#007bb5;'."\n".
								'	}'."\n".
								'	a.mm-sl-in{'."\n".
								'		background-color:#125688;'."\n".
								'	}'."\n".
								'	a.mm-sl-pi{'."\n".
								'		background-color:#cb2027;'."\n".
								'	}'."\n".
								'	a.mm-sl-gi{'."\n".
								'		background-color:#333;'."\n".
								'	}'."\n".
								'</style>';
	$global_css_value = get_option('mm_connection_global_css', $global_css_default_value);
	echo '<textarea name="mm_connection_global_css" style="width:80%;height:250px;">'.$global_css_value.'</textarea>';
}

function mm_twitter_connection_print_client_id_field() {
	$client_id_value = get_option('mm_twitter_connection_client_id');
	echo '<input type="text" name="mm_twitter_connection_client_id" class="regular-text" value="'.$client_id_value.'">';
}

function mm_twitter_connection_print_client_secret_field() {
	$client_secret_value = get_option('mm_twitter_connection_client_secret');
	echo '<input type="text" name="mm_twitter_connection_client_secret" class="regular-text" value="'.$client_secret_value.'">';
}

function mm_twitter_connection_global_settings_section($args) {
	?>
	<table class="form-table">
		<tbody>
			<?php do_settings_fields('mm_connection_settings_section', 'default');?>
		</tbody>
	</table>
	<?php
}

function mm_twitter_connection_settings_section($args) {
	?>
	<table class="form-table">
		<tbody>
			<tr>
				<th colspan="2">
					<label>Procedure:</label>
					<p class="description">1 - Create a Twitter Application here <a href="https://apps.twitter.com/" target="_blank">https://apps.twitter.com/</a>.</p>
					<p class="description">2 - Callback URL needs to be this: <?php echo mm_twitter_connection_get_current_domain();?></p>
					<p class="description">3 - Take the Client ID and Client Secret from your recent created App, and complete the fields below.</p>
				</td>
			</tr>
			<?php do_settings_fields('mm_twitter_connection_settings_section', 'default');?>
		</tbody>
	</table>
	<?php
}

function mm_twitter_connection_get_current_domain() {
	$server_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!='off' || $_SERVER['SERVER_PORT']==443)?'https://':'http://';
	return $server_protocol.$_SERVER["HTTP_HOST"];
}

function mm_twitter_connection_get_current_url() {
	return mm_twitter_connection_get_current_domain().$_SERVER["PHP_SELF"];
}

add_action('admin_head','mm_twitter_connection_hook_css');
add_action('login_head', 'mm_twitter_connection_hook_css');
add_action('wp_head','mm_twitter_connection_hook_css');
function mm_twitter_connection_hook_css() {
	global $mm_connection_global_css_value;

	if(!isset($mm_connection_global_css_value)) {
		$mm_connection_global_css_value = get_option('mm_connection_global_css');
		echo html_entity_decode($mm_connection_global_css_value);
	}
}

function mm_twitter_connection_is_conifgured() {
	$client_id = get_option('mm_twitter_connection_client_id');
	$client_secret = get_option('mm_twitter_connection_client_secret');

	if($client_id && $client_secret)
		return true;
	else
		return false;
}

function mm_twitter_connection_is_linked($user_id) {
	$twitter_internal_id = get_user_meta($user_id, 'mm_twitter_connection_twitter_id', true);
	
	if($twitter_internal_id)
		return true;
	else
		return false;
}

function mm_twitter_connection_get_authorize_url($login=false) {
	$client_id = get_option('mm_twitter_connection_client_id');
	$site_url = urlencode(mm_twitter_connection_get_current_domain());
	$current_url = mm_twitter_connection_get_current_url();

	$state = array();
	$state['current_url'] = $current_url;
	if($login)
		$state['mm_twitter_login'] = 'true';
	else
		$state['mm_twitter_connection'] = 'true';

	$state = strtr(base64_encode(json_encode($state)), '+/=', '-_,');

	return $current_url.'?mm_twitter_authorize=true&state='.$state;
	//return 'https://twitter.com/oauth/authorize?client_id='.$client_id.'&state='.$state.'&redirect_uri='.$redirect_url;
}

add_action('profile_personal_options', 'mm_twitter_connection_profile_personal_options');
function mm_twitter_connection_profile_personal_options() {
	global $user_ID;

	$current_url = mm_twitter_connection_get_current_url();
	?>
	<table class="form-table">
		<tbody>
			<tr>
				<th>
					<label><?php echo __('MM twitter Connect', 'marsminds');?>:</label>
				</th>
				<td>
					<?php if(mm_twitter_connection_is_conifgured()) { ?>
						<?php if(mm_twitter_connection_is_linked($user_ID)) { ?>
							<a class="mm-sl-btn mm-sl-tw" href="<?php echo $current_url.'?mm_unlink_twitter=true';?>"><i class="fa fa-twitter"></i><?php echo __('Unlink Account', 'marsminds');?></a>
						<?php } else { ?>
							<a class="mm-sl-btn mm-sl-tw" href="<?php echo mm_twitter_connection_get_authorize_url();?>"><i class="fa fa-twitter"></i><?php echo __('Link Account', 'marsminds');?></a>
						<?php } ?>
					<?php } elseif(current_user_can('manage_options')) { ?>
						<a href="<?php echo admin_url('options-general.php#mm_twitter_connection_settings');?>"><?php echo __('Configure it first!', 'marsminds');?></a>
					<?php } ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

add_action('login_form', 'mm_twitter_connection_login_form');
function mm_twitter_connection_login_form() {
	if(mm_twitter_connection_is_conifgured()) {
		echo '<a class="mm-sl-btn mm-sl-tw" href="'.mm_twitter_connection_get_authorize_url(true).'"><i class="fa fa-twitter"></i>Login with twitter</a>';
	}
}

function mm_twitter_connection_get_data($twitter_verifier, $current_url) {
	if(session_status()==PHP_SESSION_NONE)
		session_start();
	
	require_once('sdk/tmhOAuth.php');
	require_once('sdk/tmhUtilities.php');

	$client_id = get_option('mm_twitter_connection_client_id');
	$client_secret = get_option('mm_twitter_connection_client_secret');

	$tmhOAuth = new tmhOAuth(array(
		'consumer_key' => $client_id,
		'consumer_secret' => $client_secret,
	));

	$tmhOAuth->config['user_token'] = $_SESSION['mm_twitter_connect_oauth']['oauth_token'];
	$tmhOAuth->config['user_secret'] = $_SESSION['mm_twitter_connect_oauth']['oauth_token_secret'];

	$params = array('oauth_verifier' => $twitter_verifier);
	$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', '') , $params);

	if($code==200) {
		$access_token = $tmhOAuth->extract_params($tmhOAuth->response['response']);

		$tmhOAuth->config['user_token'] = $access_token['oauth_token'];
		$tmhOAuth->config['user_secret'] = $access_token['oauth_token_secret'];
		$code = $tmhOAuth->request('GET', $tmhOAuth->url('1.1/account/verify_credentials'));
		if($code==200) {
			$result = json_decode($tmhOAuth->response['response'], ARRAY_A);

			if(is_array($result) && count($result)>0) {
				$user_data['twitter_internal_id'] = $result['id'];
				$user_data['name'] = $result['name'];
				$user_data['picture'] = $result['profile_image_url'];
				
				return $user_data;
			}
		}
	}

	return false;
}
?>