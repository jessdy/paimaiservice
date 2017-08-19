<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use DB;

class ShareController extends Controller {

	public function allshare($id) {
		$goods = DB::select('select ps.*,pu.nickname,pu.headimgurl from pm_share ps inner join pm_user pu on ps.userid = pu.id where ps.id < ? order by ps.id desc limit 6', [$id]);
		foreach($goods as $good) {
			$comments = DB::select('select pc.*, pu.nickname, pu.headimgurl from pm_comment pc inner join pm_user pu on pc.userid = pu.id where pc.shareid = ? order by pc.commenttime limit 5', [$good->id]);
			$commentscount = DB::select('select count(*) as cc from pm_comment where shareid = ?', [$good->id])[0] -> cc;
			$good -> comments = $comments;
			$good -> commentscount = $commentscount;
		}
		return $goods;
	}
	
	public function myshare($id, $uid) {
		$goods = DB::select('select ps.*,pu.nickname,pu.headimgurl from pm_share ps inner join pm_user pu on ps.userid = pu.id where ps.id < ? and pu.id = ? order by ps.id desc limit 6', [$id, $uid]);
		foreach($goods as $good) {
			$comments = DB::select('select pc.*, pu.nickname, pu.headimgurl from pm_comment pc inner join pm_user pu on pc.userid = pu.id where pc.shareid = ? order by pc.commenttime limit 5', [$good->id]);
			$commentscount = DB::select('select count(*) as cc from pm_comment where shareid = ?', [$good->id])[0] -> cc;
			$good -> comments = $comments;
			$good -> commentscount = $commentscount;
		}
		return $goods;
	}
	
	public function sharedetail($id) {
		$goods = DB::select('select ps.*,pu.nickname,pu.headimgurl from pm_share ps inner join pm_user pu on ps.userid = pu.id where ps.id = ? order by ps.id desc', [$id]);
		foreach($goods as $good) {
			$comments = DB::select('select pc.*, pu.nickname, pu.headimgurl from pm_comment pc inner join pm_user pu on pc.userid = pu.id where pc.shareid = ? order by pc.commenttime', [$good->id]);
			$commentscount = DB::select('select count(*) as cc from pm_comment where shareid = ?', [$good->id])[0] -> cc;
			$good -> comments = $comments;
			$good -> commentscount = $commentscount;
		}
		return $goods;
	}
	
	public function share(Request $request) {
		$content = $request -> input('content');
		$userid = $request -> input('userid');
		$pic = $request -> input('pic');
		DB::insert('insert into pm_share (content, userid, pic, sharetime) values (?,?,?,?)', [$content, $userid, $pic, date('Y-m-d H:i:s',time())]);
	}
	
	public function visit($id) {
		DB::update('update pm_share set visit = visit + 1 where id = ?', [$id]);
	}
	
	public function likeit(Request $request) {
		$userid = $request -> input('userid');
		$shareid = $request -> input('shareid');
		$count = DB::select('select count(*) as cc from pm_share where id = ? and likeusers like ?', [$shareid, '%,'.$userid.',%'])[0] -> cc;
		if($count > 0) {
			return 0;
		} else {
			$userimg = DB::select('select headimgurl from pm_user where id = ?', [$userid])[0] -> headimgurl;
			$users = DB::select('select likeusers from pm_share where id = ?', [$shareid])[0] -> likeusers;
			$userimgs = DB::select('select likeuserimgs from pm_share where id = ?', [$shareid])[0] -> likeuserimgs;
			if(substr_count($userimgs,',') <= 20) {
				$userimgs = $userimgs.','.$userimg;
			}
			DB::update('update pm_share set likeusers = ?,likeuserimgs = ?,likes = likes + 1 where id = ?', [$users.','.$userid.',', $userimgs, $shareid]);
			return 1;
		}
	}

	public function comment(Request $request) {
//		DB::select('set names utf8mb4');
		$content = $request -> input('content');
		$userid = $request -> input('userid');
		$pic = $request -> input('pic');
		$shareid = $request -> input('shareid');
		$isreply = $request -> input('isreply');
		if($isreply != 0) {
			$replyid = $request -> input('replyuser');
			$replyusername = DB::select('select * from pm_user where id = ?', [$replyid])[0]->nickname;
			$username = DB::select('select * from pm_user where id = ?', [$userid])[0]->nickname;
			$this -> replySMS($username, $shareid, $content, $replyid);
		} else {
			$replyid = '';
			$replyusername = '';
		}
		DB::insert('insert into pm_comment (content, userid, pic, shareid, commenttime, isreply, replyuserid, replyusername) values (?,?,?,?,?,?,?,?)', 
			[$content, $userid, $pic, $shareid, date('Y-m-d H:i:s',time()), $isreply, $replyid, $replyusername]);
	}
	
	public function shareit($id) {
		DB::update('update pm_share set trans = trans + 1 where id = ?', [$id]);
	}
	
	public function replySMS($username, $shareid, $content, $replyid) {
		$sms = "你好，[".$username."]回复帖子[".$shareid."]：".$content;
		//echo $sms;
		$user = DB::select('select * from pm_user where id = ?', [$replyid])[0];
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
}
