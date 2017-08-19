<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use DB;

class FileController extends Controller {

	public function upload() {
		$file = Request::file('photo');
		if($file->isValid()) {
			$path = env('FILE_UPLOAD_PATH');
			$relatePath = substr(guid(), 1, -1).'/';
			Request::file('photo')->move($path.$relatePath, $file->getClientOriginalName());
			resize($path.$relatePath.$file->getClientOriginalName());
			$res = array();
			$res["url"] = $relatePath.$file->getClientOriginalName();
			return $res;
		} else {
			return 0;
		}
	}
}

function guid(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
        return $uuid;
    }
}

function resize($pic) {
    //图片的等比缩放
    
    //因为PHP只能对资源进行操作，所以要对需要进行缩放的图片进行拷贝，创建为新的资源
    $src = imagecreatefromjpeg($pic);
	$suffix = strrchr($pic,'.');
    
    //取得源图片的宽度和高度
    $size_src=getimagesize($pic);
    $w=$size_src['0'];
    $h=$size_src['1'];
    
    //指定缩放出来的最大的宽度（也有可能是高度）
    $max=300;
    
	if($w < 300 && $h < 300) {
		return;
	}
    //根据最大值为300，算出另一个边的长度，得到缩放后的图片宽度和高度
    if($w > $h){
        $w=$max;
        $h=$h*($max/$size_src['0']);
    }else{
        $h=$max;
        $w=$w*($max/$size_src['1']);
    }
    
    //声明一个$w宽，$h高的真彩图片资源
    $image=imagecreatetruecolor($w, $h);
    
    //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
    imagecopyresized($image, $src, 0, 0, 0, 0, $w, $h, $size_src['0'], $size_src['1']);
    switch($suffix){
    	case '.gif':
            imagegif($image, $pic);
			break;
		case '.png':
			imagepng($image, $pic);
			break;
		case '.jpg':
			imagejpeg($image, $pic);
			break;
		case '.bmp':
			imagewbmp($image, $pic);
			break;
		case '.jpeg':
			imagejpeg($image, $pic);
			break;
		default:
			imagejpeg($image, $pic);
			break;
	} 
	
    //销毁资源
    imagedestroy($image);
}
