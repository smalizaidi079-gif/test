<?php
$_SERVER['HTTP_HOST']='localhost:8899'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REMOTE_ADDR']='203.0.113.5';
require dirname(__DIR__).'/site/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
$pass=0; $fail=0;
function check($name, $cond, $extra='') { global $pass,$fail; if ($cond) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name $extra\n"; } }

// Reset state.
delete_option('mockpp');
$r = is_plugin_active("phenix-payments/phenix-payments.php") ? true : new WP_Error("x","not active");
check('plugin activates', !is_wp_error($r), is_wp_error($r)?$r->get_error_message():'');
global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}phenix_payments"); $wpdb->query("DELETE FROM {$wpdb->prefix}phenix_payment_events");
delete_option('phenix_pay_log');
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%phenix_pay_rl_%' OR option_name LIKE '%phenix_pay_tok_%'") as $o) delete_option($o);

// Settings through the real sanitize callback (as an admin).
wp_set_current_user(1);
require_once PHENIX_PAY_DIR.'includes/admin/class-admin-settings-page.php';
$page = new Phenix_Pay_Admin_Settings_Page();
$clean = $page->sanitize(array('environment'=>'sandbox','sandbox_client_id'=>'test-client','sandbox_client_secret'=>'test-secret','sandbox_webhook_id'=>'WH-TEST','currency'=>'USD','enable_card_button'=>1,'enable_card_fields'=>1,'allow_custom_amount'=>1,'min_amount'=>'1.00','max_amount'=>'10000','brand_name'=>'The Phenix LLC','accent_color'=>'#d9480f','success_message'=>'Thanks!','notify_admin'=>1,'send_receipt'=>1,'notify_email'=>'owner@example.com'));
update_option(Phenix_Pay_Settings::OPTION, $clean);
$raw = get_option(Phenix_Pay_Settings::OPTION);
check('secret stored encrypted (not plaintext)', strpos($raw['sandbox_client_secret'],'enc:v1:')===0 && strpos(serialize($raw),'test-secret')===false);
Phenix_Pay_Settings::update(array()); // reset cache (re-reads option)
check('secret decrypts', Phenix_Pay_Settings::credential('client_secret')==='test-secret');
$clean2 = $page->sanitize(array_merge($clean, array('sandbox_client_secret'=>'')));
check('blank secret keeps existing', $clean2['sandbox_client_secret']===$raw['sandbox_client_secret']);
wp_set_current_user(0);

// Mail capture.
$GLOBALS['mails']=array();
add_filter('pre_wp_mail', function($null,$atts){ $GLOBALS['mails'][]=$atts; return true; },10,2);

