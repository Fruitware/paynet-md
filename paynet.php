<?php
/*
Plugin Name: paynet.md payment gateway
Plugin URI: https://paynet.md/ecom
Description: Платежный шлюз "paynet.md" для сайтов на WordPress. (версия 1.2.1)
Version: 1.2.1
Last Update: 22.12.2017
Author: paynet.md
Author URI: https://paynet.md/ecom
*/

if (!defined('ABSPATH')) exit;

add_action( 'plugins_loaded', 'wc_paynetmdecom_gateway_init', 0 );
register_activation_hook( __FILE__,"paynetmdecom_activate");

$plugin_dir = basename(dirname(__FILE__));

$load_text= load_plugin_textdomain( 'paynetmdecom', '/wp-content/plugins/'. $plugin_dir , $plugin_dir );

function wc_paynetmdecom_gateway_init() {

	if (!class_exists('WC_Payment_Gateway')) return;

	class WC_Gateway_paynetmdecom extends WC_Payment_Gateway
	{
		private $_paynet_name = "ecom.paynet.md 1.2.1";
		private $_url_client_test = "https://test.paynet.md/acquiring/setecom";
		private $_url_client = "https://paynet.md/acquiring/setecom";
		private $_url_merchant_test = "https://test.paynet.md:4447";
		private $_url_merchant = "https://paynet.md:4448";

		public function __construct()
		{

			global $woocommerce;
			global $paynetmdecom;

			$this->id = 'paynetmdecom';
			$this->has_fields = false;
			$this->method_title = $this->_paynet_name;
			$this->method_description = $this->_paynet_name;
			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->merchant_id = $this->get_option('merchant_id');
			$this->secret_key = $this->get_option('secret_key');
			$this->customer_code = $this->get_option('customer_code');
			$this->user_login = $this->get_option('user_login');
			$this->user_password = $this->get_option('user_password');
			$this->mode = $this->get_option('mode');

			$plugin_dir = plugin_dir_url(__FILE__);
			$this->icon = apply_filters( 'woocommerce_gateway_icon', ''.$plugin_dir.'resources/all-gateway-icons.png' );


			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_paynetmdecom', array($this, 'receipt_page'));

			add_action('woocommerce_api_wc_gateway_paynetmdecom', array($this, 'response_check'));

			add_action('woocommerce_api_wc_gateway_paynetmdecom_ipn', array( $this, 'check_ipn_response' ) );

		}

		public function admin_options()
		{
			global $woocommerce;
			global $paynetmdecom;
			?>
			<h3><?php echo "Admin panel for $this->_paynet_name <hr>"; ?></h3>

			<?php if ($this->is_valid_for_use()) { ?>

			<table class="form-table">
				<?php
				$this->generate_settings_html();
				?>
			</table>

		<?php } else {

			?>
			<div class="inline error"><p>
					<strong>Message: </strong><?php echo 'Мы не поддерживает валюты Вашего магазина.'; ?>
				</p></div>
			<?php
		}
		}
		function is_valid_for_use()
		{
			if (!in_array(get_option('woocommerce_currency'), array('MDL'))) {
				return false;
			}

			return true;
		}
		function convert_currency_to_code()
		{
			switch(get_woocommerce_currency())
			{
				case 'USD': return 840;
				case 'EUR': return 978;
			}
			return 498;
		}

		public function init_form_fields()
		{

			$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
				'enabled' => array(
					'title' => __('Вкл. / Выкл.', 'paynetmdecom'),
					'type' => 'checkbox',
					'label' => __('Включить', 'paynetmdecom'),
					'default' => 'yes'
				),
				'mode' => array(
					'title'   => __( 'Режим работы', 'paynetmdecom' ),
					'type'    => 'checkbox',
					'label'   => __( 'Установите для приема реальных платежей', 'paynetmdecom' ),
					'default' => 'no'
				),

				'title' => array(
					'title'       => __( 'Имя платежной системы', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Данное название отображается в списке доступных платежных систем', 'paynetmdecom' ),
					'default'     => __( 'Оплата через paynet.md', 'paynetmdecom' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Описание платежной системы', 'paynetmdecom' ),
					'type'        => 'textarea',
					'description' => __( 'Данное описание предоставляет информацию о платежной системе', 'paynetmdecom' ),
					'default'     => "visa, master card, bitcoin, yandex",
					'desc_tip'    => true,
				),

				'merchant_id' => array(
					'title'       => __( 'Мерчант ид.', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Уникальный номер в системе paynet.md', 'paynetmdecom' )
				),
				'secret_key' => array(
					'title'       => __( 'Секретный ключ', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Секретный ключ, предоставлен платежной системой', 'paynetmdecom' )
				),
				'customer_code' => array(
					'title'       => __( 'Код пользователя', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Код пользователя нужен для генерации формы, предоставлен платежной системой', 'paynetmdecom' )
				),
				'user_login' => array(
					'title'       => __( 'Имя пользователя', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Имя пользователя API для проверки платежа', 'paynetmdecom' )
				),
				'user_password' => array(
					'title'       => __( 'Пароль пользователя', 'paynetmdecom' ),
					'type'        => 'text',
					'description' => __( 'Пароль API  для проверки платежа', 'paynetmdecom' )
				),
			) );
		}

		//-------------------------------------
		public function process_payment( $order_id )
		{
			$order = wc_get_order( $order_id );
			return array(
				'result'    => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);

		}
		//---------------------------------------------
		public function receipt_page($order)
		{
			global $wpdb;
			global $woocommerce;

			$table_name = $wpdb->prefix."paynetmdecom_log";

			$pp_order = $wpdb->get_row( "SELECT * FROM $table_name WHERE action='response_check_order' and order_id = ".strval(intval($order)));
			$image_path = plugin_dir_url('ecom-paynet-md').'ecom-paynet-md/resources/';
			echo "<a href='https://paynet.md' target='blank'><img src='$image_path/paynet-top-logo.png' /></a>";
			echo "<br><div>";
			echo "<a href='#' target='blank'><img src='$image_path/bitcoin-logo.png' width='120' /></a>";
			echo "<a href='#' ><img src='$image_path/logo-yandex-money.png' width='120' /></a>";
			echo "<a href='#' ><img src='$image_path/maestro-logo.png' width='80' /></a>";
			echo "<a href='#' ><img src='$image_path/mastercard-logo.png' width='100' /></a>";
			echo "<a href='#' ><img src='$image_path/qiwi.png' width='90' /></a>";
			echo "<a href='#' ><img src='$image_path/visa-electron-logo.png' width='100' /></a>";
			echo "<a href='#' ><img src='$image_path/visa-logo.png' width='100' /></a>";
			echo "</div>";

			if($this->mode !== "yes")
			{
				//echo  "<h4>Locale: ".get_locale()."</h4>";
				//echo  "<h4>Title: ".get_bloginfo()."</h4>";
				echo  "<h4 style='color:red;margin-top:10px; padding: 7px; border: 2px solid red;font-weight:bold;' >".__( 'Вы используете тестовый режим для обращения к платежному шлюзу !!!', 'paynetmdecom' )."</h4>";
			}
			if($pp_order == null)
			{
				echo '<p>'.__('Спасибо за Ваш выбор, пожалуйста, нажмите кнопку ниже, чтобы перейти к оплате.', 'paynetmdecom' ).'</p>';
				echo $this->generate_form($order);
			}else
			{
				$order_obj = new WC_Order($order_id);
				echo "<p>".__('Данный платеж уже был переправлен на сторону платежного шлюза.', 'paynetmdecom' )."</p>";
				echo "<p>".__('Если платеж не прошел успешно повторите занова или дождитесь его обработки или свяжитесть со службой поддержки.', 'paynetmdecom' )."</p>";
				echo '<form method="POST" action="'.wc_get_cart_url().'">'.
				     '<input type="submit" class="button alt" id="submit_paynetmdecom_button" value="'.__('Вернуться в корзину', 'paynetmdecom' ).'" />'.
				     '<a class="button cancel" href="' . $order_obj->get_cancel_order_url() . '">'.__('Отказаться от оплаты и вернуться в корзину', 'paynetmdecom' ).'</a>' . "\n" .
				     '</form>';
			}
		}
		//------------------------------------------------
		public function generate_form($order_id)
		{
			global $woocommerce;
			global $paynetmdecom;
			global $wpdb;
			global $wp;

			$order = new WC_Order($order_id);
			$merchant_id = $this->merchant_id;
			$secret_key = $this->secret_key;
			$redirect_url = "";
			if($this->mode == "yes")
				$redirect_url = $this->_url_client;
			else
				$redirect_url = $this->_url_client_test;

			$date = strtotime("+5 hour");
			$_date_expire = date('Y-m-d', $date).'T'.date('H:i:s', $date);
			$_lang = substr(get_locale(),0,2);
			$_amount = $order->order_total * 100;

			//-----------------------------------------------------------------
			$_customer = array(
				'Code' 		=>  $this->customer_code,
				'Address' 	=> NULL,
				'Name' 		=> get_bloginfo()
			);

			//----------------- preparing a service  ----------------------------
			$items = $order->get_items();
			$_service_name = '';
			$product_line = 0;
			$_service_item = "";
			foreach ( $items as $item ) {
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][LineNo]" value="'.$product_line.'"/>';
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Code]" value="'.esc_attr($item['product_id']).'"/>';
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Name]" value="'.esc_attr($item['name']).'"/>';
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Description]" value="'.esc_attr($item['name']).'"/>';
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][Quantity]" value="'.esc_attr($item['quantity']).'"/>';
				$_service_item .='<input type="hidden" name="Services[0][Products]['.$product_line.'][UnitPrice]" value="'.esc_attr(($item['total'] * 100)/$item['quantity']).'"/>';
				if($product_line == 0) $_service_name = $item['name'];

				$product_line++;
			}

			$_services	= array(
				array('Name' =>$_service_name, 'Description' =>$_service_name, 'Amount' => $_amount)
			);
			$_service_item .='<input type="hidden" name="Services[0][Description]" value="'.esc_attr($_services[0]["Description"]).'"/>';
			$_service_item .='<input type="hidden" name="Services[0][Name]" value="'.esc_attr($_services[0]["Name"]).'"/>';
			$_service_item .='<input type="hidden" name="Services[0][Amount]" value="'.esc_attr($_services[0]['Amount']).'"/>';

			//------------------------------- creating a Signature --------------------------------------
			$current_url = home_url($wp->request);
			$_linkUrlSuccess = add_query_arg(array('wc-api' => 'WC_Gateway_paynetmdecom', "id" => $order_id, 'result' => 'ok'), home_url('/'));
			$_linkUrlCancel = add_query_arg(array('wc-api' => 'WC_Gateway_paynetmdecom', "id" => $order_id, 'result' => 'error'), home_url('/'));

			$_sing_raw  = $this->convert_currency_to_code();
			$_sing_raw .= $_customer['Address'].$_customer['Code'].$_customer['Name'];
			$_sing_raw .= $_date_expire.strval($order_id).$merchant_id;
			$_sing_raw .= $_services[0]['Amount'].$_services[0]['Name'].$_services[0]['Description'];

			$_sing_raw .= $secret_key;
			$signature = base64_encode(md5($_sing_raw, true));

			$pp_form =  '<form method="POST" action="'.$redirect_url.'">'.
			            '<input type="hidden" name="ExternalDate" value="'.esc_attr(date('Y-m-d', time()).'T'.date('H:i:s', time())).'"/>'.
			            '<input type="hidden" name="ExternalID" value="'.esc_attr(strval($order_id)).'"/>'.
			            '<input type="hidden" name="Currency" value="'.esc_attr( $this->convert_currency_to_code()).'"/>'.
			            '<input type="hidden" name="Merchant" value="'.esc_attr($merchant_id).'"/>'.
			            '<input type="hidden" name="Customer.Code"   value="'.esc_attr($_customer['Code']).'"/>'.
			            '<input type="hidden" name="Customer.Name"   value="'.esc_attr($_customer['Name']).'"/>'.
			            '<input type="hidden" name="LinkUrlSuccess" value="'.esc_attr($_linkUrlSuccess).'"/>'.
			            '<input type="hidden" name="LinkUrlCancel" value="'.esc_attr($_linkUrlCancel).'"/>'.
			            $_service_item.
			            '<input type="hidden" name="ExpiryDate"   value="'.esc_attr($_date_expire).'"/>'.
			            '<input type="hidden" name="Signature" value="'.$signature.'"/>'.
			            '<input type="hidden" name="Lang" value="'.$_lang.'"/>'.
			            '<input type="submit" class="button alt" id="submit_paynetmdecom_button" value="'.__('Оплатить', 'paynetmdecom' ).'" />'.
			            '<a class="button cancel" href="' . $order->get_cancel_order_url() . '">'.__('Отказаться от оплаты и вернуться в корзину', 'paynetmdecom' ).'</a>' . "\n" .
			            '</form>';

			$this->pp_add_to_log('generate_form',$pp_form,$order_id);
			return $pp_form;
		}

		//---------------------- response from a ecom server
		public function response_check()
		{
			global $woocommerce;
			global $paynetmdecom;
			global $wpdb;
			global $wp;

			$order_id = isset($_GET['id']) ? $_GET['id'] : null;
			$result_page = isset($_GET['result']) ? $_GET['result'] : null;
			if($order_id !== null)
			{
				$order = new WC_Order($order_id);

				if ($order != null && $result_page == "ok") {
					//------------ request to the ecom server
					$auth = $this->pp_auth();

					if($auth["result"] == "ok")
					{
						$_orderCheck = $this->pp_order_check($order_id,$auth["token"]);
						$this->pp_add_to_log('response_check_order',print_r($_orderCheck,true),$order_id);

						if($_orderCheck["result"] == "ok")
						{
							$ecom_order = $_orderCheck["object"];
							if(intval($ecom_order->Status) === 4 && intval($ecom_order->Invoice) == $order_id)
							{
								$order->payment_complete();
								$order->update_status('completed', 'The payment was approved on the paynet.md payment gatwey.  [Status: '.$ecom_order->Status.', ConfirmDate: '.$ecom_order->Confirmed.', EcomID: '.$ecom_order->OperationId.']');
								$woocommerce->cart->empty_cart();
								wp_redirect($this->get_return_url( $order ));
								exit();
							}else
							{
								// Add order note
								$order->add_order_note( 'The order Id: '.$order_id.'  processing. Status: '.$ecom_order->Status.' EcomID:'.$ecom_order->OperationId );

							}
						}else
						{
							$order->update_status('cancelled', 'The payment was canceled. Not found or exeption in method get status, Response: '.print_r($_orderCheck,true));
						}
					}else
					{
						$this->pp_add_to_log('response_check_auth',print_r($auth,true),$order_id);
						$order->update_status('cancelled', 'The payment was canceled, because happened error during a aut connection to the payment gateway. Response: '.print_r($auth,true));
					}

					wp_redirect($order->get_cancel_order_url());
					exit();

				}else
				{
					$order->update_status('cancelled', 'The payment was canceled by a customer on the payment gateway site.');
					wp_redirect($order->get_cancel_order_url());
					exit();
				}
				exit();

			}else {
				wp_redirect(home_url());
				exit;
			}
		}
		//---------------------------
		function check_ipn_response()
		{
			$this->pp_add_to_log('check_ipn_response',"--->>>>>",$order_id);
		}
		//--------- get an object of ecom transaction
		function pp_order_check($order_id, $token)
		{

			$_url_merchant_api = "";
			if($this->mode == "yes")
				$_url_merchant_api = $this->_url_merchant;
			else
				$_url_merchant_api = $this->_url_merchant_test;
			$_url_merchant_api .= "/api/Payments?ExternalID=".$order_id;

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL =>$_url_merchant_api ,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_HTTPHEADER => array(
					"cache-control: no-cache",
					"authorization: bearer ".$token
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			$request_info = curl_getinfo($curl);
			curl_close($curl);

			if ($err) {
				return array("result" => "error", "msg" => $err);
			}
			else
			{
				if($request_info["http_code"] == "200")
				{
					$order = json_decode($response);
					return array("result" => "ok", "object" => $order[0]);
				}else
				{
					$errObj = json_decode($response);
					return array("result" => "error", "msg" => $errObj->Message);
				}
			}
			//------------------------
			return array("result" => "error", "msg" => $response);
		}
		//--------- get a permission token
		function pp_auth()
		{
			global $wpdb;

			$_merchant_user = $this->user_login;
			$_merchant_user_pass = htmlspecialchars_decode($this->user_password);
			$_cred = "grant_type=password&username=".$_merchant_user."&password=".urlencode($_merchant_user_pass);

			$_url_merchant_api = "";
			if($this->mode == "yes")
				$_url_merchant_api = $this->_url_merchant."/auth";
			else
				$_url_merchant_api = $this->_url_merchant_test."/auth";

			$wpdb->insert( 'wp_log',array('action' => 'pp_auth','text' => $_cred ), array( '%s','%s' ) );
			$curl = curl_init();

			curl_setopt_array($curl, array(
				//CURLOPT_PORT => $_url_merchant_port",
				CURLOPT_URL =>$_url_merchant_api,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => $_cred,
				CURLOPT_HTTPHEADER => array("cache-control: no-cache")
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			$request_info = curl_getinfo($curl);
			curl_close($curl);

			if ($err) {
				return array("result" => "error", "msg" => $err);
			}
			else
			{
				if($request_info["http_code"] == "200")
				{
					$auth = json_decode($response);
					return array("result" => "ok", "token" => $auth->access_token, "msg" => "");
				}else
				{
					$auth = json_decode($response);
					return array("result" => "error", "msg" => $auth->error);
				}
			}
			//------------------------
			return array("result" => "error", "msg" => $response);
		}

		function pp_add_to_log($action, $data, $order_id = 0)
		{
			global $wpdb;
			$table_name = $wpdb->prefix . "paynetmdecom_log";
			$wpdb->insert( $table_name, array('action' => $action,'data' => $data, 'order_id' => $order_id ), array( '%s','%s', '%d' ) );
		}
	}

	//---------------------------------------------------

	function wc_offline_add_paynetmdecom_gateways( $gateways ) {
		$gateways[] = 'WC_Gateway_paynetmdecom';
		return $gateways;
	}

	add_filter( 'woocommerce_payment_gateways', 'wc_offline_add_paynetmdecom_gateways' );
}

function paynetmdecom_activate()
{
	global $wpdb;

	$table_name = $wpdb->prefix . "paynetmdecom_log";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name)
	{
		$wpdb->insert( $table_name,array('action' => "paynetmdecom_install",'data' => "Was activated the plugin.Version: 1.2.1, table with name ".$table_name."  already exist !", 'order_id' => -2 ), array( '%s','%s', '%d' ) );
	}
	else
	{
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `reg_date` timestamp DEFAULT CURRENT_TIMESTAMP,
                          `order_id` int(11) DEFAULT NULL,			 
                          `action` varchar(256) DEFAULT '' NOT NULL,
                          `data` text NOT NULL,
                          PRIMARY KEY  (id)) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$r = dbDelta( $sql );
		$wpdb->insert( $table_name,array('action' => "paynetmdecom_install",'data' => print_r($r,true), 'order_id' => -1 ), array( '%s','%s', '%d' ) );
	}

}





// Payment Callback
add_action( 'rest_api_init', 'paynet_callback_order_api');

function paynet_callback_order_api(){
	register_rest_route( 'paynet/v2', '/callback', array(
		'methods' => array('GET', 'POST'),
		'callback' => 'payment_confirm_order_api',
	));
}


// The callback to confirm order
function payment_confirm_order_api($data){

	// add_action( 'woocommerce_thankyou', 'custom_woocommerce_auto_complete_order' );
	add_action( 'woocommerce_thankyou', 'woocommerce_thankyou_change_order_status', 10, 1);
	return $data;
	//return 'test2';
}






function custom_woocommerce_auto_complete_order( $order_id ) {
	if ( ! $order_id ) {
		return;
	}

	$order = wc_get_order( $order_id );
	$order->update_status( 'completed' );
}




function woocommerce_thankyou_change_order_status( $order_id ){
	if( ! $order_id ) return;

	$order = wc_get_order( $order_id );

	$order->update_status( 'completed' );
}
?>
