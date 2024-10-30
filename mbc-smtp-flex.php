<?php

/*
  Plugin Name: MBC SMTP Flex
  Version: 0.5
  Plugin URI: http://bistromatics.com/mbc-smtp-flex
  Description: Use SMTP with authentication to deliver messages from WordPress. Intercepts wp_mail function to allow you to define the server, port, connection security and credentials.
  Author: Mike Block
  Author URI: http://bistromatics.com/mbc-smtp-flex
 */

define('MBC_SMTP_FLEX_VERSION', 0.5);

class mbc_smtp_flex {
	protected $plugin_id = 'mbc_smtp_flex';
	public    $ready = false;
	private   $logfile = null;
	private   $from = null;
	protected $opt_key = 'mbc_smtp_flex_options';
	private   $opts = null;
	protected $notices = array();
	private   $opt_settings = array(
		'active' => array(0,'bool',"Active","Enable this plugin to extend and override settings for `wp_mail` function"),
		'smtp_server' => array('','fqdn',"SMTP Server","FQDN for the server (if sending secure, set tls or ssl protocol below)"),
		'smtp_secure' => array('','secure',"SMTP Security","Security Proto for the server, tls, ssl or none if not secure"),
		'smtp_port' => array(25,'number',"SMTP Port","Communication port for server (often 25-non-secure, 465-ssl, or 587-tls)"),
		'smtp_username' => array('','text',"SMTP Username","Account username (leave blank to skip credentials)"),
		'smtp_password' => array('','text',"SMTP Password","Account password (leave blank to skip credentials)"),
		'default_address' => array('','email',"Default Sender","Use this sender for all messages, unless defined."),
		'smtp_use_php_mail' => array(0,'bool',"Use PHP Mail","Skip credentials and socket mail connections, just use basic PHP mail function for testing"),
		'force_sender' => array(0,'bool',"Force Sender","Force all messages to be delivered from the default mail account (beneficial when relaying through most SMTP services - use Reply-To headers to change the return address)"),
		'force_sender_name' => array(0,'text',"Force Sender Name","Force the name associated with all messages (replaces standard of WordPress)"),
		'force_recipient' => array(0,'bool',"Force Recipient","Force all messages to be delivered to the default mail account (as an example, this is necessary while in the while in Amazon SES sandbox)"),
		'use_log' => array(0,'bool',"Use Log","If there are errors, log them to a file located at __logfile__"),
		'debug_mode' => array(0,'bool',"Debugging","Allow verbose messages to be written in the log file during mail delivery to troubleshoor errors"),
		'logfile_suffix' => array('','text',"Log File Suffix","For security, obsfucate the log file by generating a random suffix to it's name"),
		'content_type' => 'UTF-8'
	);
	
	public function __construct() {
		add_action( 'init', array($this, 'init') );
		add_filter( 'wp_mail', array($this, 'filter_wp_mail') );
		add_filter( 'wp_mail_from', array($this, 'filter_wp_mail_from') );
		add_filter( 'wp_mail_from_name', array($this, 'filter_wp_mail_from_name') );
		add_action( 'phpmailer_init', array($this, 'phpmailer_init') );
		if (is_admin()) {
			add_action( 'admin_menu', array($this, 'admin_menu') );
			add_action( 'admin_notices', array($this, 'show_notices') );
			register_activation_hook( __FILE__, array($this, 'install') );
			register_deactivation_hook( __FILE__, array($this, 'uninstall') );
		}
	}
	
