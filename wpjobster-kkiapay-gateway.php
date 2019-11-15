<?php

/**
 * Plugin Name: WPJobster Kkiapay Gateway
 * Plugin URI: http://wpjobster.com/
 * Description: This plugin extends Jobster Theme to accept payments with kkiapay.
 * Author: Kkipay Developer Team ❤️
 * Author URI: https://app.kkiapay.me/
 * Version: 0.1
 *
 * Copyright (c) 2016 WPJobster
 *
 */


if (!defined('ABSPATH')) {
	exit;
}


/**
 * Required minimums
 */
define('WPJOBSTER_KKIAPAY_MIN_PHP_VER', '5.4.0');


class WPJobster_Sample_Loader
{

	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	private static $instance;
	public $priority, $unique_slug;


	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();


	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	protected function __construct()
	{
		$this->priority = 100;           // 100, 200, 300 [...] are reserved
		$this->unique_slug = 'kkiapay';    // this needs to be unique
		$key = get_option('wpjobster_kkiapay_public');
		$callback = get_bloginfo('url') . '/?payment_response=kkiapay';
		$wpjobster_kkiapay_enablesandbox = get_option('wpjobster_kkiapay_enablesandbox');
		$theme = get_option('wpjobster_kkiapay_theme');
		$text = get_option('wpjobster_kkiapay_button_caption');
		if ($wpjobster_kkiapay_enablesandbox == 'no') {
			$sandbox = 'false';
		} else {
			$sandbox = 'true';
		}


		add_shortcode('kkiapay', 'kkiapay_shortcode');

		wp_register_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/invoke.js', array(), $this->version, false);
		wp_enqueue_script($this->plugin_name);


		$add_something_nonce = wp_create_nonce("add_something");
		$user_id = get_current_user_id();



		if (is_admin() === false) {
			echo "<script>	
			function addScript() {
					var s = document.createElement( 'script' );
					s.setAttribute( 'src', 'https://cdn.kkiapay.me/' );
					document.head.appendChild(s);
				}

			function registerScript(){
				if(typeof window.kkiapayisregistred == 'undefined'){
					window.kkiapayisregistred = true
					if(document.getElementById('kkiapay') != null){
									addScript()
									
					document.getElementById('kkiapay').removeAttribute('onclick')
						document.getElementById('kkiapay').addEventListener('click',(e)=>{
						e.preventDefault()
							addSuccessListener(response => {
								let input = document.createElement('input')
								let form = document.createElement('form')
								input.setAttribute('name', 'transactionId')
								input.setAttribute('value', response.transactionId)
								form.appendChild(input)
								console.log(response);
								take_to_gateway('kkiapay')
							});
						openKkiapayWidget({ amount: document.querySelector('.total').getAttribute('data-total'),key:'$key',sandbox:'$sandbox',theme:'$theme',text:'$text' })
						console.log('=====>')
						})
					}
				}
			}
			window.addEventListener('load',()=>{
				registerScript();
			})
			</script>";
		}


		add_action('admin_init',       array($this, 'check_environment'));
		add_action('admin_notices',    array($this, 'admin_notices'), 15);
		add_action('plugins_loaded',   array($this, 'init_gateways'), 0);
		add_filter(
			'plugin_action_links_' . plugin_basename(__FILE__),
			array($this, 'plugin_action_links')
		);

		add_action(
			'wpjobster_taketo_' . $this->unique_slug . '_gateway',
			array($this, 'taketogateway_function'),
			10,
			2
		);
		add_action(
			'wpjobster_processafter_' . $this->unique_slug . '_gateway',
			array($this, 'processgateway_function'),
			10,
			2
		);
		add_filter(
			'wpj_payment_response_accepted_params',
			array($this, 'add_gateway_param_accepted_uri_params')
		);

		// use this filter if your gateway works with a specific currency only
		add_filter(
			'wpjobster_take_allowed_currency_' . $this->unique_slug,
			array($this, 'get_gateway_currency')
		);

		if (isset($_POST['wpjobster_save_' . $this->unique_slug])) {
			add_action('wpjobster_payment_methods_action', array($this, 'save_gateway'), 11);
		}
	}

	/*
	 * Define the gateways default currency if any
	 */
	function get_gateway_currency($currency)
	{
		// if the gateway requires a specific currency you can declare it there
		// currency conversions are done automatically
		$currency = '	XOF'; // delete this line if the gateway works with any currency
		return $currency;
	}


