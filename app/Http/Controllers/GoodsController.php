<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use DB;

class GoodsController extends Controller {

	public function banner() {
		$goods = DB::select('select * from pm_good where recommend = 1 and end = 0 limit 4');
		return $goods;
	}
	
	public function allgoods() {
		$goods = DB::select('select pg.*,pu.nickname as sellername from pm_good pg inner join pm_user pu on pg.seller = pu.id left join pm_order po on po.good_id = pg.id where ifnull(po.finish,0) = 0 and pg.end = 0 order by pg.begintime desc');
		return $goods;
	}
	
	public function good($id, Request $request) {
		$good = DB::select('select pg.*,UNIX_TIMESTAMP(pg.endtime)-8*3600 as endsec,pu.nickname as sellername from pm_good pg inner join pm_user pu on pg.seller = pu.id where pg.id = ?', [$id]);
		$userid = $request -> input("uid");
		$users = DB::select('select * from pm_user where id = ?', [$userid]);
		if($users[0] -> groupid == env('MANAGER_GROUP')) {
			$good[0]->cand = 1;
		}
		
		return $good;
	}
	
	public function deletegood($id) {
		$good = DB::delete('delete from pm_good where id = ?', [$id]);
		return $good;
	}
	
	public function topgood($id) {
		$good = DB::update('update pm_good set recommend = 1 where id = ?', [$id]);
		return $good;
	}
	
	public function notopgood($id) {
		$good = DB::update('update pm_good set recommend = 0 where id = ?', [$id]);
		return $good;
	}
	
	public function goods($userid) {
		return DB::select('select * from pm_good where seller = ? limit 3', [$userid]);
	}
	
	public function edit($id, Request $request) {
		$title = $request -> input("title");
		$describe = $request -> input("describe");
		$price = $request -> input("price") * 100;
		$buynow = $request -> input("buynow") * 100;
		$endtime = $request -> input("endtime");
		$category = $request -> input("category");
		$seller = $request -> input("seller");
		$allpics = $request -> input("allpics");
		$pics = $request -> input("pics");
		$step = $request -> input("step") * 100;
		$good = DB::update('update pm_good set title = ?, `describe` = ?, price = ?, buynow = ?, begintime = ?, endtime = ?, category = ?, seller = ?, pics = ?, allpics = ?, step = ?, pricenow = ? where id = ?', 
		[$title, $describe, $price, $buynow, date('Y-m-d h:i:s',time()), $endtime, $category, $seller, $pics, $allpics, $step, $price, $id]);
	}
	
	public function add(Request $request) {
		$title = $request -> input("title");
		$describe = $request -> input("describe");
		$price = $request -> input("price") * 100;
		$buynow = $request -> input("buynow") * 100;
		$endtime = $request -> input("endtime");
		$category = $request -> input("category");
		$seller = $request -> input("seller");
		$allpics = $request -> input("allpics");
		$pics = $request -> input("pics");
		$step = $request -> input("step") * 100;
		$good = DB::insert('insert into pm_good(title, `describe`, price, buynow, begintime, endtime, category, seller, pics, allpics, step, pricenow) values (?,?,?,?,?,?,?,?,?,?,?,?)', 
		[$title, $describe, $price, $buynow, date('Y-m-d h:i:s',time()), $endtime, $category, $seller, $pics, $allpics, $step, $price]);
	}

	public function goodrecord($id) {
		$record = DB::select("select por.ordertime,pu.mobile,por.price from pm_order_record por inner join pm_user pu on por.user_id = pu.id where por.good_id = ? order by por.price desc", [$id]);
		return $record;
	}
	
	public function postrecord(Request $request) {
		$goodid = $request -> input("goodid");
		$userid = $request -> input("userid");
		$price = $request -> input("price");
		$goods = DB::select('select * from pm_good where id = ?', [$goodid]);
		$result = array();
		if(count($goods) > 0) {
			$user = DB::select('select * from pm_user where id = ? and mobile is not null', [$userid]);
			if(count($user) == 0) {
				$result["result"] = "请先完善个人资料！";
				return $result;
			}
			$good = $goods[0];
			$records = DB::select('select count(*) as c from pm_order_record where good_id = ?', [$good->id])[0]->c;
			if($good->end == 1 || strtotime($good->endtime) < strtotime(date("Y-m-d H:i:s"))) {
				$result["result"] = "此拍品竞价已结束！";
				return $result;
			} else if($good->price > $price * 100) {
				$result["result"] = "出价低于起拍价格！";
				return $result;
			} else if($good->pricenow >= $price * 100 && $records > 0) {
				$result["result"] = "出价低于当前价格！";
				return $result;
			} else {
				DB::insert("insert into pm_order_record (good_id, user_id, price, ordertime) values (?,?,?,?)", [$goodid, $userid, $price*100, date('Y-m-d h:i:s',time())]);
				DB::update("update pm_good set pricenow = ? where id = ?", [$price*100, $goodid]);
				$result["result"] = "出价成功！";
				$good->pricenow = $price*100;
				$this->sendSMS($good);
				return $result;
			}
		} else {
			$result["result"] = "没有找到此拍品！";
			return $result;
		}
	}

