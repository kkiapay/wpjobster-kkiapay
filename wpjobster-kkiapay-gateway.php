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


class WPJobster_Kkiapay_Loader
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
		$this->priority = 1211;           // 100, 200, 300 [...] are reserved
		$this->unique_slug = 'kkiapay';    // this needs to be unique

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

		add_action('wp_enqueue_scripts', array($this, 'inject_kkiapay_script'));
	}

	function inject_kkiapay_script()
	{
		wp_enqueue_script('kkiapay', 'https://cdn.kkiapay.me/', false);
	}



	/*
	 * Define the gateways default currency if any
	 */
	function get_gateway_currency($currency)
	{
		// if the gateway requires a specific currency you can declare it there
		// currency conversions are done automatically
		$currency = 'XOF'; // delete this line if the gateway works with any currency
		return $currency;
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

			update_option('wpjobster_kkiapay_public_test',           trim($_POST['wpjobster_kkiapay_public_test']));
			update_option('wpjobster_kkiapay_private_test',           trim($_POST['wpjobster_kkiapay_private_test']));
			update_option('wpjobster_kkiapay_secret_test',           trim($_POST['wpjobster_kkiapay_secret_test']));
			update_option('wpjobster_kkiapay_theme',           trim($_POST['wpjobster_kkiapay_theme']));

			update_option('wpjobster_kkiapay_success_page',  trim($_POST['wpjobster_kkiapay_success_page']));
			update_option('wpjobster_kkiapay_failure_page',  trim($_POST['wpjobster_kkiapay_failure_page']));

			echo '<div class="updated fade"><p>' . __('Settings saved!', 'wpjobster-kkiapay') . '</p></div>';
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
				<table width="100%" class="sitemile-table">
					<tr>
						<td style="border-bottom: none !important;" valign=top width="22"><?php wpjobster_theme_bullet(); ?></td>
						<td style="border-bottom: none !important;" valign="top"><?php _e('Kkiapay Gateway', 'wpjobster-kkiapay'); ?></td>
					</tr>

					<tr>
						<?php // _enable and _button_caption are mandatory
								?>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Activer Kkiapay', 'wpjobster-kkiapay')); ?></td>
						<td width="200"><?php _e('Activer:', 'wpjobster-kkiapay'); ?></td>
						<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_' . $this->unique_slug . '_enable', 'no'); ?></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Mettre Kkiapay en mode sandbox', 'wpjobster-kkiapay')); ?></td>
						<td width="200"><?php _e('Mode Test:', 'wpjobster-kkiapay'); ?></td>
						<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_' . $this->unique_slug . '_enablesandbox', 'no'); ?></td>
					</tr>




					<tr>
						<?php // _enable and _button_caption are mandatory
								?>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Text par defaut du button de paiement', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Texte du button de paiement:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_<?php echo $this->unique_slug; ?>_button_caption" value="<?php echo get_option('wpjobster_' . $this->unique_slug . '_button_caption'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé public lorsque vous ete en Live', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé publique:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_public" value="<?php echo get_option('wpjobster_kkiapay_public'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé privé lorsque vous ete en Live', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé privé:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_private" value="<?php echo get_option('wpjobster_kkiapay_private'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé secrete lorsque vous ete en Live', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé secrete:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_secret" value="<?php echo get_option('wpjobster_kkiapay_secret'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé public lorsque vous ete en Sandbox', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé publique Test:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_public_test" value="<?php echo get_option('wpjobster_kkiapay_public_test'); ?>" /></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé privé lorsque vous ete en Sandbox', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé privé Test:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_private_test" value="<?php echo get_option('wpjobster_kkiapay_private_test'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Votre clé secrete lorsque vous ete en Sandbox', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Clé secrete Test:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="password" size="45" name="wpjobster_kkiapay_secret_test" value="<?php echo get_option('wpjobster_kkiapay_secret_test'); ?>" /></td>
					</tr>
					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(__('Definissez la couleur du widget', 'wpjobster-kkiapay')); ?></td>
						<td><?php _e('Couleur du widget:', 'wpjobster-kkiapay'); ?></td>
						<td><input type="text" size="45" name="wpjobster_kkiapay_theme" value="<?php echo get_option('wpjobster_kkiapay_theme'); ?>" /></td>
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

			$wpjobster_kkiapay_enablesandbox = get_option('wpjobster_kkiapay_enablesandbox');

			if ($wpjobster_kkiapay_enablesandbox == 'no') {
				$kkiapay_payment_url = 'https://api.kkiapay.me/api/v1/transactions/status';
				$key = get_option('wpjobster_kkiapay_public');
			} else {
				$kkiapay_payment_url = 'https://api-sandbox.kkiapay.me/api/v1/transactions/status';
				$key = get_option('wpjobster_kkiapay_public_test');
			}

			$credentials = array(
				'key'                 => $key,
				'sandbox' 			  => $wpjobster_kkiapay_enablesandbox != 'no',
				'kkiapay_payment_url' => $kkiapay_payment_url,
			);

			return $credentials;
		}

		// wpjobster_kkiapay_enable
		/**
		 * Collect all the info that we need and forward to the gateway
		 *
		 * @since 1.0.0
		 */
		public function taketogateway_function($payment_type, $common_details)
		{
			$credentials = $this->get_gateway_credentials();

			$all_data                       = array();
			$all_data['key']                = $credentials['key'];
			$all_data['kkiapay_payment_url'] = $credentials['kkiapay_payment_url']; // The URL where all the data will be posted that is the gateway endpoint.


			$uid                            = $common_details['uid'];
			$wpjobster_final_payable_amount = $common_details['wpjobster_final_payable_amount'];
			$currency                       = $common_details['currency'];
			$order_id                       = $common_details['order_id'];
			$wpjobster_kkiapay_enablesandbox = get_option('wpjobster_kkiapay_enablesandbox') == 'no' ? 'false' : 'true';

			// user() is a helper function which calls the appropriate function
			// between get_userdata() and get_user_meta() depending on what info is needed

			$all_data['amount']       = $wpjobster_final_payable_amount;
			$all_data['currency']     = $currency;
			$all_data['success_url']  = get_bloginfo('url') . '/?jb_action=process_payment&payment_response=kkiapay&payment_type=' . $payment_type;
			$all_data['fail_url']     = get_bloginfo('url') . '/?payment_response=kkiapay&action=fail&payment_type=' . $payment_type;

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
			$theme = get_option('wpjobster_kkiapay_theme');
			$website_url = get_bloginfo('url');

			$loading_text = __('Loading...', 'wpjobster-kkiapay');

			get_header();
			?>
		<script>
			window.addEventListener('DOMContentLoaded', function() {
				openKkiapayWidget({
					key: "<?= $all_data['key'] ?>",
					sandbox: "<?= $wpjobster_kkiapay_enablesandbox ?>",
					amount: <?= $all_data['amount']; ?>,
					theme: "<?= $theme ?>",
					data: {
						orderId: <?= $order_id; ?>
					},
					callback: "<?= $all_data['success_url'] ?>"
				})

				addSuccessListener(response => {
					console.log(response)

					const url = "<?= $all_data['success_url'] . '&transaction_id=' ?>"

					const full_url = url + `${response.transactionId}`

					window.location.href = full_url
				})
			})
		</script>
<?php
		get_footer();
		exit;
	}

	public function verify_transaction($transactionId)
	{
		$credentials = $this->get_gateway_credentials();
		$kkiapay_payment_url = $credentials['kkiapay_payment_url'];

		$verify_transaction_url = $kkiapay_payment_url;

		$response = wp_remote_post($verify_transaction_url, [
			'method' 	=> 'POST',
			'headers'	=> [
				'Accept' 	=> 'application/json',
				'x-api-key'	=> $credentials['key']
			],
			'body'		=> [
				'transactionId' => $transactionId
			]
		]);

		$result = 'failed';

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			$result = json_decode($response['body']);
		}

		return $result;
	}

	/**
	 * Process the response from the gateway and mark the order as completed or failed
	 *
	 * @since 1.0.0
	 */
	function processgateway_function($payment_type, $details)
	{
		$credentials        = $this->get_gateway_credentials();
		$key                = $credentials['key'];
		$merchant_key       = $credentials['merchant_key'];
		$kkiapay_payment_url = $credentials['kkiapay_payment_url'];

		// you will usually get the response from the gateway as $_POST
		$transaction_id   = $_GET['transaction_id'];

		$kkiapay_response = $this->verify_transaction($transaction_id);

		if ($kkiapay_response->status == 'SUCCESS') {
			$payment_details = "ID transaction Kkiapay " . $transaction_id; // any info you may find useful for debug
			do_action(
				"wpjobster_" . $payment_type . "_payment_success",
				$kkiapay_response->state->orderId,
				$this->unique_slug,
				$payment_details,
				maybe_serialize($kkiapay_response)
			);
		} else {
			$payment_details = "Failed action returned"; // any info you may find useful for debug
			do_action(
				"wpjobster_" . $payment_type . "_payment_failed",
				$kkiapay_response->state->orderId,
				$this->unique_slug,
				$payment_details,
				maybe_serialize($kkiapay_response)
			);
		}
	}

	/**
	 * Allow the theme to receive the response if the gateway sends it using the URL instead of the POST
	 */
	public function add_gateway_param_accepted_uri_params($arr = array())
	{
		$arr[] = 'transaction_id'; // replace kkiapay_param with a gateway specific parameter
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
				$message = __('The current theme is not compatible with the plugin WPJobster Kkiapay Gateway. Activate the WPJobster theme before installing this plugin.', 'wpjobster-kkiapay');
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
		if (version_compare(phpversion(), WPJOBSTER_KKIAPAY_MIN_PHP_VER, '<')) {
			if ($during_activation) {
				$message = __('The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-kkiapay');
			} else {
				$message = __('The Kkiapay Powered by wpjobster plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-kkiapay');
			}
			return sprintf($message, WPJOBSTER_KKIAPAY_MIN_PHP_VER, phpversion());
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
			'<a href="' . $setting_link . '">' . __('Settings', 'wpjobster-kkiapay') . '</a>',
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

$GLOBALS['WPJobster_Kkiapay_Loader'] = WPJobster_Kkiapay_Loader::get_instance();
register_activation_hook(__FILE__, array('WPJobster_Kkiapay_Loader', 'activation_check'));