	function kkiapay_shortcode()
	{

		$order_id = $_GET['order_id'];
		

		return  "<script>	
			function addScript() {
					var s = document.createElement( 'script' );
					s.setAttribute( 'src', 'https://cdn.kkiapay.me/' );
					document.head.appendChild(s);
				}

			function registerScript(){
				if(typeof window.kkiapayisregistred == 'undefined'){
					window.kkiapayisregistred = true
					if(document.getElementById('kkiapay') != null){
									addScript()
									
					document.getElementById('kkiapay').removeAttribute('onclick')
						document.getElementById('kkiapay').addEventListener('click',(e)=>{
						e.preventDefault()
							addSuccessListener(response => {
								let input = document.createElement('input')
								let form = document.createElement('form')
								input.setAttribute('name', 'transactionId')
								input.setAttribute('value', response.transactionId)
								form.appendChild(input)
								console.log(response);
								take_to_gateway('kkiapay')
							});
						openKkiapayWidget({ amount: document.querySelector('.total').getAttribute('data-total'),key:'$key',sandbox:'$sandbox',theme:'$theme',text:'$text' })
						console.log('=====>')
						})
					}
				}
			}
			window.addEventListener('load',()=>{
				registerScript();
			})
			</script>";
	}

	/**
	 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
	 *
	 * @since 1.0.0
	 */
	public function init_gateways()
	{
		load_plugin_textdomain('wpjobster-kkiapay', false, trailingslashit(dirname(plugin_basename(__FILE__))));
		add_filter('wpjobster_payment_gateways', array($this, 'add_gateways'));
	}


	/**
	 * Add the gateways to WPJobster
	 *
	 * 'action' is called when user resuest to send payment to gateway
	 * 'response_action' is called when any response comes from gateway after payment
	 *
	 * @since 1.0.0
	 */
	public function add_gateways($methods)
	{

		$methods[$this->priority] =
			array(
				'label'           => __('Kkiapay', 'wpjobster-kkiapay'),
				'unique_id'       => $this->unique_slug,
				'action'          => 'wpjobster_taketo_' . $this->unique_slug . '_gateway',
				'response_action' => 'wpjobster_processafter_' . $this->unique_slug . '_gateway',
			);
		add_action('wpjobster_show_paymentgateway_forms', array($this, 'show_gateways'), $this->priority, 3);

		return $methods;
	}


	/**
	 * Save the gateway settings in admin
	 *
	 * @since 1.0.0
	 */
	public function save_gateway()
	{

		if (isset($_POST['wpjobster_save_' . $this->unique_slug])) {

			// _enable and _button_caption are mandatory
			update_option(
				'wpjobster_' . $this->unique_slug . '_enable',
				trim($_POST['wpjobster_' . $this->unique_slug . '_enable'])
			);
			update_option(
				'wpjobster_' . $this->unique_slug . '_button_caption',
				trim($_POST['wpjobster_' . $this->unique_slug . '_button_caption'])
			);

			global $payment_type_enable_arr;
			foreach ($payment_type_enable_arr as $payment_type_enable_key => $payment_type_enable) {
				if ($payment_type_enable_key != 'job_purchase') {
					if (isset($_POST['wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key]))
						update_option('wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key, trim($_POST['wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key]));
				}
			}

			// you can add here any other information that you need from the user
			update_option('wpjobster_kkiapay_enablesandbox', trim($_POST['wpjobster_kkiapay_enablesandbox']));
			update_option('wpjobster_kkiapay_id',            trim($_POST['wpjobster_kkiapay_id']));
			update_option('wpjobster_kkiapay_public',           trim($_POST['wpjobster_kkiapay_public']));
			update_option('wpjobster_kkiapay_private',           trim($_POST['wpjobster_kkiapay_private']));
			update_option('wpjobster_kkiapay_secret',           trim($_POST['wpjobster_kkiapay_secret']));
			update_option('wpjobster_kkiapay_theme',           trim($_POST['wpjobster_kkiapay_theme']));

			update_option('wpjobster_kkiapay_success_page',  trim($_POST['wpjobster_kkiapay_success_page']));
			update_option('wpjobster_kkiapay_failure_page',  trim($_POST['wpjobster_kkiapay_failure_page']));

			echo '<div class="updated fade"><p>' . __('Settings saved!', 'wpjobster-sample') . '</p></div>';
		}
	}


	/**
	 * Display the gateway settings in admin
	 *
	 * @since 1.0.0
	 */
	public function show_gateways($wpjobster_payment_gateways, $arr, $arr_pages)
	{

		$tab_id = get_tab_id($wpjobster_payment_gateways);
		?>
		<div id="tabs<?php echo $tab_id ?>">
			<form method="post" action="<?php bloginfo('url'); ?>/wp-admin/admin.php?page=payment-methods&active_tab=tabs<?php echo $tab_id; ?>">
				<table width="100%" class="wpj-admin-table">
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(); ?></td>
						<td valign="top"><?php _e('Kkiapay Gateway', 'wpjobster-kkiapay'); ?></td>
					</tr>

					<tr>
						<?php // _enable and _button_caption are mandatory 
								?>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Enable/Disable Sample payment gateway', 'wpjobster-kkiapay')); ?></td>
						<td width="200"><?php _e('Activer:', 'wpjobster-kkiapay'); ?></td>
						<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_' . $this->unique_slug . '_enable', 'no'); ?></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Enable/Disable Sample test mode.', 'wpjobster-kkiapay')); ?></td>
						<td width="200"><?php _e('Mode Test:', 'wpjobster-kkiapay'); ?></td>
						<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_' . $this->unique_slug . '_enablesandbox', 'no'); ?></td>
					</tr>




