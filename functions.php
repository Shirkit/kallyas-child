<?php
// Replacing POS edit order page: it's done via MU-PLUGINS
?>
<?php
/* ========================================================
 * WooCommerce API
 * ======================================================== */

add_action('pos_admin_print_scripts', 'orquidario_pos_admin_print_scripts');

function orquidario_pos_admin_print_scripts()
{
	?>
<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
<?php
}

add_action('wc_pos_footer_scripts', 'modal_printer_select');
function modal_printer_select()
{
	?>
<div class="md-modal md-dynamicmodal md-menu md-close-by-overlay md-register" id="modal-printer_select">
  <div class="md-content">
    <h1>Configurações de Impressão<span class="md-close"></span></h1>
    <div class="md-content-wrapper">
      <p class="form-row form-row-wide" id="selected_printer_field" style="margin: 0; ">
        <label for="selected_printer" style="font-variant: all-petite-caps; width: calc(100% - 12px); padding: 12px 0; padding-left: 12px; color: #757575;"><span class="dashicons printing-receipt-icon"></span> - Selecione a impressora</label>
        <span>
          <select name="selected_printer" id="selected_printer" class="select wc-ecfb-select"></select>
        </span>
      </p>
      <table id="past_orders">
        <thead>
          <tr>
            <th>#</th>
            <th>Valor</th>
            <th>Hora</th>
            <th>-</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
    <div class="wrap-button">
      <button class="button button-primary wp-button-large alignright" type="button" id="save_selected_printer">Salvar</button>
    </div>
  </div>
</div>
<?php
}

add_action('valid_pagseguro_assinaturas_ipn_request', 'orquidario_woocommerce_api_pagseguro_notification_handler', 100, 1);
function orquidario_woocommerce_api_pagseguro_notification_handler($posted)
{
	if ('transaction' == $_POST['notificationType']) {
		//update order
		if (isset($posted->reference)) {
			$order = wc_get_order($id);

			// Check if order exists.
			if (!$order) {
				return;
			}

			if ('' != get_post_meta($order->get_id(), '_pagseguro_assinatura', true)) {
				return;
			}

			// Checks whether the invoice number matches the order.
			// If true processes the payment.
			if ($order->get_id() === $id) {
				if ('yes' === $this->debug) {
					$this->log->add($this->id, 'PagSeguro payment status for order ' . $order->get_id() . ' is: ' . intval($posted->status));
				}

				$transaction_id = get_post_meta($order->get_id(), '_pagseguro_transaction_id', true);
				$posted_transaction_id = str_replace('-', '', $posted->code);

				if ($posted_transaction_id == $transaction_id) {
					switch (intval($posted->status)) {
						case 1:
							$order->update_status('on-hold');
							$order->add_order_note(__('PagSeguro: The buyer initiated the transaction. Waiting for payment confirmation.', 'woocommerce-pagseguro-assinaturas'));
							break;
						case 2:
							$order->update_status('on-hold');
							$order->add_order_note(__('PagSeguro: Payment under review.', 'woocommerce-pagseguro-assinaturas'));
							// Reduce stock for billets.
							if (function_exists('wc_reduce_stock_levels')) {
								wc_reduce_stock_levels($order->get_id());
							}

							break;
						case 3:
							// Sometimes PagSeguro should change an order from cancelled to paid, so we need to handle it.
							if (method_exists($order, 'get_status') && 'cancelled' === $order->get_status()) {
								$order->update_status('processing', __('PagSeguro: Payment approved.', 'woocommerce-pagseguro-assinaturas'));
								wc_reduce_stock_levels($order->get_id());
							} else {
								$order->add_order_note(__('PagSeguro: Payment approved.', 'woocommerce-pagseguro-assinaturas'));

								// Changing the order for processing and reduces the stock.
								$order->payment_complete(sanitize_text_field((string) $posted->code));
							}

							break;
						case 4:
							$order->add_order_note(__('PagSeguro: Payment completed and credited to your account.', 'woocommerce-pagseguro-assinaturas'));

							break;
						case 5:
							$order->update_status('on-hold');
							$order->add_order_note(__('PagSeguro: Payment came into dispute.', 'woocommerce-pagseguro-assinaturas'));
							$this->send_email(
								/* translators: %s: order number */
								sprintf(__('Payment for order %s came into dispute', 'woocommerce-pagseguro-assinaturas'), $order->get_id()),
								__('Payment in dispute', 'woocommerce-pagseguro-assinaturas'),
								/* translators: %s: order number */
								sprintf(__('Order %s has been marked as on-hold, because the payment came into dispute in PagSeguro.', 'woocommerce-pagseguro-assinaturas'), $order->get_id())
							);

							break;
						case 6:
							$order->update_status('refunded', __('PagSeguro: Payment refunded.', 'woocommerce-pagseguro-assinaturas'));
							$this->send_email(
								/* translators: %s: order number */
								sprintf(__('Payment for order %s refunded', 'woocommerce-pagseguro-assinaturas'), $order->get_id()),
								__('Payment refunded', 'woocommerce-pagseguro-assinaturas'),
								/* translators: %s: order number */
								sprintf(__('Order %s has been marked as refunded by PagSeguro.', 'woocommerce-pagseguro-assinaturas'), $order->get_id())
							);

							if (function_exists('wc_increase_stock_levels')) {
								wc_increase_stock_levels($order->get_id());
							}

							break;
						case 7:
							$order->update_status('cancelled', __('PagSeguro: Payment canceled.', 'woocommerce-pagseguro-assinaturas'));

							if (function_exists('wc_increase_stock_levels')) {
								wc_increase_stock_levels($order->get_id());
							}

							break;

						default:
							break;
					}
				} else {

					// If got to this point, it's probably a validation transaction
					if ((int) $posted->type == 1 && (int) $posted->status == 6) {
						if ('yes' != get_post_meta($order->get_id(), '_pagseguro_card_valid', true)) {
							update_post_meta($order->get_id(), '_pagseguro_card_valid', 'yes');

							$order->add_order_note(__('PagSeguro IPN: Credit Card Validated.', 'woocommerce-pagseguro-assinaturas'));

							$this->process_initial_payment($order);
						}
					}
				}
			} else {
				if ('yes' === $this->debug) {
					$this->log->add($this->id, 'Error: Order Key does not match with PagSeguro reference.');
				}
			}
		}
		return;
	}

	return;
}

