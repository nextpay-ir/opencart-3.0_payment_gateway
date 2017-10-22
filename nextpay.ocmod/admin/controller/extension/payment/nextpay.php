<?php
class ControllerExtensionPaymentNextPay extends controller {
	private $error = array();
	/**
	 * ControllerPaymentNextPay::index()
	 *
	 * default route for form load/update
	 */
	function index() {

		// Load language file and settings model
		$this->load->language('extension/payment/nextpay');
		$this->load->model('setting/setting');

		// Set page title
		$this->document->setTitle($this->language->get('heading_title'));


		// Process settings if form submitted
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {			
			$this->model_setting_setting->editSetting('payment_nextpay', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');
			
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}


		// Set errors if fields not correct
 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

 		if (isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		} else {
			$data['error_api_key'] = '';
		}

		// Set breadcrumbs
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/nextpay', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/nextpay', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
 
		// Load values for fields
		if (isset($this->request->post['payment_nextpay_api_key'])) {
			$data['payment_nextpay_api_key'] = $this->request->post['payment_nextpay_api_key'];
		} else {
			$data['payment_nextpay_api_key'] = $this->config->get('payment_nextpay_api_key');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		
		if (isset($this->request->post['payment_nextpay_status'])) {
			$data['payment_nextpay_status'] = $this->request->post['payment_nextpay_status'];
		} else {
			$data['payment_nextpay_status'] = $this->config->get('payment_nextpay_status');
		}

		if (isset($this->request->post['payment_nextpay_sort_order'])) {
			$data['payment_nextpay_sort_order'] = $this->request->post['payment_nextpay_sort_order'];
		} else {
			$data['payment_nextpay_sort_order'] = $this->config->get('payment_nextpay_sort_order');
		}

		if (isset($this->request->post['payment_nextpay_order_status_id'])) {
			$data['payment_nextpay_order_status_id'] = $this->request->post['payment_nextpay_order_status_id'];
		} else {
			$data['payment_nextpay_order_status_id'] = $this->config->get('payment_nextpay_order_status_id');
		}

		// Load a list of order status values
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Render template
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/nextpay', $data));
	}


	/**
	 * ControllerPaymentNextPay::validate()
	 *
	 * Validation code for form
	 */
	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/nextpay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_nextpay_api_key']) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}

		return !$this->error;
	}
}