					<tr>
						<?php // _enable and _button_caption are mandatory 
								?>
						<!-- <td valign=top width="22"><?php wpjobster_theme_bullet(__('Put the Sample button caption you want user to see on purchase page', 'wpjobster-sample')); ?></td> -->
						<!-- <td></td> -->
						<!-- <td><input type="text" size="45" name="wpjobster_<?php echo $this->unique_slug; ?>_button_caption" value="<?php echo get_option('wpjobster_' . $this->unique_slug . '_button_caption'); ?>" /></td> -->
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Your Sample Merchant ID', 'wpjobster-sample')); ?></td>
						<td><?php _e('Clé publique:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_kkiapay_public" value="<?php echo get_option('wpjobster_kkiapay_public'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Your Sample Key', 'wpjobster-sample')); ?></td>
						<td><?php _e('Clé privé:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_kkiapay_private" value="<?php echo get_option('wpjobster_kkiapay_private'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Your Sample Key', 'wpjobster-sample')); ?></td>
						<td><?php _e('Clé secrete:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_kkiapay_secret" value="<?php echo get_option('wpjobster_kkiapay_secret'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Your Sample Key', 'wpjobster-sample')); ?></td>
						<td><?php _e('Couleur du widget:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_kkiapay_theme" value="<?php echo get_option('wpjobster_kkiapay_theme'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Please select a page to show when Sample payment successful. If empty, it redirects to the transaction page', 'wpjobster-sample')); ?></td>
						<td><?php _e('Page de Redirection en cas de success:', 'wpjobster-kkiapay'); ?></td>
						<td><?php
									echo wpjobster_get_option_drop_down($arr_pages, 'wpjobster_' . $this->unique_slug . '_success_page', '', ' class="select2" '); ?>
						</td>
					</tr>
					<tr>
						<td></td>
						<td></td>
						<td><input type="submit" name="wpjobster_save_<?php echo $this->unique_slug; ?>" value="<?php _e('Save Options', 'wpjobster-kkiapay'); ?>" /></td>
					</tr>
				</table>
			</form>
		</div>
	<?php
		}


		/**
		 * This function is not required, but it helps making the code a bit cleaner.
		 *
		 * @since 1.0.0
		 */
		public function get_gateway_credentials()
		{

			$merchant_key = get_option('wpjobster_sample_id');
			$key = get_option('wpjobster_sample_key');

			$credentials = array(
				'key'                => $key,
				'merchant_key'       => $merchant_key,
				// 'sample_payment_url' => $sample_payment_url,
			);
			return $credentials;
		}


		/**
		 * Collect all the info that we need and forward to the gateway
		 *
		 * @since 1.0.0
		 */
		public function taketogateway_function($payment_type, $common_details)
		{

			$credentials = $this->get_gateway_credentials();
			// $this->processgateway_function('kkiapay','payment');

			$all_data                       = array();
			$all_data['merchant_key']       = $credentials['merchant_key'];
			$all_data['key']                = $credentials['key'];


			$uid                            = $common_details['uid'];
			$wpjobster_final_payable_amount = $common_details['wpjobster_final_payable_amount'];
			$currency                       = $common_details['currency'];
			$order_id                       = $common_details['order_id'];




			do_action(
				"wpjobster_" . $payment_type . "_payment_success",
				$order_id,
				$this->unique_slug,
				'Completed',
				'Completed'
			);

			// user() is a helper function which calls the appropriate function
			// between get_userdata() and get_user_meta() depending on what info is needed

			$all_data['amount']       = $wpjobster_final_payable_amount;
			$all_data['currency']     = $currency;
			$all_data['success_url']  = get_bloginfo('url') . '/?payment_response=sample&payment_type=' . $payment_type;
			$all_data['fail_url']     = get_bloginfo('url') . '/?payment_response=sample&action=fail&payment_type=' . $payment_type;

			// any other info that the gateway needs
			$all_data['firstname']    = user($uid, 'first_name');
			$all_data['email']        = user($uid, 'user_email');
			$all_data['phone']        = user($uid, 'cell_number');
			$all_data['lastname']     = user($uid, 'last_name');
			$all_data['address']      = user($uid, 'address');
			$all_data['city']         = user($uid, 'city');
			$all_data['country']      = user($uid, 'country_name');
			$all_data['zipcode']      = user($uid, 'zip');
			$all_data['order_id']     = $order_id;

			$loading_text = __('Loading...', 'wpjobster-sample');
			?>

		<!-- <script>
	document.getElementById('sample').removeAttribute('onclick')
    document.getElementById('sample').addEventListener('click',(e)=>{
      e.preventDefault()
      openKkiapayWidget({ amount: '1000',key:'f1e7270098f811e99eae1f0cfc677927',sandbox:true })
      console.log('element bien clické')
    })
				
				openKkiapayWidget({ amount: '1000',key:'f1e7270098f811e99eae1f0cfc677927',sandbox:true })
				
		</script> -->
		<!-- <html>
		<head></head>
		<body onload="document.getElementById('sampleform').submit();" style="">
			<div id="loader" style="display: block; position:relative; width:100%; height:100%;">
				<img style="position:absolute; left:50%; top:50%; margin-left:-50px; margin-top:-50px;" src="<?php echo get_template_directory_uri(); ?>/assets/images/ajax-loader.gif" alt="<?php echo $loading_text; ?>" />
			</div>

			<form action="<?php echo $all_data['sample_payment_url']; ?>" method="post" name="sampleform" id="sampleform" style="display: none;">
				<input type="hidden" name="key" value="<?php echo $all_data['merchant_key']; ?>" />
				<input type="hidden" name="order_id" value="<?php echo $all_data['order_id']; ?>" />
				<input type="hidden" name="amount" value="<?php echo $all_data['amount']; ?>" />
				<input type="hidden" name="currency" value="<?php echo $all_data['currency']; ?>" />

				<input type="hidden" name="success_url" value="<?php echo $all_data['success_url']; ?>" />
				<input type="hidden" name="fail_url" value="<?php echo $all_data['fail_url']; ?>" />

				<?php // any other info that the gateway needs 
						?>
				<input type="hidden" name="firstname" value="<?php echo $all_data['firstname']; ?>" />
				<input type="hidden" name="email" value="<?php echo $all_data['email']; ?>" />
				<input type="hidden" name="phone" value="<?php echo $all_data['phone']; ?>" />
				<input type="hidden" name="address" value="<?php echo $all_data['address']; ?>" />
				<input type="hidden" name="city" value="<?php echo $all_data['city']; ?>" />
				<input type="hidden" name="country" value="<?php echo $all_data['country']; ?>" />
				<input type="hidden" name="zipcode" value="<?php echo $all_data['zipcode']; ?>" />

				<input type="submit" value="Pay" />
			</form>
		</body>
		</html> -->
<?php
		exit;
	}


	/**
	 * Process the response from the gateway and mark the order as completed or failed
	 *
	 * @since 1.0.0
	 */




	function processgateway_function($payment_type, $details)
	{
		if (isset($_GET['txref'])) {
			var_dump('hello world');
			// die();
			$this->requery($payment_type);
		}
	}
	function requery($payment_type)
	{
		$order_id = $_POST['order_id'];

		$status = $this->verifyTransaction($_GET['txref']);


		if ($status == 'SUCCESS') {

			$payment_details = "success action returned";

			do_action(
				"wpjobster_" . $payment_type . "_payment_success",
				$order_id,
				$this->unique_slug,
				$payment_details,
				// $payment_response
			);
		} else {
			$payment_details = "Failed action returned"; // any info you may find useful for debug
			do_action(
				"wpjobster_" . $payment_type . "_payment_failed",
				$order_id,
				$this->unique_slug,
				$payment_details,
			);
			die('failed');
		}
	}

	/**
	 * Allow the theme to receive the response if the gateway sends it using the URL instead of the POST
	 */
	public function add_gateway_param_accepted_uri_params($arr = array())
	{
		$arr[] = 'sample_param'; // replace sample_param with a gateway specific parameter
		return $arr;
	}


	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
	 */
	public function add_admin_notice($slug, $class, $message)
	{
		$this->notices[$slug] = array(
			'class' => $class,
			'message' => $message
		);
	}


	public function verifyTransaction($transactionId)
	{
		$wpjobster_kkiapay_enablesandbox = get_option('wpjobster_kkiapay_enablesandbox');
		if ($wpjobster_kkiapay_enablesandbox == 'no') {
			$url = 'https://api.kkiapay.me/api/v1/transactions/status';
		} else {
			$url = 'https://api-sandbox.kkiapay.me/api/v1/transactions/status';
		}


		$response = wp_remote_post($url, [
			'method' => 'POST',
			'headers' => [
				'Accept'     => 'application/json',
				'x-api-key' => get_option('wpjobster_kkiapay_public')
			],
			'body' => [
				'transactionId' => $transactionId
			]
		]);

		$status = '';

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			$result = json_decode($response['body']);
			if ($result->status === 'SUCCESS') {
				$status = 'SUCCESS';
			}
		}

		return $status;
	}





	/**
	 * The primary sanity check, automatically disable the plugin on activation if it doesn't
	 * meet minimum requirements.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 */
	public static function activation_check()
	{
		$environment_warning = self::get_environment_warning(true);
		if ($environment_warning) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die($environment_warning);
		}
	}


	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation.
	 */
	public function check_environment()
	{
		$environment_warning = self::get_environment_warning();
		if ($environment_warning && is_plugin_active(plugin_basename(__FILE__))) {
			deactivate_plugins(plugin_basename(__FILE__));
			$this->add_admin_notice('bad_environment', 'error', $environment_warning);
			if (isset($_GET['activate'])) {
				unset($_GET['activate']);
			}
		}
		if (!function_exists('wpj_get_wpjobster_plugins_list')) {
			if (is_plugin_active(plugin_basename(__FILE__))) {
				deactivate_plugins(plugin_basename(__FILE__));
				$message = __('The current theme is not compatible with the plugin WPJobster Kkiapay Gateway. Activate the WPJobster theme before installing this plugin.', 'wpjobster-sample');
				$this->add_admin_notice($this->unique_slug, 'error', $message);
				if (isset($_GET['activate'])) {
					unset($_GET['activate']);
				}
			}
		}
	}


	/**
	 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 */
	static function get_environment_warning($during_activation = false)
	{
		if (version_compare(phpversion(), WPJOBSTER_SAMPLE_MIN_PHP_VER, '<')) {
			if ($during_activation) {
				$message = __('The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-sample');
			} else {
				$message = __('The Sample Powered by wpjobster plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-sample');
			}
			return sprintf($message, WPJOBSTER_SAMPLE_MIN_PHP_VER, phpversion());
		}
		return false;
	}


	/**
	 * Adds plugin action links
	 *
	 * @since 1.0.0
	 */
	public function plugin_action_links($links)
	{
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __('Settings', 'wpjobster-sample') . '</a>',
		);
		return array_merge($plugin_links, $links);
	}


	/**
	 * Get setting link.
	 *
	 * @return string Braintree checkout setting link
	 */
	public function get_setting_link()
	{
		$section_slug = $this->unique_slug;
		return admin_url('admin.php?page=payment-methods&active_tab=tabs' . $section_slug);
	}


	/**
	 * Display any notices we've collected thus far (e.g. for connection, disconnection)
	 */
	public function admin_notices()
	{
		foreach ((array) $this->notices as $notice_key => $notice) {
			echo "<div class='" . esc_attr($notice['class']) . "'><p>";
			echo wp_kses($notice['message'], array('a' => array('href' => array())));
			echo "</p></div>";
		}
	}
}

$GLOBALS['WPJobster_Sample_Loader'] = WPJobster_Sample_Loader::get_instance();
register_activation_hook(__FILE__, array('WPJobster_Sample_Loader', 'activation_check'));