/* ========================================================
 * Integrations with WebmaniaBR
 * ======================================================== */

// Fazer com que os pedidos do POS sejam vistos pelo WebmaniaBR
//add_action ('woocommerce_order_status_pending_to_completed', 'orquidario_woocommerce_order_status_pending_to_completed', 10, 1);
function orquidario_woocommerce_order_status_pending_to_completed($id)
{
   $order = wc_get_order($id);
   if ($order->get_created_via() == 'POS') {
	   if (class_exists('WooCommerceNFe')) {
		   WooCommerceNFe::instance()->emitirNFeAutomaticamenteOnStatusChange($id);
	   }
   }
}

// TODO: verificar quando, mesmo quando não solicitado pelo Cashier, se decide forçar a emissão ou não.
// NOTE: aqui vai dar override se a emissão automática estiver desabilitada
// NOTE: por exemplo, se for NFC-e e for no cartão, tem que emitir em TODOS os casos
add_filter('webmaniabr_emissao_automatica', 'orquidario_webmaniabr_emissao_automatica', 10, 3);
function orquidario_webmaniabr_emissao_automatica($force, $option, $post_id)
{
   if (!$force && get_post_type( $order_id ) == 'shop_order') {
	 $order = wc_get_order($post_id);
	 $register_id = get_post_meta($post_id, 'wc_pos_id_register', true);
	 $register = WC_Pos_Registers::instance()->get_register_name_by_id($register_id);
	 if (get_post_meta($post_id, 'wc_pos_order_type', true) !== '') {
		 $data = get_post_meta($post_id, 'card_payment_data', true);
		 if ($data)
		  return true;
	 }
   }
   return $force;
}

/*

Possíveis coisas:
1. Venda no orquidário - NFC-e - Presencial
2. Venda para entrega (aluguel ou outro) - NF-e - Não Presencial
3. Venda no shopping - NFC-e - Presencial fora
4. Venda direto pela internet
*/

