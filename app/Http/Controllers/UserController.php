<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use DB;

class UserController extends Controller {

	public function userinfo($id) {
		$user = DB::select('select pu.*,(select count(1) from pm_user_love where from_id = pu.id) as favors,(select count(1) from pm_user_love where to_id = pu.id) as fans from pm_user pu where id=?', [$id]);
		return $user;
	}
	
	public function userorders($id) {
		$orders = DB::select('select pg.title, po.price, pu.mobile as seller, po.order_time, pg.pics, po.good_id, po.id from pm_order po inner join pm_good pg on po.good_id = pg.id inner join pm_user pu on pg.seller = pu.id where po.user_id = ? order by po.order_time desc', [$id]);
		return $orders;
	}
	
	public function userfavor($id) {
		$favors = DB::select('select pf.id, pg.title, pu.mobile as seller, pg.begintime, pg.endtime, pg.pics, pf.good_id from pm_favor pf inner join pm_good pg on pf.good_id = pg.id inner join pm_user pu on pg.seller = pu.id  where pf.user_id = ?', [$id]);
		return $favors;
	}
	
	public function userfavornot($id) {
		$deleted = DB::delete('delete from pm_favor where id = ?', [$id]);
		return $deleted;
	}
	
	public function userfavorit() {
		$userid = Request::input('userid');
		$goodid = Request::input('goodid');
		$favor = DB::select("select * from pm_favor where user_id = ? and good_id = ?", [$userid, $goodid]);
		if(count($favor) == 0){
			DB::insert('insert pm_favor(user_id, good_id) values (?,?)', [$userid, $goodid]);
			return array();
		} else {
			return array();
		}
	}
	
	public function userpublish($id) {
		$products = DB::select('select pg.id, pg.buynow, pg.title, pg.pricenow, pg.pics, pg.end, po.id as orderid from pm_good pg left join pm_order po on pg.id = po.good_id where pg.seller = ? order by pg.id desc', [$id]);
		return $products;
	}
	
	public function useredit($id) {
		$name = Request::input('name');
		$mobile = Request::input('mobile');
		$thumb = Request::input('thumb');
		$email = Request::input('email');
		$wechatno = Request::input('wechatno');
		$user = DB::update('update pm_user set nickname = ?,mobile=?,headimgurl=?,wechatno=?,email=? where id=?', [$name,$mobile,$thumb,$wechatno,$email,$id]);
		return $user;
	}
	
	public function guanzhu($id) {
		$users = DB::select('select pu.* from pm_user_love pul inner join pm_user pu on pul.to_id = pu.id where pul.from_id = ?', [$id]);
		foreach($users as $user) {
			$user -> goods = DB::select('select * from pm_good pg where pg.seller = ? limit 3', [$user->id]);
		}
		return $users;
	}
	
	public function fensi($id) {
		$users = DB::select('select pu.* from pm_user_love pul inner join pm_user pu on pul.from_id = pu.id where pul.to_id = ?', [$id]);
		foreach($users as $user) {
			$user -> guanzhu = DB::select('select count(*) as guanzhu from pm_user_love where from_id = ? and to_id = ?', [$id, $user->id])[0];
		}
		return $users;
	}
	
	public function userlover() {
		$from = Request::input('from');
		$to = Request::input('to');
		$ct = DB::select('select count(1) as ct from pm_user_love where from_id = ? and to_id = ?', [$from, $to]);
		return $ct;
	}

	public function userlove() {
		$from = Request::input('from');
		$to = Request::input('to');
		$ct = $this->userlover();
		if($ct[0]->ct > 0) {
			return 0;
		} else {
			DB::insert('insert into pm_user_love(from_id, to_id) values (?,?)', [$from, $to]);
			return 1;
		}
	}
	
	public function unuserlove() {
		$from = Request::input('from');
		$to = Request::input('to');
		$ct = $this->userlover();
		DB::delete('delete from pm_user_love where from_id = ? and to_id = ?', [$from, $to]);
		return array();
	}

	public function usergood($id) {
		$res = array(
			'ing' => DB::select('select pg.*,pu.nickname from pm_good pg inner join pm_user pu on pg.seller = pu.id where pg.seller = ? and pg.end = 0 order by pg.begintime desc', [$id]),
			'end' => DB::select('select pg.*,pu.nickname from pm_good pg inner join pm_user pu on pg.seller = pu.id where pg.seller = ? and pg.end = 1 order by pg.begintime desc', [$id])
		);
		return $res;
	}
}