	public function filter_wp_mail($args) {
		if ((bool)$this->opts['force_recipient']) {
			$args['to'] = $this->opts['default_address'];
		}
		// from header will get stripped from the array, but we need to trap it and switch it to a reply-to header
		// if the sender is being forced so that replies go where expected
		// if there is a reply-to header set, we use that one instead
		if ((bool)$this->opts['force_sender']) {
			$this->from = null; // reset for this action
			$hdrs = $args['headers'];
			if (!empty($hdrs)) {
				if (!is_array($hdrs)) {
					$hdrs = preg_split('~\r?\n~',$hdrs,-1,PREG_SPLIT_NO_EMPTY);
				}
				$hrdsout = array();
				foreach ($hdrs as $h) {
					list($k,$v) = preg_split('~\s*:\s*~',$h,2,PREG_SPLIT_NO_EMPTY);
					if (strtolower($k) == 'from') {
						$this->from = $v;
						continue;
					} elseif (strtolower($k) == 'reply-to') {
						$replyto = $v;
						continue;
					}
					$hrdsout[] = $h;
				}
			}
			if (!empty($replyto)) {
				$hrdsout[] = sprintf('%s: %s','Reply-To',$replyto);
			} elseif (!empty($this->from)) {
				$hrdsout[] = sprintf('%s: %s','Reply-To',$from);
			}
			$args['headers'] = $hrdsout;
		}
		return $args;
	}
	
	public function filter_wp_mail_from($from) {
		if ((bool)$this->opts['force_sender']) {
			$from = $this->opts['default_address'];
		}
		return $from;
	}
	
	public function filter_wp_mail_from_name($fromname) {
		if ((bool)$this->opts['force_sender'] && !empty($this->from)) {
			$this->from = strtolower(trim(preg_replace('~.*<(.+)>~','$1',$this->from)));
			if ($this->from != $this->opts['default_address']) {
				$fromname = sprintf(__("On behalf of %s", $this->plugin_id),$this->from);
			} elseif (!empty($this->opts['force_sender_name'])) {
				$fromname = $this->opts['force_sender_name'];
			}
		} elseif (!empty($this->opts['force_sender_name'])) {
			$fromname = $this->opts['force_sender_name'];
		}
		return $fromname;
	}
	
	public function phpmailer_init($phpmailer) {
		$phpmailer->Mailer = $this->opts['smtp_use_php_mail'] ? 'mail' : 'smtp'; // use either php mail or smtp 
		//$phpmailer->isSMTP(); // use either php mail or smtp 
		$phpmailer->Host = $this->opts['smtp_server']; // server host
		$phpmailer->Port = $this->opts['smtp_port']; // server port
		$phpmailer->SMTPSecure = $this->opts['smtp_secure']; // tls or ssl or none
		$phpmailer->SMTPAuth = !empty($this->opts['smtp_username']) && !empty($this->opts['smtp_password']); // if there is a username and password in config
		$phpmailer->Username = $this->opts['smtp_username']; // username for connect if needed
		$phpmailer->Password = $this->opts['smtp_password']; // password for connect if needed
		$phpmailer->AuthType = 'LOGIN'; // Options are LOGIN (default), PLAIN, NTLM, CRAM-MD5
		if ($this->opts['debug_mode']) {
			$phpmailer->SMTPDebug = 1;
			$phpmailer->Debugoutput = 'html';
		}
	}
	