// TODO: a condição para ser NFC-e tem que ser baseada na caixa que está sendo usado
// NOTE: se retornar algo sem ser 'nfe' ou 'nfce' não vai emitir a nota
add_filter('webmaniabr_modelo_nota', 'orquidario_webmaniabr_modelo_nota', 10, 2);
function orquidario_webmaniabr_modelo_nota($tipo, $post_id)
{
	$order = wc_get_order($post_id);
	$register_id = get_post_meta($post_id, 'wc_pos_id_register', true);
	$register = WC_Pos_Registers::instance()->get_register_name_by_id($register_id);
	if (get_post_meta($post_id, 'wc_pos_order_type', true) !== '') {
		$tipo = 'nfce';
	}
	return $tipo;
}

// TODO: a condição para a modalidade de frete é baseada em que? Se for NFC-e apenas?
//add_filter('webmaniabr_pedido_modalidade_frete', 'orquidario_webmaniabr_pedido_modalidade_frete', 10, 5);
function orquidario_webmaniabr_pedido_modalidade_frete($codigo, $modalidade, $modelo, $post_id, $order)
{
	if ((!$modalidade || $modalidade == 'null' || $modalidade = '') && $modelo == 2) {
		$codigo = 9;
	}
	return $codigo;
}

 // TODO: Na verdade tem que ver qual o caixa que está sendo usado.
add_filter('webmaniabr_pedido_presenca', 'orquidario_webmaniabr_pedido_presenca', 10, 4);
function orquidario_webmaniabr_pedido_presenca($presenca, $post_id, $modelo, $order)
{
	if ($order->get_user() === false && $modelo == 2 && get_post_meta($post_id, 'wc_pos_order_type', true) !== '') {
		$presenca = 1;
	}
	return $presenca;
}

// TODO: diferenciar à vista de à prazo?
//add_filter('webmaniabr_pedido_pagamento', 'orquidario_webmaniabr_pedido_pagamento', 10, 3 );
function orquidario_webmaniabr_pedido_pagamento($pagamento, $post_id, $order)
{
	return $pagamento;
}

//add_filter('webmaniabr_pedido_tipo_integracao', 'orquidario_webmaniabr_pedido_tipo_integracao' )
function orquidario_webmaniabr_pedido_tipo_integracao($tipo, $post_id, $order)
{
	return $tipo;
}

add_filter('webmaniabr_pedido_valor_pagamento', 'orquidario_webmaniabr_pedido_valor_pagamento', 10, 3);
function orquidario_webmaniabr_pedido_valor_pagamento($valor, $post_id, $order)
{
	$amt = get_post_meta($post_id, 'wc_pos_amount_pay', true);
	return $amt !== '' ? (float) $amt : $valor;
}

//add_filter('webmaniabr_pedido_cnpj_credenciadora', 'orquidario_webmaniabr_pedido_cnpj_credenciadora', 10, 3 );
function orquidario_webmaniabr_pedido_cnpj_credenciadora($cnpj, $post_id, $order)
{
	return $cnpj;
}

add_filter('webmaniabr_pedido_bandeira', 'orquidario_webmaniabr_pedido_bandeira', 10, 3);
function orquidario_webmaniabr_pedido_bandeira($bandeira, $post_id, $order)
{
	$data = get_post_meta($post_id, 'card_payment_data', true);
	if (isset($data['cardBrand'])) {
		if (stripos($data['cardBrand'], 'VISA') !== false) {
			return '01';
		} elseif (stripos($data['cardBrand'], 'MASTER') !== false) {
			return '02';
		} elseif (stripos($data['cardBrand'], 'ELECTRON') !== false) {
			return '01';
		} elseif (stripos($data['cardBrand'], 'MAESTRO') !== false) {
			return '02';
		} elseif (stripos($data['cardBrand'], 'ELO') !== false) {
			return '06';
		} elseif (stripos($data['cardBrand'], 'HIPERCARD') !== false) {
			return '07';
		} elseif (stripos($data['cardBrand'], 'AMEX') !== false) {
			return '03';
		} elseif (stripos($data['cardBrand'], 'AMERICAN') !== false) {
			return '03';
		} elseif (stripos($data['cardBrand'], 'SOROCRED') !== false) {
			return '04';
		} elseif (stripos($data['cardBrand'], 'CLUB') !== false) {
			return '05';
		} elseif (stripos($data['cardBrand'], 'DINER') !== false) {
			return '05';
		} elseif (stripos($data['cardBrand'], 'AURA') !== false) {
			return '08';
		} elseif (stripos($data['cardBrand'], 'CABAL') !== false) {
			return '09';
		} else {
			return '99';
		}
	}
	return $bandeira;
}

