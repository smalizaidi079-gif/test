<?php
/**
 * TEST ONLY: simulates PayPal sandbox REST responses (PayPal hosts are not
 * reachable from this test environment). Not part of the plugin.
 */
function mockpp_state() { $s = get_option('mockpp', array()); return is_array($s) ? $s + array('orders'=>array(),'captures'=>array(),'req'=>array(),'capture_calls'=>0,'create_calls'=>0) : array(); }
function mockpp_save($s) { update_option('mockpp', $s, false); }
function mockpp_resp($code, $body) { return array('headers'=>array('paypal-debug-id'=>'dbg123'),'body'=>wp_json_encode($body),'response'=>array('code'=>$code,'message'=>''),'cookies'=>array(),'filename'=>null); }

add_filter('pre_http_request', function ($pre, $args, $url) {
	if (strpos($url, 'https://api-m.sandbox.paypal.com') !== 0) return $pre;
	$path = substr($url, strlen('https://api-m.sandbox.paypal.com'));
	$m = $args['method'] ?? 'GET';
	$h = array_change_key_case($args['headers'] ?? array());
	$s = mockpp_state();
	if ($path === '/v1/oauth2/token') {
		if (($h['authorization'] ?? '') !== 'Basic '.base64_encode('test-client:test-secret')) return mockpp_resp(401, array('error'=>'invalid_client','error_description'=>'Client Authentication failed'));
		return mockpp_resp(200, array('access_token'=>'A21TOKEN','expires_in'=>32400));
	}
	if (($h['authorization'] ?? '') !== 'Bearer A21TOKEN') return mockpp_resp(401, array('name'=>'AUTHENTICATION_FAILURE'));

	if ($path === '/v2/checkout/orders' && $m === 'POST') {
		$rid = $h['paypal-request-id'] ?? '';
		if ($rid && isset($s['req'][$rid])) return mockpp_resp(200, $s['orders'][$s['req'][$rid]]);
		$b = json_decode($args['body'], true);
		foreach ($s['orders'] as $o) if ($o['purchase_units'][0]['invoice_id'] === $b['purchase_units'][0]['invoice_id']) return mockpp_resp(422, array('name'=>'UNPROCESSABLE_ENTITY','details'=>array(array('issue'=>'DUPLICATE_INVOICE_ID'))));
		$id = strtoupper(substr(md5(uniqid('',true)),0,17));
		$o = array('id'=>$id,'status'=>'CREATED','intent'=>'CAPTURE','purchase_units'=>$b['purchase_units']);
		if (isset($b['payment_source'])) $o['payment_source'] = $b['payment_source'];
		$s['orders'][$id] = $o; if ($rid) $s['req'][$rid] = $id; $s['create_calls']++;
		mockpp_save($s);
		return mockpp_resp(201, $o);
	}
	if (preg_match('#^/v2/checkout/orders/([A-Z0-9]+)$#', $path, $mm) && $m === 'GET') {
		return isset($s['orders'][$mm[1]]) ? mockpp_resp(200, $s['orders'][$mm[1]]) : mockpp_resp(404, array('name'=>'RESOURCE_NOT_FOUND'));
	}
	if (preg_match('#^/v2/checkout/orders/([A-Z0-9]+)/capture$#', $path, $mm) && $m === 'POST') {
		$s['capture_calls']++;
		$id = $mm[1];
		if (!isset($s['orders'][$id])) { mockpp_save($s); return mockpp_resp(404, array('name'=>'RESOURCE_NOT_FOUND')); }
		$o = &$s['orders'][$id];
		if ($o['status'] === 'COMPLETED') { mockpp_save($s); return mockpp_resp(422, array('name'=>'UNPROCESSABLE_ENTITY','details'=>array(array('issue'=>'ORDER_ALREADY_CAPTURED')))); }
		if ($o['status'] !== 'APPROVED') { mockpp_save($s); return mockpp_resp(422, array('name'=>'UNPROCESSABLE_ENTITY','details'=>array(array('issue'=>'ORDER_NOT_APPROVED')))); }
		$amt = $o['purchase_units'][0]['amount'];
		if ($amt['value'] === '13.13') { mockpp_save($s); return mockpp_resp(422, array('name'=>'UNPROCESSABLE_ENTITY','details'=>array(array('issue'=>'INSTRUMENT_DECLINED')))); }
		$cid = 'CAP'.strtoupper(substr(md5($id),0,14));
		$cap = array('id'=>$cid,'status'=> $amt['value']==='14.14' ? 'PENDING' : 'COMPLETED','amount'=>$amt,'custom_id'=>$o['purchase_units'][0]['custom_id'],'supplementary_data'=>array('related_ids'=>array('order_id'=>$id)));
		if ($cap['status']==='PENDING') $cap['status_details']=array('reason'=>'PENDING_REVIEW');
		$o['status']='COMPLETED'; $o['purchase_units'][0]['payments']=array('captures'=>array($cap));
		$o['payment_source'] = $o['payment_source_override'] ?? array('paypal'=>array('email_address'=>'buyer@example.com'));
		$s['captures'][$cid] = $cap; unset($o);
		mockpp_save($s);
		return mockpp_resp(201, $s['orders'][$id]);
	}
	if (preg_match('#^/v2/payments/captures/([A-Z0-9]+)$#', $path, $mm)) {
		return isset($s['captures'][$mm[1]]) ? mockpp_resp(200, $s['captures'][$mm[1]]) : mockpp_resp(404, array('name'=>'RESOURCE_NOT_FOUND'));
	}
	if ($path === '/v1/notifications/verify-webhook-signature') {
		$b = json_decode($args['body'], true);
		$ok = is_array($b) && ($b['transmission_sig'] ?? '') === 'valid-sig' && ($b['webhook_id'] ?? '') === 'WH-TEST' && is_array($b['webhook_event'] ?? null);
		return mockpp_resp(200, array('verification_status'=> $ok ? 'SUCCESS' : 'FAILURE'));
	}
	if (preg_match('#^/v1/notifications/webhooks/(.+)$#', $path, $mm)) {
		return $mm[1]==='WH-TEST' ? mockpp_resp(200, array('id'=>'WH-TEST','url'=>rest_url('phenix-payments/v1/webhook'))) : mockpp_resp(404, array('name'=>'INVALID_RESOURCE_ID'));
	}
	return mockpp_resp(404, array('name'=>'NOT_MOCKED','path'=>$path));
}, 10, 3);