	public function do_checks() {
		if (!function_exists('fsockopen')) {
			$this->notices[] = array(
				'id'    => $this->plugin_id . 'fsockopen',
				'title' =>__("Error with fsocketopen", $this->plugin_id),
				'desc'  =>__("fsocketopen is not available for the MBC SMTP Flex plugin to operate. Please ensure that this function is available and has not been filtered out with the php.ini setting: `disable_functions`.", $this->plugin_id),
			);
		}
		if ((bool)$this->opts['active'] == false) {
			$this->notices[] = array(
				'id'    => $this->plugin_id . 'inactive',
				'title' =>__("MBC SMTP Flex in Sandbox", $this->plugin_id),
				'desc'  =>sprintf(
					__("MBC SMTP Flex is running in sandbox mode and is not set to override normal mail delivery functions. Check the config under %s enable the plugin when ready.", $this->plugin_id),
					'<a href="options-general.php?page=mbc_smtp_flex_options">' . __("Settings &rarr; MBC SMTP Flex", $this->plugin_id) . '</a>'
				),
			);
		}

		if ( !(bool)$this->opts['active'] ) { return false; }
		if ( empty($this->opts['smtp_server'])  || (int)$this->opts['smtp_port'] < 1 || (int)$this->opts['smtp_port'] > 65535 ) { 
			$this->opts['active'] = null;
			update_option($this->opt_key,$this->opts);
			return false;
		}
		if (is_admin()) {
			// Open an SMTP connection
			$prefix = $this->opts['smtp_secure'] == '' ? '' : $this->opts['smtp_secure'] . '://';
			$host = $prefix . $this->opts['smtp_server'];
			$cp = fsockopen($host, $this->opts['smtp_port'], $errno, $errstr, 1);
			if ($errno !== 0) {
				$this->notices[] = array(
					'id'    => $this->plugin_id . 'connecterror',
					'title' =>__("Error connecting to server", $this->plugin_id),
					'desc'  =>sprintf(__("MBC SMTP Flex is unable to connect to the server at <code>%s</code>. The following error was received: <code>%s</code>. Verify the server connection and try again.", $this->plugin_id),$host,$errstr),
				);
				//$this->opts['active'] = null;
				update_option($this->opt_key,$this->opts);
				return false;
			}
		}
		return true;
	}
	
	public function check_test_message() {
		if (is_admin() && !empty($_POST['test_message'])) {
			$debug = $this->opts['debug_mode'];
			$logs = $this->opts['use_log'];
			$this->opts['use_log'] = true;
			$this->opts['debug_mode'] = true;
			try {
				ob_start();
				$res = wp_mail(
					$_POST['test_message_email'],
					__("MBC SMTP Flex Test Message", $this->plugin_id),
					sprintf(__("This is a test message issued to you on %s", $this->plugin_id),date('r')),
					array('from'=>$_POST['test_message_email'],'reply-to'=>$_POST['test_message_email'])
				);
				$debugging = ob_get_contents(); ob_end_clean();
				if ($res == 1) {
					$this->notices[] = array(
						'id'    => $this->plugin_id . 'testokay',
						'title' =>__("Test Looks Good", $this->plugin_id),
						'desc'  =>sprintf(__("MBC SMTP Flex send an email to %s. Please confirm delivery of that email.", $this->plugin_id),$_POST['test_message_email']),
					);
					return;
				} else {
					$error = __("Message failed. Error information below", $this->plugin_id).'<br>'.$debugging;
				}
			} catch (Exception $e) {
				$error = print_r($e,true);
			}
			$this->notices[] = array(
				'id'    => $this->plugin_id . 'testfailed',
				'title' =>__("Test Failed", $this->plugin_id),
				'desc'  =>sprintf(__("MBC SMTP Flex send an email to %, but it failed.", $this->plugin_id),$_POST['test_message_email']).'<br>'.$debugging,
			);
			$this->opts['debug_mode'] = $debug;
			$this->opts['use_log'] = $logs;
		}

	}

	public function install() {
		if (!get_option($this->opt_key)) {
			$current_user = wp_get_current_user();
			$settings = array();
			foreach ($this->opt_settings as $k=>$info) {
				$settings[$k] = $info[0]; // first element contains the default setting
			}
			$settings['default_address'] = $current_user->user_email;
			$settings['logfile_suffix'] = uniqid();
			add_option($this->opt_key,$settings);
			$this->opts = get_option($this->opt_key);
		}
	}

	public function init() {
		load_plugin_textdomain($this->plugin_id, false, basename(dirname(__FILE__)));
		$this->opts = get_option($this->opt_key);
		if (is_admin()) {
			if (isset($_POST[$this->opt_key])) {
				$v = $_POST[$this->opt_key];
				foreach ($this->opt_settings as $k=>$info) {
					switch ($info[1]) {
						case 'bool' :
							$v[$k] = (bool)$v[$k];
							break;
						case 'email' :
							$v[$k] = filter_var($v[$k],FILTER_VALIDATE_EMAIL);
							break;
						case 'number' :
							$v[$k] = (int)$v[$k];
							break;
						case 'secure' :
						case 'url' :
						case 'fqdn' :
						case 'text' :
							$v[$k] = $v[$k];
							break;
					}
				}
				update_option($this->opt_key, $v);
				$this->opts = get_option($this->opt_key);
			}
		}
		
		$this->logfile = __DIR__ . '/mail-'.$this->opts['logfile_suffix'].'.log';
		if ($this->do_checks()) {
			$this->ready = true;
		}
		$this->check_test_message();

	}

