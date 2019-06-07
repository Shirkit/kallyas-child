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

	// Views
	$('#modal-order_discount #coupon_tab .wrap-button').before('<div class="wrap-button wrap-custom-button-discount" ><button class="button button-primary wp-button-large alignright" type="button" name="apply_coupon_revenda" id="apply_coupon_revenda_btn">Revendedor</button></div>');

	// Logic
  var functions = {};

  var apply_coupon_revenda = function() {
	APP.db.values('coupons').done(function(obs) {
		for (var i = 0; i < obs.length; i++) {
			if (obs[i].code.startsWith('autorevenda')) {
				CART.add_discount(obs[i].code);
			}
		}
	});
  };
  
  var is_valid = function() {
    return true;
  };

  $('#apply_coupon_revenda_btn').click(function(e) {
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
    if (code && code.indexOf('autorevenda_') != -1) {
      coupon.is_valid = is_valid;
    }
    return coupon;
  };

  functions.set_default_data = CUSTOMER.set_default_data;
  functions.WC_Coupon = WC_Coupon;

  APP.search_customer_wc_3 = search_customer_wc_3;
  CUSTOMER.set_default_data = set_default_data;
  WC_Coupon = My_WC_Coupon;
});