// Test helper: simulate the buyer approving an order in the PayPal popup.
function mockpp_approve($order_id, $source = null) { $s = mockpp_state(); $s['orders'][$order_id]['status']='APPROVED'; if ($source) $s['orders'][$order_id]['payment_source_override']=$source; mockpp_save($s); }
function mockpp_set_capture_status($cid, $status) { $s = mockpp_state(); $s['captures'][$cid]['status']=$status; mockpp_save($s); }

// TEST ONLY route so the stubbed SDK can simulate buyer approval.
add_action('rest_api_init', function () {
	register_rest_route('mockpp/v1', '/approve/(?P<id>[A-Z0-9]+)', array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){ mockpp_approve($r['id']); return array('ok'=>true); }));
});

// TEST ONLY: server-side fetch of the JS SDK URL.
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (strpos($url, 'https://www.paypal.com/sdk/js') !== 0) return $pre;
	parse_str(parse_url($url, PHP_URL_QUERY), $q);
	if (($q['client-id'] ?? '') !== 'test-client') {
		$body = "/* error */ throw new Error(\"SDK Validation error: 'Invalid query value for client-id: " . ($q['client-id'] ?? '') . "'\");";
		return array('headers'=>array(),'body'=>$body,'response'=>array('code'=>400,'message'=>''),'cookies'=>array(),'filename'=>null);
	}
	return array('headers'=>array(),'body'=>'/* paypal sdk */','response'=>array('code'=>200,'message'=>''),'cookies'=>array(),'filename'=>null);
}, 9, 3);
