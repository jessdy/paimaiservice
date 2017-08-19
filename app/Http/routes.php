<?php

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use DB;
date_default_timezone_set('Asia/Shanghai');
/*
 |--------------------------------------------------------------------------
 | Application Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register all of the routes for an application.
 | It is a breeze. Simply tell Lumen the URIs it should respond to
 | and give it the Closure to call when that URI is requested.
 |
 */
$app -> get('/', function(Request $request) {
	$gid = $request->input('gid');
	$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".env('WEIXIN_APPID')."&redirect_uri=" . env('WEIXIN_BACKURL') . "&state=".$gid."&response_type=code&scope=snsapi_userinfo#wechat_redirect";
	return redirect($url);
});

$app -> get('back' , 'WeixinController@back');

$app -> get('cron' , function() {
	$goods = DB::select('select * from pm_good where end = 0 and endtime < ?', [date("Y-m-d H:i:s")]);
	for($i=0;$i<count($goods);$i++) {
		$order = DB::select('select * from pm_order_record where good_id = ? order by price desc limit 1', [$goods[$i]->id]);
		DB::update('update pm_good set end = 1 where id = ?', [$goods[$i]->id]);
		if(count($order) > 0) {
			DB::insert('insert into pm_order(good_id, user_id, order_time, price) values (?,?,?,?)', [$order[0]->good_id,$order[0]->user_id,date("Y-m-d H:i:s"),$order[0]->price]);
			
		}
		sendSMS($goods[$i]);
	}
	
	return $goods;
});

function sendSMS($good) {
	$sms = "您的拍品“".$good->title."”已到期，请登录系统查看最新竞拍情况。";
	echo $sms;
	$user = DB::select('select * from pm_user where id = ?', [$good->seller])[0];
	$token = gettoken();
	$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
	$body = new \stdClass();
	$text = new \stdClass();
	$text->content = $sms;
	$body->touser = $user->openid;
	$body->msgtype = 'text';
	$body->text = $text;
	$res = $client -> request('POST', '/cgi-bin/message/custom/send?access_token=' . $token, ['body' => json_encode($body, JSON_UNESCAPED_UNICODE)]);
}

function gettoken(){
	$token = DB::select('select * from pm_token')[0];
	$appid = env('WEIXIN_APPID');
	$appsecret = env('WEIXIN_SECRET');
	$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
	if($token->expire < time()) {
		$res = $client -> request('GET', '/cgi-bin/token?grant_type=client_credential&appid=' . $appid . '&secret=' . $appsecret);
		$body = json_decode($res->getBody());
		DB::update('update pm_token set token = ?, expire = ?', [$body->access_token, time() + 7000]);
		return $body->access_token;
	} else {
		return $token->token;
	}
}

$app -> get('banners/', 'GoodsController@banner');
$app -> get('allgoods/', 'GoodsController@allgoods');
$app -> get('good/{id}', 'GoodsController@good');
$app -> post('good', 'GoodsController@add');
$app -> post('editgood/{id}', 'GoodsController@edit');
$app -> get('deletegood/{id}', 'GoodsController@deletegood');
$app -> get('topgood/{id}', 'GoodsController@topgood');
$app -> get('notopgood/{id}', 'GoodsController@notopgood');
$app -> get('goodrecord/{id}', 'GoodsController@goodrecord');
$app -> post('postrecord', 'GoodsController@postrecord');
$app -> post('buyit', 'GoodsController@buyit');
$app -> post('searchgoods', 'GoodsController@searchgoods');
$app -> get('goods/{userid}', 'GoodsController@goods');
$app -> get('order/{orderid}', 'GoodsController@order');
$app -> get('finish/{orderid}', 'GoodsController@finish');

$app -> get('userinfo/{id}', 'UserController@userinfo');
$app -> get('userorders/{id}', 'UserController@userorders');
$app -> get('userfavor/{id}', 'UserController@userfavor');
$app -> post('userfavornot/{id}', 'UserController@userfavornot');
$app -> post('useredit/{id}', 'UserController@useredit');
$app -> post('userfavorit', 'UserController@userfavorit');
$app -> get('userlove', 'UserController@userlover');
$app -> post('userlove', 'UserController@userlove');
$app -> post('unuserlove', 'UserController@unuserlove');
$app -> get('guanzhu/{id}', 'UserController@guanzhu');
$app -> get('fensi/{id}', 'UserController@fensi');
$app -> get('usergoods/{id}', 'UserController@usergood');

$app -> get('userpublish/{id}', 'UserController@userpublish');
$app -> post('fileupload', 'FileController@upload');

$app -> get('weixin/token', 'WeixinController@gettoken');
$app -> get('weixin/publishgoods', 'WeixinController@publishgoods');
//$app -> get('weixin/publish/{id}', 'WeixinController@publish');
$app -> get('weixin/send/{userid}', 'WeixinController@send');
$app -> get('weixin/sendall', 'WeixinController@sendall');
$app -> get('weixin/status/{id}', 'WeixinController@status');
$app -> post('weixin/config', 'WeixinController@config');
$app -> post('weixin/upload', 'WeixinController@upload');
$app -> post('weixin/uploadimg', 'WeixinController@uploadimg');

$app -> get('share/allshare/{id}', 'ShareController@allshare');
$app -> get('share/visit/{id}', 'ShareController@visit');
$app -> post('share', 'ShareController@share');
$app -> post('share/comment', 'ShareController@comment');
$app -> post('share/likeit', 'ShareController@likeit');
$app -> get('share/{id}', 'ShareController@sharedetail');
$app -> post('shareit/{id}', 'ShareController@shareit');

$app -> get('share/myshare/{id}/{uid}', 'ShareController@myshare');