	public function uninstall() {
		delete_option($this->opt_key);
	}

	public function admin_menu() {
		add_options_page(
			__("MBC SMTP Flex Settings", $this->plugin_id),
			__("MBC SMTP Flex", $this->plugin_id),
			'manage_options',
			$this->opt_key,
			array($this, 'options_page')
		);
	}
	
	public function options_page() {
		$this->opts = get_option($this->opt_key);
		// Set class property
		$fields = array();
		foreach ($this->opt_settings as $k=>$info) {
			if (!is_array($info)) { continue; }
			$field = '';
			switch ($info[1]) {
				case 'bool' :
					$field = sprintf(
						'<input type="checkbox" name="%s[%s]" id="%s" value="1"%s />',
						$this->opt_key, 
						$k, 
						$k, 
						(bool)$this->opts[$k] ? ' checked' : ''
					);
					break;
				case 'secure' :
					$field = sprintf(
						'<select name="%s[%s]" id="%s">
									<option value=""%s>None</option>
									<option value="tls"%s>TLS</option>
									<option value="ssl"%s>SSL</option>
								</select>',
						$this->opt_key, 
						$k, 
						$k, 
						$this->opts[$k] == '' ? ' selected' : '', 
						$this->opts[$k] == 'tls' ? ' selected' : '', 
						$this->opts[$k] == 'ssl' ? ' selected' : ''
					);
					break;
				case 'url' :
				case 'fqdn' :
				case 'email' :
				case 'number' :
				case 'text' :
					$field = sprintf(
						'<input type="%s" class="regular-text%s" name="%s[%s]" id="%s" value="%s" />',
						$info[1] == 'fqdn' ? 'text' : $info[1], 
						$info[1] == 'fqdn' ? ' code' : '', 
						$this->opt_key, 
						$k, 
						$k, 
						esc_attr($this->opts[$k])
					);
					break;
			}
			$fields[] = sprintf(
				'<tr>
					<th scope="row"><label for="%s">%s</label></th>
					<td>
						%s
						<p class="description" id="%s-description">%s</p>
					</td>
				</tr>',
				$k, 
				__($info[2], $this->plugin_id),
				$field,
				$k, 
				str_replace('__logfile__', $this->logfile, __($info[3], $this->plugin_id))
			);
		}
		$current_user = wp_get_current_user();
		?>
		<div class="wrap">
			<h2><?php _e("MBC SMTP Flex Settings") ?></h2>
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<table class="form-table">
				<tbody>
				<?php echo join("\n",$fields); ?>
				<tr>
					<th scope="row"><label for="test_message"><?php _e("Send Test Message", $this->plugin_id) ?></label></th>
					<td>
						<input type="checkbox" name="test_message" id="test_message" value="1" />
						<input type="text" name="test_message_email" class="regular-text" id="test_message_email" value="<?php echo esc_attr($current_user->user_email) ?>" />
						<p class="description" id="test_message-description"><?php _e("Send a message with deliver details", $this->plugin_id) ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>

			</form>
			</div>
			<?php
	}

	function show_notices() {
		foreach ($this->notices as $n) {
			printf(
				'<div id="%s" class="updated fade notice is-dismissible"><p><strong>%s</strong><br>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>',
				$n['id'], $n['title'], $n['desc'], __("Dismiss this notice.")
			);
		}
	}


}

$MBC_SMTP_Flex = new mbc_smtp_flex();