$nonce = wp_create_nonce('wp_rest');
function clear_rl(){ global $wpdb; foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%phenix_pay_rl_%'") as $o) delete_option($o); }
function rest($method,$path,$body=null,$use_nonce=true,$headers=array(),$raw=null) {
	$req = new WP_REST_Request($method, '/phenix-payments/v1'.$path);
	if ($use_nonce) $req->set_header('X-WP-Nonce', $use_nonce===true?$GLOBALS['nonce']:$use_nonce);
	foreach ($headers as $k=>$v) $req->set_header($k,$v);
	if ($raw!==null) { $req->set_body($raw); $req->set_header('content-type','application/json'); }
	elseif ($body!==null) { $req->set_body(wp_json_encode($body)); $req->set_header('content-type','application/json'); }
	$res = rest_do_request($req);
	return array($res->get_status(), $res->get_data());
}
function uuid4(){ return wp_generate_uuid4(); }
function base($amount='150.00', $extra=array()) { return array_merge(array('attempt_id'=>uuid4(),'funding'=>'paypal','name'=>'Jane Client','email'=>'jane@example.com','reference'=>'INV-1024','description'=>'Website build','amount'=>$amount), $extra); }
function rec($order){ return Phenix_Pay_Payments_Repository::find_by_order_id($order); }

// --- Security: nonce
list($c,$d)=rest('POST','/orders', base(), false);
check('create order without nonce is rejected (403)', $c===403, "got $c");
list($c,$d)=rest('POST','/orders', base(), 'bogus');
check('create order with bad nonce is rejected (403)', $c===403, "got $c");

// --- Validation
foreach (array('0','-5','abc','1.234','10000.01','1e3','') as $bad) {
	list($c,$d)=rest('POST','/orders', base($bad));
	check("invalid amount '$bad' rejected (422)", $c===422 && isset($d['fields']['amount']), "got $c ".wp_json_encode($d));
}
list($c,$d)=rest('POST','/orders', base('50', array('email'=>'not-an-email','name'=>'')));
check('invalid email/name rejected', $c===422 && isset($d['fields']['email'],$d['fields']['name']));
list($c,$d)=rest('POST','/orders', base('50', array('attempt_id'=>'123')));
check('invalid attempt id rejected', $c===400);

clear_rl();
// --- Happy path
$in = base('150.00');
list($c,$d)=rest('POST','/orders', $in);
check('create order OK', $c===200 && !empty($d['order_id']), wp_json_encode($d));
$order = $d['order_id'];
$st = mockpp_state();
$sent = $st['orders'][$order]['purchase_units'][0];
check('order amount set server-side (150.00 USD)', $sent['amount']==array('currency_code'=>'USD','value'=>'150.00'));
check('order has custom_id + invoice_id', $sent['custom_id']===$in['attempt_id'] && $sent['invoice_id']==='PHX-'.$in['attempt_id']);
check('NO_SHIPPING requested', $st['orders'][$order]['purchase_units'] && ($st['orders'][$order]['payment_source'] ?? null) === null);

// Duplicate submission of the same attempt
$calls = mockpp_state()['create_calls'];
list($c,$d2)=rest('POST','/orders', $in);
check('duplicate attempt returns same order, no new PayPal order', $c===200 && $d2['order_id']===$order && mockpp_state()['create_calls']===$calls);
list($c,$d3)=rest('POST','/orders', array_merge($in, array('amount'=>'1.00')));
check('same attempt with different amount is refused (409)', $c===409);

// Capture before buyer approval
list($c,$d)=rest('POST',"/orders/$order/capture");
check('capture before approval refused (409), not marked paid', $c===409 && rec($order)->status==='created', "got $c ".rec($order)->status);

// Client-side "success" without capture never marks paid
check('record still unpaid', rec($order)->status !== 'completed');

mockpp_approve($order);
list($c,$d)=rest('POST',"/orders/$order/capture");
check('capture completes', $c===200 && $d['status']==='completed' && !empty($d['transaction_id']), wp_json_encode($d));
$r = rec($order);
check('record completed w/ capture id, method, completed_at', $r->status==='completed' && $r->paypal_capture_id && $r->payment_method==='PayPal' && $r->completed_at);
check('admin + receipt emails sent once', count($GLOBALS['mails'])===2, count($GLOBALS['mails']));

$cc = mockpp_state()['capture_calls'];
list($c,$d)=rest('POST',"/orders/$order/capture");
check('duplicate capture is idempotent (no 2nd PayPal capture)', $c===200 && $d['status']==='completed' && mockpp_state()['capture_calls']===$cc);
check('no duplicate emails', count($GLOBALS['mails'])===2);

// Cancel cannot downgrade a paid payment
rest('POST',"/orders/$order/cancel");
check('cancel does not affect completed payment', rec($order)->status==='completed');

// Unknown order
list($c,$d)=rest('POST','/orders/ABCDEF12345/capture');
check('capture of unknown order -> 404', $c===404);

// --- Cancel flow
list($c,$d)=rest('POST','/orders', base('75.00'));
$o2=$d['order_id'];
rest('POST',"/orders/$o2/cancel");
check('buyer cancel marks cancelled', rec($o2)->status==='cancelled');

// --- Declined
list($c,$d)=rest('POST','/orders', base('13.13'));
$o3=$d['order_id']; mockpp_approve($o3);
list($c,$d)=rest('POST',"/orders/$o3/capture");
check('declined card -> 402 with retry, record declined', $c===402 && !empty($d['retry']) && rec($o3)->status==='declined', "got $c ".wp_json_encode($d));

// --- Pending
list($c,$d)=rest('POST','/orders', base('14.14'));
$o4=$d['order_id']; mockpp_approve($o4);
list($c,$d)=rest('POST',"/orders/$o4/capture");
check('pending capture -> pending, not completed', $c===200 && $d['status']==='pending' && rec($o4)->status==='pending' && rec($o4)->status_detail==='PENDING_REVIEW');

// --- Concurrency guard
list($c,$d)=rest('POST','/orders', base('20.00'));
$o5=$d['order_id']; mockpp_approve($o5);
Phenix_Pay_Payments_Repository::update(rec($o5)->id, array('status'=>'capturing'));
$cc = mockpp_state()['capture_calls'];
list($c,$d)=rest('POST',"/orders/$o5/capture");
check('concurrent capture blocked while another is in progress', $d['status']==='processing' && mockpp_state()['capture_calls']===$cc);
Phenix_Pay_Payments_Repository::update(rec($o5)->id, array('status'=>'created'));

// --- Fixed amount token
$tok = Phenix_Pay_Payment_Service::sign_fixed(25000,'USD','Deposit','INV-9');
list($c,$d)=rest('POST','/orders', base('1.00', array('amount_token'=>$tok)));
check('fixed token: browser amount ignored, 250.00 used', $c===200 && mockpp_state()['orders'][$d['order_id']]['purchase_units'][0]['amount']['value']==='250.00' && rec($d['order_id'])->reference==='INV-9');
$parts = explode('.',$tok); $evil = rtrim(strtr(base64_encode(wp_json_encode(array('a'=>100,'c'=>'USD','d'=>'Deposit','r'=>'INV-9'))),'+/','-_'),'=').'.'.$parts[1];
list($c,$d)=rest('POST','/orders', base('1.00', array('amount_token'=>$evil)));
check('tampered fixed token rejected', $c===400);

// --- Card fields + 3DS
list($c,$d)=rest('POST','/orders', base('30.00', array('funding'=>'card_fields')));
$o6=$d['order_id'];
check('card_fields order requests SCA_WHEN_REQUIRED', mockpp_state()['orders'][$o6]['payment_source']['card']['attributes']['verification']['method']==='SCA_WHEN_REQUIRED');
$s=mockpp_state(); $s['orders'][$o6]['status']='APPROVED'; $s['orders'][$o6]['payment_source']=array('card'=>array('brand'=>'VISA','type'=>'CREDIT','last_digits'=>'1111','authentication_result'=>array('liability_shift'=>'NO','three_d_secure'=>array('enrollment_status'=>'Y','authentication_status'=>'N')))); mockpp_save($s);
list($c,$d)=rest('POST',"/orders/$o6/capture");
check('failed 3DS is not captured', $c===402 && rec($o6)->status==='declined' && mockpp_state()['orders'][$o6]['status']==='APPROVED');
list($c,$d)=rest('POST','/orders', base('31.00', array('funding'=>'card_fields')));
$o7=$d['order_id']; $s=mockpp_state(); $s['orders'][$o7]['status']='APPROVED'; $s['orders'][$o7]['payment_source_override']=array('card'=>array('brand'=>'VISA','type'=>'DEBIT','last_digits'=>'1111')); $s['orders'][$o7]['payment_source']=array('card'=>array('brand'=>'VISA','authentication_result'=>array('liability_shift'=>'POSSIBLE','three_d_secure'=>array('enrollment_status'=>'Y','authentication_status'=>'Y')))); mockpp_save($s);
list($c,$d)=rest('POST',"/orders/$o7/capture");
check('successful 3DS card captured; method recorded without card digits', $c===200 && rec($o7)->status==='completed' && rec($o7)->payment_method==='Card - VISA (debit)', rec($o7)->payment_method);

clear_rl();
// --- Webhooks
$cap = rec($order)->paypal_capture_id;
function hook($event, $sig='valid-sig') {
	$raw = wp_json_encode($event);
	return rest('POST','/webhook',null,false,array('paypal-auth-algo'=>'SHA256withRSA','paypal-cert-url'=>'https://api.sandbox.paypal.com/cert.pem','paypal-transmission-id'=>'t-'.uniqid(),'paypal-transmission-sig'=>$sig,'paypal-transmission-time'=>gmdate('c')),$raw);
}
$refund = array('id'=>'WH-EVT-1','event_type'=>'PAYMENT.CAPTURE.REFUNDED','resource'=>array('id'=>'REF1','status'=>'COMPLETED','amount'=>array('currency_code'=>'USD','value'=>'50.00'),'links'=>array(array('rel'=>'up','href'=>"https://api.sandbox.paypal.com/v2/payments/captures/$cap"))));
list($c,$d)=hook($refund,'forged');
check('webhook with invalid signature rejected (400), no change', $c===400 && rec($order)->status==='completed');
list($c,$d)=rest('POST','/webhook',null,false,array(),wp_json_encode($refund));
check('webhook without signature headers rejected', $c===400);
list($c,$d)=hook($refund);
check('verified partial refund webhook -> partially_refunded 50.00', $c===200 && rec($order)->status==='partially_refunded' && (int)rec($order)->refunded_minor===5000);
list($c,$d)=hook($refund);
check('replayed webhook event ignored (no double count)', $c===200 && (int)rec($order)->refunded_minor===5000);
$refund2=$refund; $refund2['id']='WH-EVT-2'; $refund2['resource']['amount']['value']='100.00';
hook($refund2);
check('second refund completes full refund', rec($order)->status==='refunded' && (int)rec($order)->refunded_minor===15000);

// Buyer closed browser after approval: CHECKOUT.ORDER.APPROVED webhook captures server-side.
list($c,$d)=rest('POST','/orders', base('99.00'));
$o8=$d['order_id']; mockpp_approve($o8);
list($c,$d)=hook(array('id'=>'WH-EVT-3','event_type'=>'CHECKOUT.ORDER.APPROVED','resource'=>array('id'=>$o8,'status'=>'APPROVED')));
check('ORDER.APPROVED webhook completes abandoned approved order', $c===200 && rec($o8)->status==='completed');

// Pending -> completed via PAYMENT.CAPTURE.COMPLETED (status re-read from API)
$cap4 = rec($o4)->paypal_capture_id; mockpp_set_capture_status($cap4,'COMPLETED');
list($c,$d)=hook(array('id'=>'WH-EVT-4','event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>array('id'=>$cap4,'status'=>'COMPLETED')));
check('pending payment completed by verified webhook', rec($o4)->status==='completed');
// Webhook payload lying about status: API value wins
list($c,$d)=rest('POST','/orders', base('14.14', array()));
$o9=$d['order_id']; mockpp_approve($o9); rest('POST',"/orders/$o9/capture");
$cap9=rec($o9)->paypal_capture_id;
hook(array('id'=>'WH-EVT-5','event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>array('id'=>$cap9,'status'=>'COMPLETED')));
check('webhook resource status not trusted blindly (API says PENDING)', rec($o9)->status==='pending');

// Amount mismatch protection
list($c,$d)=rest('POST','/orders', base('40.00'));
$o10=$d['order_id']; $s=mockpp_state(); $s['orders'][$o10]['purchase_units'][0]['amount']['value']='4.00'; $s['orders'][$o10]['status']='APPROVED'; mockpp_save($s);
list($c,$d)=rest('POST',"/orders/$o10/capture");
check('order amount mismatch -> refused, not captured', $c===409 && rec($o10)->status==='failed' && mockpp_state()['orders'][$o10]['status']==='APPROVED');

// Admin refresh
$svc = new Phenix_Pay_Payment_Service();
$res = $svc->refresh(rec($o9));
check('admin refresh re-reads status from PayPal', !is_wp_error($res) && $res->status==='pending');

// Logging must never contain secrets
Phenix_Pay_Logger::error('test', array('client_secret'=>'test-secret','authorization'=>'Bearer A21TOKEN','note'=>'card 4111 1111 1111 1111'));
$log = wp_json_encode(get_option('phenix_pay_log'));
check('log redacts secrets/tokens/card numbers', strpos($log,'test-secret')===false && strpos($log,'A21TOKEN')===false && strpos($log,'4111')===false);

// Bad credentials
Phenix_Pay_Settings::update(array('sandbox_client_secret'=>Phenix_Pay_Settings::encrypt('wrong')));
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%phenix_pay_tok_%'") as $o) delete_option($o);
$t = (new Phenix_Pay_PayPal_Client())->get_access_token(true);
check('wrong credentials detected', is_wp_error($t));
Phenix_Pay_Settings::update(array('sandbox_client_secret'=>Phenix_Pay_Settings::encrypt('test-secret')));

// Rate limit
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%phenix_pay_rl_%'") as $o) delete_option($o);
$codes=array(); for($i=0;$i<11;$i++){ list($c,)=rest('POST','/orders', base('5.00')); $codes[]=$c; }
check('rate limit after 10 orders (429)', end($codes)===429 && $codes[9]===200, implode(',',$codes));

// Admin pages: capability checks
wp_set_current_user(0);
check('anonymous user lacks capability', !current_user_can(Phenix_Pay_Plugin::capability()));

echo "\n$pass passed, $fail failed\n";
exit($fail?1:0);