add_filter('webmaniabr_pedido_autorizacao', 'orquidario_webmaniabr_pedido_autorizacao', 10, 3);
function orquidario_webmaniabr_pedido_autorizacao($autorizacao, $post_id, $order)
{
	$data = get_post_meta($post_id, 'card_payment_data', true);
	if (isset($data['hostNsu'])) {
		return $data['hostNsu'];
	}
	return $autorizacao;
}

add_filter('option_wc_settings_woocommercenfe_payment_methods', 'orquidario_wc_settings_woocommercenfe_payment_methods', 10, 2);
function orquidario_wc_settings_woocommercenfe_payment_methods($value, $option)
{
	/*$value[get_option('pos_chip_pin_name')] = 03;
	$value[get_option('pos_chip_pin2_name')] = 04;
	$value[get_option('pos_chip_pin3_name')] = 99;*/
	$value['pos_chip_pin'] = 03;
	$value['pos_chip_pin2'] = 04;
	$value['pos_chip_pin3'] = 99;
	$value['cod'] = 01;
	return $value;
}
/*add_filter('woocommerce_available_payment_gateways', 'orquidario_woocommerce_available_payment_gateways', 10, 1);
function orquidario_woocommerce_available_payment_gateways($gateways) {
	//print_r($gateways);
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if($screen &&  $screen->id === 'woocommerce_page_wc-settings'){
		global $wp;
		$qrl = add_query_arg( $wp->query_vars, home_url() );
		if (strpos($qrl, 'woocommercenfe_tab') > 0) {
			$dummy = new WC_Gateway_COD();
			$gateways['pos_chip_pin'] = $dummy;
		}
	}
	return $gateways;
}*/


/* ========================================================
 * Resize author's avatar
 * ======================================================== */
add_filter("zn_author_bio_avatar_size", "change_author_avatar_size");
function change_author_avatar_size()
{
	return 70;
}

/* ========================================================
 * Allow custom MIME types
 * ======================================================== */
add_filter('upload_mimes', 'my_myme_types', 1, 1);
function my_myme_types($mime_types)
{
	$mime_types['csv'] = 'text/csv';     // Adding .csv extension

	return $mime_types;
}

/* ========================================================
 * Reorder Checkout fields
 * ======================================================== */
