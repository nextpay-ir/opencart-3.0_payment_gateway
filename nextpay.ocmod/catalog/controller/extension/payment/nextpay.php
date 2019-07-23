<?php
class ControllerExtensionPaymentNextPay extends Controller {

	private $error;

	public function index() {

		// Load testmode text
		$this->load->language('extension/payment/nextpay');
		$data['text_testmode'] = $this->language->get('text_testmode');


		// Set up confirm/back button text
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');

		// Check if payment was a failure
		if(!empty($_GET['fail'])) $data['error'] = 'Previous Payment FAILED, please try again';

		// Load model for checkout page
		$this->load->model('checkout/order');

		// Load order into memory
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$currencies = array(
			'AUD'	=> '036',
			'CAD'	=> '124',
			'DKK'	=> '208',
			'HKD'	=> '344',
			'ILS'	=> '376',
			'JPY'	=> '392',
			'KRW'	=> '410',
			'NOK'	=> '578',
			'GBP'	=> '826',
			'SAR'	=> '682',
			'SEK'	=> '752',
			'CHF'	=> '756',
			'USD'	=> '840',
			'EUR'	=> '978',
		);

		$this->load->model('localisation/currency');
		
		$currency_infos = $this->model_localisation_currency->getCurrencies();

		// Currency set to GBP
		$currency = 'GBP';
		$currency_info = $currency_infos[$currency];
		
		$data['currency_iso'] = '826';
		
		$order_currency_code = strtoupper($order_info['currency_code']);
		
		if(isset($currencies[$order_currency_code]) && isset($currency_infos[$order_currency_code])) {
			$currency = $order_currency_code;
			$currency_info = $currency_infos[$currency];
			$data['currency_iso'] = $currencies[$order_currency_code];
		}
		
		if (!isset($currency_infos[$currency])) {
			$data['error'] = 'No suitable currency available. Please try again!';
		}

		$total = $this->currency->format($order_info['total'], $currency, '', false);
		$total  = number_format($total, (int)$currency_info['decimal_place'], $this->language->get('decimal_point'), '');
		
		$data['total'] = $total;
		$amount = $data['total'];

		// Other params for payment page
		$data['api_key'] = $this->config->get('payment_nextpay_api_key');
		$api_key = $data['api_key'];
		$data['zip'] = html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8');
		$data['email'] = $order_info['email'];
		$data['order_id'] = $this->session->data['order_id'];
		$order_id = $data['order_id'];

		$enc = strtr(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, hash('sha256', $this->config->get('config_encryption'), true), $this->session->data['order_id'], MCRYPT_MODE_ECB)), '+/=', '-_,');


		// Set success/fail urls
		$data['callback'] = str_replace("&amp;",'&',$this->url->link('extension/payment/nextpay/payment', 'e=' . urlencode($enc) . '&', true));
		$callback_uri = $data['callback'];

		// Render page template
		$this->id = 'payment';
		
		require_once("nextpay_payment.php");
		 $parameters = array(
		    'api_key'		=> $api_key,
		    'amount' 		=> $amount,
		    'callback_uri' 	=> $callback_uri,
		    'order_id' 		=> $order_id
		);
		try {
			$nextpay = new Nextpay_Payment($parameters);
			$nextpay->setDefaultVerify(Type_Verify::SoapClient);
			$result = $nextpay->token();
			if(intval($result->code) == -1){
			    //$nextpay->send($result->trans_id);
			    $trans_id = $result->trans_id;
			    $data['trans_id'] = $trans_id;
			    $data['action'] = $nextpay->request_http . "/$trans_id";
			}
			else
			{
			    $message = ' شماره خطا: '.$result->code.'<br />';
			    $message .='<br>'.$nextpay->code_error(intval($result->code));
			    echo $message;
			    exit();
			}
		    }catch (Exception $e) { echo 'Error'. $e->getMessage();  }
		
		return $this->load->view('extension/payment/nextpay', $data);
	}

	public function payment() {

		// Assign $_GET to easier var
		$g = $this->request->get;

		// e is the encrypted order ID
		$e = $g['e'];

		// Check we gota Success message back
		$trans_id = isset($_POST['trans_id']) ? $_POST['trans_id'] : false ;
		$order_id = isset($_POST['order_id']) ? $_POST['order_id'] : false ;
		
		if( $trans_id && $order_id ){
		
		    $this->load->language('extension/payment/nextpay');
		    
		    if (!is_string($trans_id) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $trans_id) !== 1)) {
			$this->response->redirect($this->url->link('checkout/failure', 'fail=1'));
		    }
		    
		    if ($e) {
			    $order_id_t = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, hash('sha256', $this->config->get('config_encryption'), true), base64_decode(strtr($e, '-_,', '+/=')), MCRYPT_MODE_ECB));
		    } else {
			    $order_id_t = 0;
		    }

		    // Hack detection
		    if(!$order_id_t) die('ERROR - Hack attempt detected');
		    // Hack detection by order
		    if($order_id_t != $order_id) die('ERROR - Hack attempt detected');
		    
		    // Load model for checkout page
		    $this->load->model('checkout/order');

		    // Load order, and verify the order has not been processed before, if it has, go to success page
		    $order_info = $this->model_checkout_order->getOrder($order_id_t);
		    
		    if ($order_info) {
			    if ($order_info['order_status_id'] != 0) {
				    $this->response->redirect($this->url->link('checkout/success'));
			    }
		    }
		    
		    $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if (!$order_info)
					throw new Exception($this->language->get('error_order_id'));
		    
		    // List of vars from reply
		    $transaction = $trans_id;	// Transaction ID
		    $amount = $this->correctAmount($order_info);
		    //$amount = $g['Amount'];			// Order Amount
		    //$crypt = $g['Crypt'];			// Crypt of vars
		    
		    $api_key = $this->config->get('payment_nextpay_api_key');
		    
		    $parameters = array
		    (
			'api_key'	=> $api_key,
			'order_id'	=> $order_id,
			'trans_id' 	=> $trans_id,
			'amount'	=> $amount,
		    );
		    try {
			include_once "nextpay_payment.php";
			$nextpay = new Nextpay_Payment();
			$result = $nextpay->verify_request($parameters);
			if( $result < 0 ) {
			    $this->session->data['error'] = $this->language->get('message_fail');
			    $this->response->redirect($this->url->link('checkout/failure', 'fail=1'));
			} elseif ($result==0) {
			    $this->model_checkout_order->addOrderHistory($order_id_t, $this->config->get('payment_nextpay_order_status_id'), 'TWS TRANSACTION ID: ' . $transaction);
			}else{
			    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_nextpay_order_status_id'), 'TWS TRANSACTION ID: ' . $transaction, FALSE);
			}
			$this->response->redirect($this->url->link('checkout/success'));
		    }catch (Exception $e) { echo 'Error'. $e->getMessage();  }
		}
		$this->response->redirect($this->url->link('checkout/failure', 'fail=1'));
	}
	
	private function correctAmount($order_info) {
		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$amount = round($amount);
		$amount = $this->currency->convert($amount, $order_info['currency_code'], "TOM");
		return (int)$amount;
	}
}