	public function sendSMS($good) {
		$sms = "您的拍品“".$good->title."”价格发生了变化，最新价格为：".$good->pricenow/100.00."元。";
		$user = DB::select('select * from pm_user where id = ?', [$good->seller])[0];
		$token = $this->gettoken();
		$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
		$body = new \stdClass();
		$text = new \stdClass();
		$text->content = $sms;
		$body->touser = $user->openid;
		$body->msgtype = 'text';
		$body->text = $text;
		$res = $client -> request('POST', '/cgi-bin/message/custom/send?access_token=' . $token, ['body' => json_encode($body, JSON_UNESCAPED_UNICODE)]);
	}
	
	public function sendSMS2($good) {
		$sms = "您的拍品“".$good->title."”已被一口价买下，请登录系统查看";
		$user = DB::select('select * from pm_user where id = ?', [$good->seller])[0];
		$token = $this->gettoken();
		$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
		$body = new \stdClass();
		$text = new \stdClass();
		$text->content = $sms;
		$body->touser = $user->openid;
		$body->msgtype = 'text';
		$body->text = $text;
		$res = $client -> request('POST', '/cgi-bin/message/custom/send?access_token=' . $token, ['body' => json_encode($body, JSON_UNESCAPED_UNICODE)]);
	}
	
	public function buyit(Request $request) {
		$goodid = $request -> input("goodid");
		$userid = $request -> input("userid");
		$goods = $good = DB::select('select pg.* from pm_good pg where pg.id = ?', [$goodid]);
		$result = array();
		if(count($goods) > 0) {
			$user = DB::select('select * from pm_user where id = ? and mobile is not null', [$userid]);
			if(count($user) == 0) {
				$result["result"] = "请先完善个人资料！";
				return $result;
			}
			$good = $goods[0];
			if($good->end == 1 || strtotime($good->endtime) < strtotime(date("Y-m-d H:i:s"))) {
				$result["result"] = "此拍品竞价已结束！";
				return $result;
			} else {
				DB::insert("insert into pm_order_record (good_id, user_id, price, ordertime) values (?,?,?,?)", [$goodid, $userid, $good->buynow, date('Y-m-d h:i:s',time())]);
				DB::update("update pm_good set pricenow = ?,end = 1 where id = ?", [$good->buynow, $goodid]);
				DB::insert("insert into pm_order (good_id, user_id, price, order_time) values (?,?,?,?)", [$goodid, $userid, $good->buynow, date('Y-m-d h:i:s',time())]);
				$result["result"] = "一口价购买成功！";
				$this->sendSMS2($good);
				return $result;
			}
		} else {
			$result["result"] = "没有找到此拍品！";
			return $result;
		}
	}

	public function order($orderid) {
		$order = DB::select('select pg.*,pu.nickname as sellername,pu.mobile as sellermobile,puu.nickname as buyername, puu.mobile as buyermobile, po.finish from pm_order po inner join pm_good pg on po.good_id = pg.id inner join pm_user pu on pg.seller = pu.id inner join pm_user puu on puu.id = po.user_id where po.id = ?', [$orderid]);
		return $order;
	}
	
	public function finish($orderid) {
		$order = DB::update('update pm_order set finish = 1 where id = ?', [$orderid]);
		return $order;
	}
	
	public function searchgoods(Request $request) {
		$key = $request -> input("key");
		$goods = DB::select('select pg.*,pu.nickname as sellername from pm_good pg inner join pm_user pu on pg.seller = pu.id where pg.end = 0 and (pg.title like ? or pg.`describe` like ?) order by pg.endtime desc', ['%'.$key.'%', '%'.$key.'%']);
		return $goods;
	}
	
	public function gettoken(){
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
}