add_filter("woocommerce_checkout_fields", "orquidario_override_checkout_fields", 1);
function orquidario_override_checkout_fields($fields)
{
	$fields['billing']['billing_first_name']['priority'] = 10;
	$fields['billing']['billing_last_name']['priority'] = 20;
	$fields['billing']['billing_company']['priority'] = 30;
	$fields['billing']['billing_country']['priority'] = 40;
	foreach ($fields['billing']['billing_country']['class'] as &$str) {
		$str = str_replace('form-row-wide', 'form-row-first', $str);
	}

	$fields['billing']['billing_state']['priority'] = 50;
	foreach ($fields['billing']['billing_state']['class'] as &$str) {
		$str = str_replace('form-row-wide', 'form-row-last', $str);
	}

	$fields['billing']['billing_address_1']['priority'] = 60;
	$fields['billing']['billing_address_1']['placeholder'] = '';
	foreach ($fields['billing']['billing_address_1']['class'] as &$str) {
		$str = str_replace('form-row-last', 'form-row-wide', $str);
		$str = str_replace('form-row-first', 'form-row-wide', $str);
	}

	$fields['billing']['billing_number']['priority'] = 61;
	$fields['billing']['billing_address_2']['priority'] = 62;
	$fields['billing']['billing_address_2']['placeholder'] = '';

	$fields['billing']['billing_city']['priority'] = 70;

	$fields['billing']['billing_neighborhood']['priority'] = 80;
	foreach ($fields['billing']['billing_neighborhood']['class'] as &$str) {
		$str = str_replace('form-row-last', 'form-row-first', $str);
	}

	$fields['billing']['billing_postcode']['priority'] = 90;
	$fields['billing']['billing_email']['priority'] = 100;
	$fields['billing']['billing_phone']['priority'] = 110;


	// Shipping

	$fields['shipping']['shipping_first_name']['priority'] = 10;
	$fields['shipping']['shipping_last_name']['priority'] = 20;
	$fields['shipping']['shipping_company']['priority'] = 30;
	$fields['shipping']['shipping_country']['priority'] = 40;
	foreach ($fields['shipping']['shipping_country']['class'] as &$str) {
		$str = str_replace('form-row-wide', 'form-row-first', $str);
	}

	$fields['shipping']['shipping_state']['priority'] = 50;
	foreach ($fields['shipping']['shipping_state']['class'] as &$str) {
		$str = str_replace('form-row-wide', 'form-row-last', $str);
	}

	$fields['shipping']['shipping_address_1']['priority'] = 60;
	$fields['shipping']['shipping_address_1']['placeholder'] = '';
	foreach ($fields['shipping']['shipping_address_1']['class'] as &$str) {
		$str = str_replace('form-row-last', 'form-row-wide', $str);
		$str = str_replace('form-row-first', 'form-row-wide', $str);
	}

	$fields['shipping']['shipping_number']['priority'] = 61;
	$fields['shipping']['shipping_address_2']['priority'] = 62;
	$fields['shipping']['shipping_address_2']['placeholder'] = '';

	$fields['shipping']['shipping_city']['priority'] = 70;

	$fields['shipping']['shipping_neighborhood']['priority'] = 80;
	foreach ($fields['shipping']['shipping_neighborhood']['class'] as &$str) {
		$str = str_replace('form-row-last', 'form-row-first', $str);
	}

	$fields['shipping']['shipping_postcode']['priority'] = 90;

	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				$fields['billing']['billing_number']['priority'] = 65;
				$fields['billing']['billing_address_2']['priority'] = 61;

				foreach ($fields['billing']['billing_address_2']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-wide', $str);
					$str = str_replace('form-row-first', 'form-row-wide', $str);
				}

				foreach ($fields['billing']['billing_email']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-wide', $str);
					$str = str_replace('form-row-first', 'form-row-wide', $str);
				}

				foreach ($fields['billing']['billing_cellphone']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-first', $str);
				}

				foreach ($fields['billing']['billing_phone']['class'] as &$str) {
					$str = str_replace('form-row-first', 'form-row-last', $str);
				}

				foreach ($fields['billing']['billing_postcode']['class'] as &$str) {
					$str = str_replace('form-row-first', 'form-row-last', $str);
				}
			}
		} catch (Error $e) {
		}
	}

	return $fields;
}

/* ========================================================
 * Allow reseller coupons to be used
 * ======================================================== */
add_filter('woocommerce_coupon_is_valid', 'woocommerce_coupon_is_valid', 10, 3);
add_filter('woocommerce_coupon_is_valid_for_cart', 'woocommerce_coupon_is_valid_for_cart', 10, 2);
add_filter('woocommerce_coupon_is_valid_for_product', 'woocommerce_coupon_is_valid_for_product', 10, 4);

function woocommerce_coupon_is_valid($valid, $coupon, $discount)
{
	if (strpos($coupon->get_code(), 'autorevenda') === 0) {
		return 109;
	}
	return $valid;
}

function woocommerce_coupon_is_valid_for_cart($valid, $coupon)
{
	if (strpos($coupon->get_code(), 'autorevenda') === 0 && !is_null(WC()->cart)) {
		return false;
	}
	return $valid;
}

function woocommerce_coupon_is_valid_for_product($valid, $product, $coupon, $values)
{
	if (strpos($coupon->get_code(), 'autorevenda') === 0 && !is_null(WC()->cart)) {
		return false;
	}
	return $valid;
}

/* ========================================================
 * Put custom JS/CSS in POS screen
 * ======================================================== */
