<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use DB;

class WeixinController extends Controller {

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
	
	public function getticket(){
		$ticket = DB::select('select * from pm_ticket')[0];
		$token = $this->gettoken();
		$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
		if($ticket->expire < time()) {
			$res = $client -> request('GET', '/cgi-bin/ticket/getticket?access_token=' . $token . '&type=jsapi');
			$body = json_decode($res->getBody());
			DB::update('update pm_ticket set ticket = ?, expire = ?', [$body->ticket, time() + 7000]);
			return $body->ticket;
		} else {
			return $ticket->ticket;
		}
	}
	
	public function back(Request $request) {
		$code = $request -> input('code');
		$state = $request -> input('state');
		$goto = "";
		if(!empty($state)) {
			if($state == -1) {
				$goto = "#index_share.html";
			} else {
				$goto = "#good.html?id=".$state;
			}
		}
		$appid = env('WEIXIN_APPID');
		$appsecret = env('WEIXIN_SECRET');
		$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
		$res = $client -> request('GET', '/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $appsecret . '&code=' . $code . '&grant_type=authorization_code');
		$body = json_decode($res->getBody());
		$openid = $body->openid;
		$token = $body->access_token;
//		$token = $this->gettoken();
		$res = $client -> request('GET', '/sns/userinfo?access_token='.$token.'&openid='.$openid.'&lang=zh_CN');
//		echo $res->getBody();
		$user = json_decode($res->getBody());
		$exist = DB::select('select * from pm_user where openid = ?', [$openid]);
		session_start();
		if(count($exist) == 0) {
			$userid = DB::table('pm_user')->insertGetId(
				['openid' => $openid,'nickname'=>$user->nickname,'sex'=>$user->sex,'language'=>$user->language,'city'=>$user->city,'province'=>$user->province,'country'=>$user->country,'headimgurl'=>$user->headimgurl]);
			$_SESSION['userid'] = $userid;
			return redirect('http://paimai.momore.cn/paimai/index.php'.$goto);
		} else {
			$res2 = $client -> request('GET', 'cgi-bin/user/info?access_token='.$this->gettoken().'&openid='.$openid.'&lang=zh_CN');
			$body2 = json_decode($res2->getBody());
//			DB::insert('insert test(test) values (?)', [$res2->getBody()]);
			if(!empty($body2->groupid)) {
				DB::update('update pm_user set groupid = ? where openid = ?', [$body2->groupid, $openid]);
			} else {
				DB::update('update pm_user set groupid = ? where openid = ?', ['', $openid]);
			}
			$_SESSION['userid'] =  $exist[0]->id;
			return redirect('http://paimai.momore.cn/paimai/index.php'.$goto);
		}
	}
	
//	public function publish($id) {
//		$good = DB::select('select * from pm_good where id = ?', [$id])[0];
//		$thumb = env("FILE_UPLOAD_PATH").substr($good->pics, 9);
//		$web = env("WEIXIN_SHARE_URL");
//		$token = $this->gettoken();
//		$mediaid = $this->upload($thumb, $token);
//		$mid = DB::table('pm_media') -> insertGetId(array(
//			'user_id' => $good->seller,
//			'content_source_url' => $web.'?gid='.$good->id,
//			'title' => $good->title,
//			'thumb_media_id' => $mediaid,
//			'status' => 0,
//			'content' => $good -> describe
//		));
//		DB::update('update pm_good set media_id = ? where id = ?', [$mid, $id]);
//	}
	
	public function publishgoods() {
//		$users = DB::select('select distinct from_id from pm_user_love');
//		foreach($users as $user) {
//			$this -> send($user -> from_id);
//		}
		$users = DB::select('select id from pm_user');
		foreach($users as $user) {
			$this -> send2($user -> id);
		}
	}
	
	public function send2($userid) {
		$token = $this->gettoken();
		$web = env("WEIXIN_SHARE_URL");
		$articles = array();
		$news = new \stdClass();
		$goods = DB::select('select pg.* from pm_good pg where pg.end = 0 order by pg.begintime desc limit 6');
		foreach($goods as $good) {
			$article = new \stdClass();
			$article->title = $good->title;
			$article->description = $good->describe;
			$article->url = $web.'?gid='.$good->id;
			$article->picurl = $web.$good->pics;
			array_push($articles, $article);
			$news->articles = $articles;
		}
		$users = DB::select('select openid from pm_user where id = ?', [$userid]);
		foreach($users as $us) {
			$body = new \stdClass();
			$body->touser = $us->openid;
			$body->msgtype = 'news';
			$body->news = $news;
//			echo json_encode($body);
			$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
			$res = $client -> request('POST', '/cgi-bin/message/custom/send?access_token=' . $token, ['body' => json_encode($body, JSON_UNESCAPED_UNICODE)]);
			echo $res -> getBody();
		}
		
//		DB::update('update pm_good set media_id = 1 where user_id = ?', [$userid]);
	}
	
	public function send($userid) {
		$token = $this->gettoken();
		$web = env("WEIXIN_SHARE_URL");
		$articles = array();
		$news = new \stdClass();
		$goods = DB::select('select pg.* from pm_user_love pul inner join pm_good pg on pul.to_id = pg.seller and pg.end = 0 where pul.from_id = ? order by pg.begintime desc limit 6', [$userid]);
		foreach($goods as $good) {
			$article = new \stdClass();
			$article->title = $good->title;
			$article->description = $good->describe;
			$article->url = $web.'?gid='.$good->id;
			$article->picurl = $web.$good->pics;
			array_push($articles, $article);
			$news->articles = $articles;
		}
		$users = DB::select('select openid from pm_user where id = ?', [$userid]);
		foreach($users as $us) {
			$body = new \stdClass();
			$body->touser = $us->openid;
			$body->msgtype = 'news';
			$body->news = $news;
//			echo json_encode($body);
			$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
			$res = $client -> request('POST', '/cgi-bin/message/custom/send?access_token=' . $token, ['body' => json_encode($body, JSON_UNESCAPED_UNICODE)]);
			echo $res -> getBody();
		}
		
//		DB::update('update pm_good set media_id = 1 where user_id = ?', [$userid]);
	}

	public function status($id) {
		$token = $this->gettoken();
		$client = new Client(['base_uri' => 'https://api.weixin.qq.com/']);
		$res = $client -> request('POST', '/cgi-bin/message/mass/get?access_token=' . $token, ['body' => '{"msg_id": "'.$id.'"}']);
		echo $res -> getBody();		
	}

	public function sendall() {
		$users = DB::select('select distinct user_id from pm_media where status = 0');
		foreach($users as $user) {
			$this -> send($user -> user_id);
		}
	}
	
	public function uploads($url, $token){
		$ch = curl_init ();
		$data = array( 
    		'file' => curl_file_create($url)
		);
		curl_setopt ( $ch, CURLOPT_URL, 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$token.'&type=image' );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec ( $ch );
		curl_close ( $ch );
		return json_decode($result) -> media_id;
	}
	
	public function config(Request $request) {
		$url = $request -> input("url");
		$timestamp = time();
		$ticket = $this->getticket();
		$noncestr = $this->random(16);
		$str = "jsapi_ticket=".$ticket.'&noncestr='.$noncestr.'&timestamp='.$timestamp.'&url='.$url;
		$signature = sha1($str);
		$config = new \stdClass();
		$config->debug=false;
		$config->timestamp = $timestamp;
		$config->appId = env('WEIXIN_APPID');
		$config->nonceStr = $noncestr;
		$config->signature = $signature;
		$apis = array();
		array_push($apis, 'chooseImage');
		array_push($apis, 'uploadImage');
		array_push($apis, 'onMenuShareTimeline');
		array_push($apis, 'onMenuShareAppMessage');
		array_push($apis, 'onMenuShareQQ');
		array_push($apis, 'onMenuShareWeibo');
		$config->jsApiList = $apis;
		return json_encode($config);
	}
	
	public function random($length) {
		$str = null;
   		$strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
   		$max = strlen($strPol)-1;

   		for($i=0;$i<$length;$i++){
    		$str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
   		}
		return $str;
	}
	
	public function upload(Request $request) {
		$token = $this->gettoken();
		$userid = $request -> input("userid");
		$pic = $request -> input("pic");
		$file = file_get_contents('https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$pic);
    	file_put_contents(env('FILE_UPLOAD_PATH').$pic,$file);
		DB::insert('insert into pm_share(userid, pic) values (?,?)', [$userid, '/uploads/'.$pic]);
	}
	
	public function uploadimg(Request $request) {
		$token = $this->gettoken();
		$userid = $request -> input("userid");
		$pic = $request -> input("pic");
		$file = file_get_contents('https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$pic);
    	file_put_contents(env('FILE_UPLOAD_PATH').$pic,$file);
		echo '/uploads/'.$pic;
	}
}
