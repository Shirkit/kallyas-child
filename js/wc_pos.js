function fullNameHook(result, text) {
  var role = result.role;
  if (role == 'customer') {
    return text += ' { Cliente }';
  } else if (role == 'reseller') {
    return text += ' { Revendedor }';
  } else if (role == 'costumer_engaged') {
    return text += ' { Orquidófilo }';
  }
  return text;
}

jQuery(document).ready(function($) {

  /*$.fn.bindFirst = function(name, fn) {
    var elem, handlers, i, _len;
    this.bind(name, fn);
    for (i = 0, _len = this.length; i < _len; i++) {
      elem = this[i];
      handlers = jQuery._data(elem).events[name.split('.')[0]];
      handlers.unshift(handlers.pop());
    }
  };*/

  // Fix wrong payment method being selected by app.js form POS
  jQuery("#modal-order_payment a.payment_methods").click(function() {
    jQuery('#modal-order_payment a.payment_methods input[type=radio].select_payment_method').attr('checked', false);
    jQuery('#modal-order_payment a.payment_methods input[type=radio].select_payment_method').each(el => this.checked = false);
    jQuery(this).find('input[type=radio]').attr('checked', true);
    jQuery(this).find('input[type=radio]')[0].checked = true;
  });

  function deferQz(method) {
    if (window.qz) {
      method();
    } else {
      setTimeout(function() {
        deferQz(method)
      }, 1000);
    }
  }

  // Actually sign messages using QZ and local certificates
  deferQz(function() {
    qz.security.setCertificatePromise(function(resolve, reject) {
      resolve(publicKey);
    });

    qz.security.setSignaturePromise(function(toSign) {
      return function(resolve, reject) {
        try {
          var pk = KEYUTIL.getKey(privateKey);
          var sig = new KJUR.crypto.Signature({
            "alg": "SHA1withRSA"
          });
          sig.init(pk);
          sig.updateString(toSign);
          var hex = sig.sign();
          resolve(stob64(hextorstr(hex)));
        } catch (err) {
          console.error(err);
          reject(err);
        }
      };
    });
  });

  // Logic
  var functions = {};
  var lastPaymentData = null;

  // Speed up the proccess of adding many coupons. This needs the CSS to remove ability to remove the coupon 'revendedor', as expected
  var apply_coupon_revenda = function() {
    if (!CART.coupons_enabled()) {
      return false;
    }
    if (!CART.has_discount('revendedor')) {
      APP.db.values('coupons').done(function(obs) {
        for (var i = 0; i < obs.length; i++) {
          if (obs[i].code.startsWith('autorevenda')) {
            var the_coupon = new WC_Coupon(obs[i].code, obs[i]);
            CART.applied_coupons.push(obs[i].code);
            CART.coupons[obs[i].code] = the_coupon;
          }
        }
      }).done(function(obs) {
        if (typeof CART.coupons['pos discount'] != 'undefined') {
          var pos_discount = clone(CART.coupons['pos discount']);
          delete CART.coupons['pos discount'];
          CART.coupons['pos discount'] = pos_discount;
        }
        CART.calculate_totals();
      });
    }
  };

  var is_valid = function() {
    return true;
  };

  $('#modal-order_discount #coupon_tab .wrap-custom-button-discount button[name=revendedor]').click(function(e) {
    apply_coupon_revenda();
    return false;
  });

  // APP
  var search_customer_wc_3 = function(query, callback) {
    $('#customer_search_result').html('');
    var term = query.term;
    var _term = '';
    if (term) {
      _term = POS_TRANSIENT.searching_term = term.toLowerCase();
    }
    var data = {
      results: []
    };
    var q = APP.db.from('customers').where('fullname', '^', _term);
    var limit = 10000;
    var result = [];
    var chk = {};
    q.list(limit).done(function(objs) {
      $.each(objs, function(index, val) {
        if (POS_TRANSIENT.searching_term !== _term) return false;
        var fullname = [val.first_name, val.last_name];
        fullname = fullname.join(' ').trim();
        if (fullname == '') {
          fullname = val.username;
        }
        fullname = fullNameHook(val, fullname);
        fullname += ' (' + val.email + ' / ' + ' ' + val.phone + ')';
        var data_pr = {
          id: val.id,
          text: fullname
        };
        chk[val.id] = fullname;
        result.push(data_pr);
        if (typeof callback == 'undefined') {
          fullname = fullname.replace(/(cows)/g, '<span class="smallcaps">$1</span>')
          APP.add_customer_item_to_result({
            id: val.id,
            avatar_url: val.avatar_url,
            fullname: fullname
          });
        }
      });
      var q_lastfirst = APP.db.from('customers').where('lastfirst', '^', _term);
      q_lastfirst.list(limit).done(function(objs) {
        $.each(objs, function(index, val) {
          if (POS_TRANSIENT.searching_term !== _term) return false;
          if (typeof chk[val.id] == 'undefined') {
            var fullname = [val.first_name, val.last_name];
            fullname = fullname.join(' ').trim();
            if (fullname == '') {
              fullname = val.username;
            }
            fullname = fullNameHook(val, fullname);
            fullname += ' (' + val.email + ' / ' + ' ' + val.phone + ')';
            var data_pr = {
              id: val.id,
              text: fullname
            };
            chk[val.id] = fullname;
            result.push(data_pr);
            if (typeof callback == 'undefined') {
              APP.add_customer_item_to_result({
                id: val.id,
                avatar_url: val.avatar_url,
                fullname: fullname
              });
            }
          }
        });
        var qq = APP.db.from('customers').where('email', '^', _term);
        qq.list(limit).done(function(objs) {
          var i = 0;
          $.each(objs, function(index, val) {
            if (POS_TRANSIENT.searching_term !== _term) return false;
            if (typeof chk[val.id] == 'undefined') {
              var fullname = [val.first_name, val.last_name]
              var fullname = fullname.join(' ').trim();
              if (fullname == '') {
                fullname = val.username;
              }
              fullname = fullNameHook(val, fullname);
              fullname += ' (' + val.email + ' / ' + ' ' + val.phone + ')';
              var data_pr = {
                id: val.id,
                text: fullname
              };
              chk[val.id] = fullname;
              result.push(data_pr);
              if (typeof callback == 'undefined') {
                APP.add_customer_item_to_result({
                  id: val.id,
                  avatar_url: val.avatar_url,
                  fullname: fullname
                });
              }
            }
          });

          var qqq = APP.db.from('customers').where('phone', '^', _term);
          qqq.list(limit).done(function(objs) {
            var i = 0;
            $.each(objs, function(index, val) {
              if (POS_TRANSIENT.searching_term !== _term) return false;
              if (typeof chk[val.id] == 'undefined') {
                var fullname = [val.first_name, val.last_name]
                var fullname = fullname.join(' ').trim();
                if (fullname == '') {
                  fullname = val.username;
                }
                fullname = fullNameHook(val, fullname);
                fullname += ' (' + val.email + ' / ' + ' ' + val.phone + ')';
                var data_pr = {
                  id: val.id,
                  text: fullname
                };
                result.push(data_pr);
                if (typeof callback == 'undefined') {
                  APP.add_customer_item_to_result({
                    id: val.id,
                    avatar_url: val.avatar_url,
                    fullname: fullname
                  });
                }
              }
            });
            data.results = result;
            if (typeof callback != 'undefined') {
              callback(data);
            }
          });
        });
      });
    });
  };

  // Customer
  var set_default_data = function(record) {
    functions.set_default_data.bind(CUSTOMER)(record);
    if (record) {
      if (record.role == 'reseller') {
        apply_coupon_revenda();
      } else if (record.role == 'costumer_engaged') {
        CART.add_custom_discount.bind(CART)(10, 'percent');
      }
    }
  };

  var My_WC_Coupon = function(code, data) {
    var coupon = new functions.WC_Coupon(code, data);
    if (code && code.indexOf('autorevenda') != -1) {
      coupon.is_valid = is_valid;
    }
    return coupon;
  };

  var validatePayment = function(method) {
    var valid = true;
    switch (method) {
      case 'pos_chip_pin2':
        if (jQuery("#pos_chip_pin2 #generate_order_id:visible").length) {
          APP.showNotice(pos_i18n[59], 'error');
          valid = false;
        }
        break;
      case 'pos_chip_pin3':
        if (jQuery("#pos_chip_pin3 #generate_order_id:visible").length) {
          APP.showNotice(pos_i18n[59], 'error');
          valid = false;
        }
        break;
    }
    return valid && functions.validatePayment(method);
  };

  var createOrder = function(paid, paymentSense) {
    CUSTOMER.additional_fields["card_payment_data"] = lastPaymentData;
    functions.createOrder(paid, paymentSense);
    lastPaymentData = null;
  };

  window.addEventListener('message', function(e) {
    if (e.origin == window.origin) {
      if (e.data.message == 'pay_ok') {
        lastPaymentData = e.data.data;
        // Caso a operação no cartão acabe antes de obter a resposta do número do pedido
        var nTimer = setInterval(function() {
          if (jQuery('#modal-order_payment form.woocommerce-checkout .popup_section:visible span').length > 0) {
            document.querySelector('#modal-order_payment .go_payment').click();
            clearInterval(nTimer);
          }
        }, 100);
      }
    }
  });

  // TODO: remove coupons when 'revendedor' is removed

  /*$('#edit_wc_pos_registers .span_clear_order_coupon').bindFirst('click', function(e) {
    var cc = $(this).closest('tr').data('coupon');
    if (cc == 'revendedor') {
      var removedAll = true;
      jQuery('#edit_wc_pos_registers .span_clear_order_coupon').each((i, el) => {
        var $row = $(this).closest('tr');
        var coupon_code = $row.data('coupon');
        if (CART.remove_coupon(coupon_code, true))
          $row.remove();
        else
          removedAll = false;
      });
      if (!removedAll)
        e.stopPropagation();
    }
  });*/

  functions.search_customer_wc_3 = APP.search_customer_wc_3;
  functions.set_default_data = CUSTOMER.set_default_data;
  functions.WC_Coupon = WC_Coupon;
  functions.validatePayment = ADDONS.validatePayment;
  functions.createOrder = APP.createOrder;

  APP.search_customer_wc_3 = search_customer_wc_3;
  APP.createOrder = createOrder;
  CUSTOMER.set_default_data = set_default_data;
  ADDONS.validatePayment = validatePayment;
  WC_Coupon = My_WC_Coupon;
});