add_action('admin_print_footer_scripts', 'wc_poster_footer_child', 0);
function wc_poster_footer_child()
{
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				wp_enqueue_style("custom_modifications-css", get_stylesheet_directory_uri() . "/css/wc_pos.css", null, null, null);
				wp_enqueue_script("custom_modifications-js", get_stylesheet_directory_uri() . "/js/jsrsasign-all-min.js", null, null, null);
				wp_enqueue_script("custom_modifications-js", get_stylesheet_directory_uri() . "/js/wc_pos.js", null, null, null);
				wp_add_inline_script("custom_modifications-js", @file_get_contents(ABSPATH . "../private/keys.js"), 'before' );
			}
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error($e->__toString(), array( 'source' => 'custom modification' ));
		}
	}
}

/* ========================================================
 * Remove obrigatory fields for POS area
 * Re-order them a bit as well
 * ======================================================== */
add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
add_filter('woocommerce_form_field_args', 'custom_override_checkout_fields2', 10, 3);

function custom_override_checkout_fields($fields)
{
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				$fields['account']['account_password']['required'] = false;
			}
			$fields['billing']['billing_address_1']['required'] = false;
			$fields['billing']['billing_country']['required'] = false;
			$fields['billing']['billing_state']['required'] = false;
			$fields['billing']['billing_city']['required'] = false;
			$fields['billing']['billing_phone']['required'] = false;
			$fields['billing']['billing_postcode']['required'] = false;
			$fields['billing']['billing_email']['required'] = false;
			$fields['billing']['billing_phone']['priority'] = 21;
			$fields['billing']['billing_cellphone']['priority'] = 21;
			$fields['billing']['billing_email']['priority'] = 21;
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error($e->__toString(), array( 'source' => 'custom modification' ));
		}
	}
	return $fields;
}

function custom_override_checkout_fields2($args, $key, $value)
{
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				if ($key == 'billing_password_confirm') {
					$args['required'] = false;
				}
			}
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error($e->__toString(), array( 'source' => 'custom modification' ));
		}
	}

	return $args;
}

/* ========================================================
 * Adds the current user role to the administration backend
 * ======================================================== */
add_filter('admin_body_class', function ($classes) {
	$roles = wp_get_current_user()->roles;
	$imp = ' ' . implode($roles, ' ') . ' ';
	return $classes . $imp;
});

/* ========================================================
 * Add the ability to Registrations for Woocommerce to get a Phone per participant
 * ======================================================== */
function registrations_display_participant_fields($checkout, $current_participant)
{
	woocommerce_form_field(
		'participant_phone_' . $current_participant,
		array(
		'type'          => 'text',
		'class'         => array('participant-phone form-row-wide'),
		'label'         => __('WhatsApp / Telefone', 'my-theme-textdomain'),
		'placeholder'   => __('ex: (71) 98876-5137', 'my-theme-textdomain'),
	),
		$checkout->get_value('participant_phone_' . $current_participant)
	);
}
add_action('registrations_display_participant_fields', 'registrations_display_participant_fields', 10, 2);

function registrations_custom_checkout_fields_meta_value($participant, $count)
{
	if (! empty($_POST['participant_phone_' . $count ]) &&  ! empty($participant)) {
		$participant['phone'] = sanitize_text_field($_POST['participant_phone_' . $count]);
	}
	return $participant;
}
add_filter('registrations_checkout_fields_order_meta_value', 'registrations_custom_checkout_fields_meta_value', 10, 2);

function registrations_admin_display_participant_fields($participant)
{
	echo sprintf(__('Telefone: %s', 'twentyseventeen'), $participant['phone']);
}
add_action('registrations_admin_order_meta_participant_fields', 'registrations_admin_display_participant_fields', 10, 1);

/* ========================================================
 * Restores the ability to add to cart on CURSO for online shopping
 * ======================================================== */
add_action('template_redirect', 'restore_registrations', 50);
function restore_registrations()
{
	if (class_exists('WooCommerce') && is_product()) {
		global $post;
		$terms = wp_get_post_terms($post->ID, 'product_cat');
		foreach ($terms as $term) {
			$categories[] = $term->slug;
		}
		if (in_array('curso', $categories)) {
			add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
			add_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
		}
	}
}

/* ========================================================
 * Modify login on the front-end to enable translation
 * ======================================================== */
add_action('zn_head__top_right', 'replace_zn_login_text_with_woocommerce');

function replace_zn_login_text_with_woocommerce()
{
	remove_action('zn_head__top_right', 'zn_login_text', 40);
	add_action('zn_head__top_right', 'custom_zn_login_text', 40);
}

if (! function_exists('custom_zn_login_text')) {
	/**
	 * Login Form - Login/logout text
	 * @hooked to zn_head_right_area
	 * @see functions.php
	 */
	function custom_zn_login_text()
	{

		// CHECK IF OPTION IS ENABLED
		if (zget_option('head_show_login', 'general_options', false, 1) == 1) {
			if (is_user_logged_in()) {
				echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a class="topnav-item" href="' . wp_logout_url(home_url('/')) . '">';
				echo '<i class="glyphicon glyphicon-log-out visible-xs xs-icon"></i>';
				echo '<span class="hidden-xs">' . __("LOGOUT", 'zn_framework') . '</span>';
				echo '</a></li></ul>';

				if (class_exists('WooCommerce')) {
					echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a class="topnav-item woocommerce-my-account-link" href="' . get_permalink(get_option('woocommerce_myaccount_page_id')) . '">';
					echo '<span class="hidden-xs">' . __("My Account", 'woocommerce') . '</span>';
					echo '</a></li></ul>';
				}

				return;
			}
			echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a href="#login_panel" class="kl-login-box topnav-item">';
			echo '<i class="glyphicon glyphicon-log-in visible-xs xs-icon"></i>';
			echo '<span class="hidden-xs">'. __("LOGIN", 'zn_framework') . '</span>';
			echo '</a></li></ul>';
		}
	}
}

/* ========================================================
 * Permissions management
 * ======================================================== */

add_action('admin_init', 'disallowed_admin_pages');
 function disallowed_admin_pages()
 {
	 global $pagenow;

	 # Check current admin page.
	 if (!current_user_can('publish_shop_orders') && $pagenow == 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
		 wp_redirect(admin_url('/edit.php?post_type=shop_order'), 301);
		 exit;
	 }
 }

add_action('admin_head', 'custom_hide_options');
function custom_hide_options()
{
	global $pagenow;


	// HIDE "New Order" button when current user don't have 'manage_options' admin user role capability
	if (! current_user_can('publish_shop_orders')):
	?>
<style>
  .post-type-shop_order #wpbody-content a.page-title-action,
  a[href="post-new.php?post_type=shop_order"],
  #wpadminbar .menupop a[href$="post-new.php?post_type=shop_order"] {
    display: none !important;
  }
</style>
<?php

	  if ($pagenow == 'post.php' && isset($_GET['action']) && $_GET['action'] == 'edit'):
	  ?>
<style>
  #woocommerce-order-downloads,
  #postcustom,
  #woocommernfe_transporte,
  .edit_address,
  label[for="order_status"],
  .add-items .add-line-item,
  .add-items .add-coupon,
  #woocommerce-order-actions .order_actions #actions {
    display: none !important;
  }
</style>
<?php
	  endif; ?>
<?php

	endif;
}

add_action('admin_footer', 'custom_hide_options2');
function custom_hide_options2()
{
	global $pagenow;


	// HIDE "New Order" button when current user don't have 'manage_options' admin user role capability
	if (! current_user_can('publish_shop_orders') && $pagenow == 'post.php' && isset($_GET['action']) && $_GET['action'] == 'edit'):
  ?>
<script>
  var jQueryTimer = setInterval(function() {
    if (window.jQuery) {
      clearInterval(jQueryTimer);
      // Disabling the input fields bugs out when updating, reseting the Payment Status to Pending Payment
      /*jQuery('.panel-wrap.woocommerce select').prop('disabled', true);
      jQuery('.panel-wrap.woocommerce input').prop('disabled', true);
      jQuery('.order_actions select').prop('disabled', true);*/
    }
  }, 100);
</script>
<?php
  endif;
}

/* ========================================================
 * Supress admin notices for WooCommerce
 * ======================================================== */
//add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );

/* ========================================================
 * Add a humans.txt link to the front-end
 * ======================================================== */
add_action("wp_head", "author_tag");
function author_tag($match)
{
	echo '<link type="text/plain" rel="author" href="http://www.orquidariobahia.com.br/humans.txt" />';
}

/* ========================================================
 * Front-end CSS and JS enqueue
 * ======================================================== */
add_action('wp_enqueue_scripts', 'kl_child_scripts', 11);
function kl_child_scripts()
{
	wp_deregister_style('kallyas-styles');
	wp_register_script("atlantida", "https://www.atlantidastudios.com/link/link.js", "jquery", null, null);

	wp_enqueue_style('kallyas-styles', get_template_directory_uri().'/style.css', '', ZN_FW_VERSION);
	wp_enqueue_style('kallyas-child', get_stylesheet_uri(), array('kallyas-styles'), ZN_FW_VERSION);
	//wp_enqueue_script ( "atlantida" );
}

/* ========================================================
 * Admin area enqueue
 * ======================================================== */
function kl_child_admin_scripts()
{
	// Load only on ?page=mypluginname
	/*if($hook != 'toplevel_page_mypluginname') {
			return;
	}*/
	if (is_admin()) {
		wp_register_style('custom_child_admin_css', get_stylesheet_directory_uri() . '/css/admin.css', false, '1.0.0');
		wp_enqueue_style('custom_child_admin_css');
	}
}
add_action('admin_enqueue_scripts', 'kl_child_admin_scripts');

/* ========================================================
 * Unkown
 * ======================================================== */
function kallyas_parse_options($admin_options)
{
	foreach ($admin_options as $key => $item) {
		if (is_array($item)) {
			if (($item['slug'] == 'nav_options') && ($item['id'] == 'header_res_width') && ($item['parent'] == 'general_options')) {
				$admin_options[$key]['helpers']['max'] = '2000';
			}
		}
	}

	return $admin_options;
}

add_filter('zn_theme_options', 'kallyas_parse_options');

function zn_resmenu_wrapper()
{
	?>
<div class="zn-res-menuwrapper">
  <a href="#" class="zn-res-trigger hide-bars">MENU </a>
  <a href="#" class="zn-res-trigger zn-header-icon"></a>
</div><!-- end responsive menu -->
<?php
}

/* ========================================================
 * Load child theme's textdomain.
 * ======================================================== */
/*function kallyasChildLoadTextDomain(){
   load_child_theme_textdomain( 'zn_framework', get_stylesheet_directory().'/languages' );
}
add_action( 'after_setup_theme', 'kallyasChildLoadTextDomain' );*/

/* ========================================================
 * Remove related procuts for WooCommerce
 * This prevent loading on the backend and having any actual HTML code showing up on the front-end
 * ======================================================== */
function wc_remove_related_products($args)
{
	return array();
}
add_filter('woocommerce_related_products_args', 'wc_remove_related_products', 10);

/* ========================================================
 * Removes the white bar from kallyas that wraps the related products
 * ======================================================== */
function remove_kallyas_white_bar($args)
{
	remove_action('woocommerce_after_single_product_summary', 'zn_wrap_prodpage_rel_upsells', 14);
	remove_action('woocommerce_after_single_product_summary', 'zn_close_wrappings', 21);
}
add_action('woocommerce_after_single_product_summary', 'remove_kallyas_white_bar', 5);

/* ========================================================
 * Changes WooCommerce default state
 * ======================================================== */
function change_default_checkout_state()
{
	return "BA"; // state code
}
add_filter('default_checkout_state', 'change_default_checkout_state');

/* ========================================================
 * Remove unused/invalid/impossible states
 * ======================================================== */
function custom_woocommerce_states($states)
{

  /*$states['BR'] = array(
	'BA' => 'Bahia',
  );*/

	return $states;
}
add_filter('woocommerce_states', 'custom_woocommerce_states');

/* ========================================================
 * Inline HTML code in the Header
 * ======================================================== */
//add_action('wp_head', 'KallyasChild_loadHeadScript' );
function KallyasChild_loadHeadScript()
{
	echo '
	<script type="text/javascript">

	// Your JS code here

	</script>';
}

/* ========================================================
 * Inline HTML code in the Footer.
 * ======================================================== */
function KallyasChild_loadFooterScript()
{
	echo '
	<script type="text/javascript">

	// Your JS code here

	</script>';
}
