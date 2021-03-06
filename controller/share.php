<?php
/**
 *      [PinTuXiu] (C)2001-2099 ONightjar.com Pintuxiu.com.
 *      This is NOT a freeware, use is subject to license terms
 */
class share extends basecontroller {

	public function __construct() {
		parent::__construct();
	}

	public function index()
	{
		$this->ouput("/share/index.php");
	}

	public function buy(){
		$item_id = $this->spArgs("mid");
		if($item_id&&is_numeric($item_id)){
			$ptx_item = spClass('ptx_item');
			$item = $ptx_item->get_item_by_id($item_id,' ptx_item.promotion_url ');
			if($item['promotion_url']){
				$this->jump(str_ireplace('+', '%2B', $item['promotion_url']));
				return;
			}
		}

		$this->jump(spUrl('welcome','index'),2,T('page_404_redirecting'));
	}

	public function create_square(){
		$ptx_item = spClass('ptx_item');
		$items = $ptx_item->findAll();
		$imagelib = spClass('ImageLib');
		foreach ($items as $item) {
			$dest_file_path = APP_PATH.$item['image_path'].'.jpg';
			if(file_exists($dest_file_path)){
				$imagelib->crop_square($dest_file_path, 100,'square_like');
			}
		}
	}

	public function update_category_hot_share(){
		$this->ajax_check_editer();
		$category_id = $this->spArgs("cid");
		$ptx_category = spClass('ptx_category');
		$results = $ptx_category->update_category_top();
	}

	public function find_category_hot_share(){
		$this->ajax_check_editer();
		$category_id = $this->category_id;
		$ptx_category = spClass('ptx_category');
		$results = $ptx_category->get_category_top();
		foreach ($results as $category) {
			if($category['category_id']==$category_id){
				$category['rand'] = random_string();
				$response = array('success' => true, 'data' => $category);
				echo json_encode($response);
				return;
			}
		}
	}


	public function crop_category_hot_share(){
		$this->ajax_check_editer();
		$share_id = $this->share_id;
		$category_id = $this->category_id;

		if(!$category_id){
			$response = array('success' => false, 'message' => T('category_not_existed'));
			echo json_encode($response);
			return;
		}

		$position = $this->spArgs("position");
		$x = $this->spArgs("x");
		$y = $this->spArgs("y");
		$w = $this->spArgs("w");
		$h = $this->spArgs("h");
		$js_w = $this->spArgs("js_w");
		$js_h = $this->spArgs("js_h");
		$ww = $this->spArgs("ww");
		$hh = $this->spArgs("hh");
		$style = $this->spArgs("sy");

		$ptx_category = spClass("ptx_category");
		$ptx_share = spClass('ptx_share');
		$share = $ptx_share->findJoin(array('share_id' => $share_id),null,'image_path');
		if($share){
			$imagelib = spClass("ImageLib");
			$imagepath = APP_PATH.$share['image_path'];
			$image_size=getimagesize($imagepath.'_large.jpg');
			$weight=$image_size[0];
			$height=$image_size[1];
			if($js_w<$weight){
				$scale = $js_w/$weight;
			}elseif ($js_h<$height){
				$scale = $js_h/$height;
			}else{
				$scale = 1;
			}
			$x = $x/$scale;
			$y = $y/$scale;
			$w = $w/$scale;
			$h = $h/$scale;
			$imagelib->crop_image($imagepath.'_large.jpg',$imagepath.'_home.jpg',$x,$y,$w,$h);
			$imagelib->create_thumb($imagepath.'_home.jpg',NULL,$ww,$hh,$imagepath.'_home.jpg');
		}

		$category = $ptx_category->find(array('category_id'=>$category_id));
		if($category&&$category_id){
			$shares = $category['category_home_shares']?unserialize($category['category_home_shares']):array();
			$shares['style'] = $style;
			$shares['s'.$position] = $share_id;
			$shares = serialize($shares);
			$ptx_category->update(array('category_id'=>$category_id),array('category_home_shares'=>$shares));
			$ptx_category->update_category_top();
			$data['img']= base_url().$share['image_path'].'_home.jpg?'.uniqid();
			$response = array('success' => true, 'message' => T('operate_succeed'), 'data' => $data);
			echo json_encode($response);
			return;
		}
		$response = array('success' => false, 'message' => T('operate_failed'));
		echo json_encode($response);
		return;
	}

	public function forwarding(){
		$this->ajax_check_login();
		$this->ajax_check_can_post();
		if(!$this->album_id){
			return ajax_failed_response(T('album_cannot_be_null'));
		}
		if(!$this->category_id){
			return ajax_failed_response(T('category_cannot_be_null'));
		}
		if(!$this->share_id){
			return ajax_failed_response(T('must_select_a_pin'));
		}

		$ptx_share = spClass('ptx_share');
		$response = $ptx_share->creat_forward($this->share_id,$this->category_id,$this->album_id,$this->current_user['user_id'],$this->current_user['nickname']);
		$event_dispatcher = spClass('event_dispatcher');
		$event_dispatcher->invoke('forward_share',$this->current_user['user_id']);
		echo json_encode($response);
		exit();
	}


	public function ajax_get_share(){
		$this->ajax_check_login();
		if(!$this->share_id){
			return ajax_failed_response(T('must_select_a_pin'));
		}
		$ptx_share = spClass('ptx_share');
		$share = $ptx_share->find_one(array('share_id'=>$this->share_id));

		$share['images'] = unserialize($share['images_array']);
		$share['intro'] = deparse_message($share['intro']);
		$share['random'] = random_string(alnum,3);

		return ajax_success_response(array('share'=>$share), T('operate_succeed'));
	}

	public function edit_share(){
		if($this->share_id){
			$ptx_share = spClass('ptx_share');
			$share = $ptx_share->get_share_by_id($this->share_id);

			if($share){
				if(!$this->is_editer()&&$share['user_id'] != $this->current_user['user_id']){
					return ajax_failed_response(T('no_permission'));
				}

				$share_data['album_id'] = $this->album_id;
				$share_data['category_id'] = $this->category_id;
				if(!$share_data['album_id']||!is_numeric($share_data['album_id'])||!$share_data['category_id']||!is_numeric($share_data['category_id'])){
					return ajax_failed_response(T('album_cat_required'));
				}

				if(($share['user_id']==$share['poster_id'])&&($share['poster_id']==$this->current_user['user_id']||$this->is_editer())){
					$intro_length = strlen($this->spArgs('intro'));
					if ($intro_length==0||$intro_length>6000){
						return ajax_failed_response(T('intro_too_long'));
					}
					$title_length = strlen($this->spArgs('title'));
					if ($title_length==0||$title_length>200){
						return ajax_failed_response(T('title_too_long'));
					}

					$update_data['title'] = $this->spArgs("title");
					$segment = spClass('Segment');
					$update_data['intro'] = $this->spArgs('intro');
					$segment_str = $segment->segment($update_data['intro']);
					$at_array = $this->parse_at($update_data['intro']);
					$tag_parse = $this->parse_tag($update_data['intro']);
					$update_data['intro'] = $at_array['message'];
					$update_data['intro_search'] .= ' '.$segment->convert_to_py($tag_parse);;
					$update_data['intro_search'] .= ' '.$at_array['atsearch_str'];
					$update_data['intro_search'] .= $segment_str['py'];
					$update_data['keywords'] .= ' '.$tag_parse;
					$update_data['keywords'] .= ' '.$segment_str['cn'];
					$update_data['price'] = $this->spArgs('price','0');

					$all_files = $this->spArgs('all_files');
					$all_files_arr = unserialize(stripslashes($all_files));
					$images = unserialize($share['images_array']);
					$images_array = array();
					$images_array_remove = array();
					$images_cover = null;
					$old_images_cover = null;
					$del_cover = false;
					foreach ($images as $image){
						if($image['url']==$share['image_path']){
							$old_images_cover = $image;
						}
						if($image_find = $this->hasImage($all_files_arr, $image)){
							$images_array[] = array('id'=>$image_find['id'],'url'=>$image_find['url'],'desc'=>delete_html($image_find['desc']),'cover'=>$image_find['cover']);
							if($image_find['cover']){
								$images_cover = $image_find;
							}
						}else{
							if($image['url']==$share['image_path']){
								$del_cover = true;
							}
							$images_array_remove[] = $image;
						}
					}

					$imagelib = spClass('ImageLib');
					if($images_cover&&$images_cover['url']!=$share['image_path']){
						$file_path = APP_PATH.$share['image_path'].'.jpg';
						@copy(APP_PATH.$share['image_path'].'_large.jpg', APP_PATH.$share['image_path'].'_large_tmp.jpg');
						@copy(APP_PATH.$images_cover['url'].'_large.jpg', APP_PATH.$share['image_path'].'_large.jpg');
						@copy(APP_PATH.$images_cover['url'].'_large.jpg', $file_path);
						if(!$del_cover){
							@copy(APP_PATH.$share['image_path'].'_square_like.jpg', APP_PATH.$images_cover['url'].'_square_like.jpg');
							@copy(APP_PATH.$share['image_path'].'_large_tmp.jpg',APP_PATH.$images_cover['url'].'_large.jpg');
						}else{
							file_exists(APP_PATH.$images_cover['url'].'_square_like.jpg') && unlink(APP_PATH.$images_cover['url'].'_square_like.jpg');
							file_exists(APP_PATH.$images_cover['url'].'_large.jpg') && unlink(APP_PATH.$images_cover['url'].'_large.jpg');
						}
						$imagelib->crop_square($file_path, 100,'square_like');
						$imagelib->create_thumb($file_path, 'middle', 200);
						$imagelib->create_thumb($file_path, 'small', 150);
						$imagelib->crop_square($file_path, 62);
						file_exists(APP_PATH.$share['image_path'].'_large_tmp.jpg') && unlink(APP_PATH.$share['image_path'].'_large_tmp.jpg');
						file_exists($file_path) && unlink($file_path);

						$img_pro = @getimagesize(APP_PATH.$share['image_path'].'_middle.jpg');
						$img['width']=$img_pro['0'];
						$img['height']=$img_pro['1'];
						$update_data['img_pro'] = array_to_str($img, ',');
						foreach ($images_array as $key=>$new_img) {
							if($new_img['url']==$images_cover['url']){
								$images_array[$key]['cover']=false;
								$images_array[$key]['desc']=$old_images_cover['desc'];
								$images_array[$key]['id']=$old_images_cover['id'];
								if($del_cover){
									$images_array[$key]['url'] = $share['image_path'];
									$images_array[$key]['cover'] = true;
								}
								continue;
							}
							if($new_img['url']==$share['image_path']){
								$images_array[$key]['desc']=$images_cover['desc'];
								$images_array[$key]['id']=$images_cover['id'];
								$images_array[$key]['cover']=true;
							}
						}

					}

					foreach ($images_array_remove as $remove) {
						if($remove['url']==$share['image_path']){
							continue;
						}
						file_exists(APP_PATH.$remove['url'].'_large.jpg') && unlink(APP_PATH.$remove['url'].'_large.jpg');
						file_exists(APP_PATH.$remove['url'].'_middle.jpg') && unlink(APP_PATH.$remove['url'].'_middle.jpg');
						file_exists(APP_PATH.$remove['url'].'_small.jpg') && unlink(APP_PATH.$remove['url'].'_small.jpg');
						file_exists(APP_PATH.$remove['url'].'_square.jpg') && unlink(APP_PATH.$remove['url'].'_square.jpg');
						file_exists(APP_PATH.$remove['url'].'_square_like.jpg') && unlink(APP_PATH.$remove['url'].'_square_like.jpg');
					}

					if(!is_numeric($update_data['price'])){
						$update_data['price'] = 0;
					}

					$update_data['total_images'] = array_length($images_array);
					$update_data['images_array'] = serialize(sysSortArray($images_array,"id","SORT_ASC","SORT_NUMERIC"));

					$ptx_item = spClass("ptx_item");
					$item_condition['item_id'] = $share['item_id'];
					$ptx_item->update($item_condition,$update_data);

				}
				$share_condition['share_id'] = $share['share_id'];
				$ptx_share->update($share_condition,$share_data);

				if($share_data['album_id']!=$share['album_id']){
					$ptx_album = spClass('ptx_album');
					$ptx_album->update_album_cover($share['album_id']);
					$ptx_album->update_album_cover($share_data['album_id']);
				}

				return ajax_success_response(null, T('edit_succeed'));
			}
		}
		return ajax_failed_response(T('illegal_operation'));
	}

	private function hasImage($img_arr,$img){
		foreach ($img_arr as $image){
			if($img['url']==$image['url']){
				return $image;
			}
		}
		return false;
	}


	public function delete_share(){
		$share_id = $this->share_id;
		$ptx_item = spClass('ptx_item');
		$ptx_share = spClass('ptx_share');
		if(share_id){
			$conditions['share_id'] = $share_id;
			$share = $ptx_share->find($conditions);
		}
		if($share){
			if(!$this->is_editer()&&$share['user_id'] != $this->current_user['user_id']){
				$response = array('result' => false, 'message' => T('no_permission'));
				echo json_encode($response);
				return;
			}else if($share['poster_id'] == $this->current_user['user_id']){
				$ptx_share->deleteByPk($share_id);
				$ptx_item->update(array('item_id'=>$share['item_id']),array('is_deleted'=>1));
				$item = $ptx_item->get_item_by_id($share['item_id'],' ptx_item.image_path,ptx_item.total_images ');
				$cover_path = $item['image_path'].'_large.jpg';
				file_exists(APP_PATH.$item['image_path'].'_large.jpg') && unlink(APP_PATH.$item['image_path'].'_large.jpg');
				file_exists(APP_PATH.$item['image_path'].'_middle.jpg') && unlink(APP_PATH.$item['image_path'].'_middle.jpg');
				file_exists(APP_PATH.$item['image_path'].'_small.jpg') && unlink(APP_PATH.$item['image_path'].'_small.jpg');
				file_exists(APP_PATH.$item['image_path'].'_square.jpg') && unlink(APP_PATH.$item['image_path'].'_square.jpg');
				file_exists(APP_PATH.$item['image_path'].'_square_like.jpg') && unlink(APP_PATH.$item['image_path'].'_square_like.jpg');
					
				$images = unserialize($item['images_array']);
				foreach ($images as $image){
					file_exists(APP_PATH.$image['url'].'_large.jpg') && unlink(APP_PATH.$image['url'].'_large.jpg');
					file_exists(APP_PATH.$image['url'].'_middle.jpg') && unlink(APP_PATH.$image['url'].'_middle.jpg');
					file_exists(APP_PATH.$image['url'].'_small.jpg') && unlink(APP_PATH.$image['url'].'_small.jpg');
					file_exists(APP_PATH.$image['url'].'_square.jpg') && unlink(APP_PATH.$image['url'].'_square.jpg');
					file_exists(APP_PATH.$image['url'].'_square_like.jpg') && unlink(APP_PATH.$image['url'].'_square_like.jpg');
				}
			}else{
				$ptx_share->deleteByPk($share_id);
			}
			$ptx_album = spClass('ptx_album');
			$ptx_album->update_album_cover($share['album_id']);

			$ptx_user = spClass('ptx_user');
			$ptx_user->del_share($share['user_id']);
			$response = array('success' => true, 'message' => T('del_succeed'));
			echo json_encode($response);
			return;
		}
		$response = array('success' => false, 'message' => T('del_failed'));
		echo json_encode($response);
		return;

	}
	public function add_like(){
		$this->ajax_check_login();
		$share_id = $this->share_id;
		$ptx_share = spClass('ptx_share');
		$share = $ptx_share->get_share_by_id($share_id);
		if($share['user_id'] == $this->current_user['user_id']){
			$response = array('result' => false, 'message' => "like_self");
			echo json_encode($response);
			return;
		}
		$ptx_favorite_sharing = spClass('ptx_favorite_sharing');
		$liked = $ptx_favorite_sharing->find(array('share_id'=>$share_id,'user_id'=>$this->current_user['user_id']));
		if($liked){
			$response = array('result' => false, 'message' => "like_already");
			echo json_encode($response);
			return;
		}

		$addlike_result = $ptx_share->add_like($share_id);
		$ptx_user = spClass('ptx_user');
		$ptx_user->add_like($share['user_id']);

		$ptx_message = spClass("ptx_message");
		$ptx_message->add_like($share_id,$share['title'],$this->current_user['user_id'],$share['user_id']);

		$event_dispatcher = spClass('event_dispatcher');
		$event_dispatcher->invoke('add_like',$this->current_user['user_id']);
		$event_dispatcher->invoke('been_like',$share['user_id']);

		$result = $ptx_favorite_sharing->add_one(array('share_id'=>$share_id,'user_id'=>$this->current_user['user_id']));
		if ($result) {
			$response = array('result' => true, 'message' => "success");
			echo json_encode($response);
		}else{
			$response = array('result' => false, 'message' => "failed");
			echo json_encode($response);
		}

	}

	public function add_comment(){
		$this->ajax_check_login();
		if(!$this->permission['other_permission']['allow_comment']){
			ajax_failed_response("no-permission");
		}

		$comment = delete_html($this->spArgs('comment'));
		$type = $this->spArgs('type','comment');
		$share_id = $this->share_id;
		$ptx_share = spClass('ptx_share');
		$share = $ptx_share->get_share_by_id($share_id);
		if(strlen($comment)>210){
			ajax_failed_response(T('length_canot_over_140'));
			return;
		}

		if (!$comment||!$share) {
			ajax_failed_response(T('fetch_data_failed'));
			return;
		}
		$at_array = $this->parse_at($comment,'comment');
		$tag_parse = $this->parse_tag($comment);
		$new_comment['comment_txt'] = $at_array['message'];

		$segment = spClass('Segment');
		$new_comment['search_en'] .= ' '.$segment->convert_to_py($tag_parse);;
		$new_comment['search_en'] .= ' '.$at_array['atsearch_str'];
		$new_comment['share_id'] = $share_id;
		$new_comment['user_id'] = $this->current_user['user_id'];
		$new_comment['create_time'] = mktime();

		$ptx_comment = spClass('ptx_comment');
		$result = $ptx_comment->add_one($new_comment);
		if ($result) {
			$new_comment['post_time_friend'] = friendlyDate($new_comment['create_time']);
			$new_comment['comment_id'] = $result;
			$new_comment['nickname'] = $this->current_user['nickname'];
			$new_comment['user_avatar'] = useravatar($new_comment['user_id'], 'middle');
			$new_comment['comment_txt'] = parse_message($new_comment['comment_txt']);

			$ptx_message = spClass("ptx_message");
			$ptx_message->add_comment($share_id,$share['title'],$this->current_user['user_id'],$share['user_id']);

			$event_dispatcher = spClass('event_dispatcher');
			$event_dispatcher->invoke('post_comment',$this->current_user['user_id']);
			ajax_success_response($new_comment, T('comment_succeed'));
			return ;
		}else{
			ajax_failed_response(T('comment_failed'));
			return ;
		}
	}

	public function del_comment(){
		$this->ajax_check_editer();
		$hash = $this->spArgs('hash');
		$share_id = $this->spArgs('sid');
		if (!is_numeric($hash)||!is_numeric($share_id)) {
			$response = array('success' => false, 'message' => T('del_failed'));
			echo json_encode($response);
			return;
		}
		$comment['comment_id'] = $hash;
		$comment['share_id'] = $share_id;
		$ptx_comment = spClass('ptx_comment');
		$result = $ptx_comment->del_one($comment);

		if($result){
			$response = array('success' => true, 'message' => T('del_succeed'));
			echo json_encode($response);
			return;
		}

		$response = array('success' => false, 'message' => T('del_failed'));
		echo json_encode($response);
	}


	public function item_upload(){
		$this->ajax_check_login();
		$this->ajax_check_can_post();
		$act = $this->spArgs("act");
		$category_model = spClass('ptx_category');
		if($act=='upload'){
			import(APP_PATH.'/include/ajaxuploader.php');
			$settings =  $this->settings;
			if($settings['file_setting']){
				$allowedExtensions = explode('|',$settings['file_setting']['upload_file_type']);
				$sizeLimit = $settings['file_setting']['upload_file_size']*1024;
			}else{
				$allowedExtensions = array('jpg','jpeg','gif','png');
				$sizeLimit = 2 * 1024 * 1024;
			}
			$uploader = new qqFileUploader($allowedExtensions, $sizeLimit);
			$temp_dir = '/data/attachments/tmp/';
			(!is_dir(APP_PATH.$temp_dir))&&@mkdir(APP_PATH.$temp_dir,0777,true);
			$result = $uploader->handleUpload(APP_PATH.$temp_dir);
			echo json_encode($result);
		}elseif ($act=='save'){
			if($this->save_share_upload()){
				$message = ($this->settings['basic_setting']['site_need_verify']&&$this->permission['need_verify']&&!$this->is_editer())?T('wait_admin_verify'):T('share_succeed');

				$response = array('success' => true, 'message' => $message);
				echo json_encode($response);
				return;
			}else{
				$response = array('success' => false, 'message' => T('share_failed'));
				echo json_encode($response);
				return;
			}
		}else{
			$this->categories = $category_model->findAll();
			//var_dump($this->categories);
			$this->ouput("/admin/item_upload.php");
		}
	}


	public function item_fetch(){
		$this->ajax_check_login();
		$this->ajax_check_can_post();
		$act = $this->spArgs("act");

		if($act=='fetch'){
			$remote_url = trim($this->spArgs('remote_url'));
			if(strpos($remote_url, 'taobao')||strpos($remote_url, 'tmall')){
				$channel_name = 'taobao';
			}else{
				$channel_name = 'others';
			}
			if($channel_name){
				$channel = spClass("Channel");
				$data = $channel->fetch_remoteinfo($channel_name,$remote_url);
			}
			if($data){
				$data['channel'] = $channel_name;
				$response = array('success' => true, 'message' => T('fetch_succeed'), 'data' => $data);
				echo json_encode($response);
			}else{
				$response = array('success' => false, 'message' => T('fetch_failed'));
				echo json_encode($response);
			}
		}elseif ($act=='save'){
			if($this->save_share_fetch()){
				$message = ($this->settings['basic_setting']['site_need_verify']&&$this->permission['need_verify']&&!$this->is_editer())?T('wait_admin_verify'):T('publish_succeed');
				$response = array('success' => true, 'message' => $message);
				echo json_encode($response);
				return;
			}else{
				$response = array('success' => false, 'message' => T('publish_failed'));
				echo json_encode($response);
				return;
			}
		}

	}

	public function video_fetch(){
		//$url = 'http://www.tudou.com/programs/view/Q_mSAYAvKp4/?resourceId=92873908_08_12_99&rpid=92873908';
		//$url="http://v.youku.com/v_show/id_XNDA2MTI1OTQ4.html?f=17651349";
		//$url="http://v.ku6.com/special/show_6578738/oUN9XYp8h2DFDh-6dKKyWg...html";
		//$url="http://video.sina.com.cn/v/b/78322036-1735047885.html";
		//$url="http://my.tv.sohu.com/u/pw/752681_1_1";
		//$url="http://www.ouou.com/starmv_mvview.jhtml?cont=1198";
		//$url="http://www.56.com/w79/play_album-aid-9915825_vid-Njg3NTU5NjQ.html";
		$this->ajax_check_login();
		$this->ajax_check_can_post();
		if(!$this->permission['other_permission']['allow_video']){
			ajax_failed_response("no-permission");
		}
		$act = $this->spArgs("act");
		if($act=='fetch'){
			$remote_url = $this->spArgs('remote_url');
			$data = parseflv($remote_url);
			if($data['flv']){
				$data['share_type'] = 'video';
				$data['type'] = 'video';
				ajax_success_response($data, T('fetch_succeed'));
			}else{
				ajax_failed_response(T('fetch_failed'));
			}
			return;
		}elseif ($act=='save'){
			if($this->save_share_fetch()){
				$message = ($this->settings['basic_setting']['site_need_verify']&&$this->permission['need_verify']&&!$this->is_editer())?T('wait_admin_verify'):T('publish_succeed');
				ajax_success_response($data, $message);
			}else{
				ajax_failed_response(T('publish_failed'));
			}
		}
	}

	public function collect(){
		$this->check_login();
		$images = $this->spArgs("links");
		if($images){
			$this->images = unserialize(stripslashes($images));
		}
		$this->ouput("/pin/collect.php");
	}

	private function save_share_fetch(){
		$cover_url = $this->spArgs('cover_filename');
		$all_files = $this->spArgs('all_files');
		$all_files_arr = unserialize(stripslashes($all_files));
		$length = array_length($all_files_arr);
		if($this->permission['other_permission']['upload_maxnum']&&$length>$this->permission['other_permission']['upload_maxnum']){
			ajax_failed_response(T('reach_upload_maxnum').$this->permission['other_permission']['upload_maxnum']);
		}

		$date_dir = '/data/attachments/'.date("Y/m/d/");
		(!is_dir(APP_PATH.$date_dir))&&@mkdir(APP_PATH.$date_dir,0777,true);
		$file_name = time().'';

		$this->save_fetch_file($cover_url, $date_dir, $file_name, true);
		$img_array = array();
		foreach ($all_files_arr as $key=>$up_image){
			if($up_image&&trim($up_image['url'])!=''){
				if($up_image['cover']){
					$img_array[] = array('id'=>$key,'url'=>$date_dir.$file_name,'desc'=>delete_html($up_image['desc']),'cover'=>$up_image['cover']);
					continue;
				}
				$this->save_fetch_file($up_image['url'], $date_dir, $file_name.'_'.$key, false);
				$img_array[] = array('id'=>$key,'url'=>$date_dir.$file_name.'_'.$key,'desc'=>delete_html($up_image['desc']),'cover'=>$up_image['cover']);
			}
		}
		$this->create_share_item($date_dir.$file_name,array_length($img_array),$img_array);
		return true;
	}

	private function save_fetch_file($url,$date_dir,$file_name,$is_cover=false){
		if(function_exists('curl_init')){
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FAILONERROR, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			$content =  curl_exec($curl);
			curl_close($curl);
		}else{
			$content = @file_get_contents($url);
		}

		$file_path = APP_PATH.$date_dir.$file_name.'.jpg';
		if(!empty($content) && @file_put_contents($file_path,$content) > 0)
		{
			$imagelib = spClass('ImageLib');
			$imagelib->create_thumb($file_path, 'large', 600);
			$imagelib->crop_square($file_path, 100,'square_like');
			if($is_cover){
				$imagelib->create_thumb($file_path, 'middle', 200);
				$imagelib->create_thumb($file_path, 'small', 150);
				$imagelib->crop_square($file_path, 62);
			}
			file_exists($file_path) && unlink($file_path);
			return true;
		}
	}
	
	public function test(){
		$test = "美女的手绢";
		$test = preg_replace(array("/美女/i","/手绢/i"), "#\\0#", $test);
		echo $test;
	}


	private function create_share_item($image_path,$image_num,$img_array){
		$local_user = $this->current_user;
		$segment = spClass('Segment');
		$img_pro = @getimagesize(APP_PATH.$image_path.'_middle.jpg');
		$img['width']=$img_pro['0'];
		$img['height']=$img_pro['1'];
		$data['img_pro'] = array_to_str($img, ',');
		$data['title'] = $this->spArgs('title');
		$data['category_id'] = $this->spArgs('category_id');
		$data['image_path'] = $image_path;
		$data['user_id'] = $local_user['user_id'];
		$data['intro'] = $this->spArgs('intro');
		$segment_str = $segment->segment($data['intro']);
		$at_array = $this->parse_at($data['intro']);
		$tag_parse = $this->parse_tag($data['intro']);
		$data['intro'] = $at_array['message'];

		$data['intro_search'] .= ' '.$segment->convert_to_py($tag_parse);;
		$data['intro_search'] .= ' '.$at_array['atsearch_str'];
		$data['intro_search'] .= $segment_str['py'];
		$data['keywords'] .= ' '.$tag_parse;
		$data['keywords'] .= ' '.$segment_str['cn'];

		$data['share_type'] = $this->spArgs('share_type','upload');
		$data['price'] = $this->spArgs('price','0');
		if(!is_numeric($data['price'])){
			$data['price'] = 0;
		}
		if($this->is_editer()){
			$data['is_show'] = 1;
		}else{
			$data['is_show'] = ($this->settings['basic_setting']['site_need_verify']&&$this->permission['need_verify'])?0:1;
		}
		$data['reference_url'] = $this->spArgs('reference_url','');
		$data['reference_itemid'] = $this->spArgs('item_id','');
		$data['reference_channel'] = $this->spArgs('channel');
		$data['promotion_url'] = $this->spArgs('promotion_url');
		$data['total_images'] = $image_num;
		$data['images_array'] = serialize($img_array);

		$share_attr = array();
		if($data['share_type']=='video'){
			$share_attr['video']['flv'] = $this->spArgs('flv','','POST','false');
			$share_attr['video']['imgurl'] = $image_path;
			$share_attr['video']['title'] = $data['title'];
			$data['share_attribute'] = serialize($share_attr);
		}

		$create_time = mktime();
		$data['create_time'] = $create_time;
		$share_data['create_time'] = $create_time;

		$share_data['poster_id'] = $local_user['user_id'];
		$share_data['poster_nickname'] = $local_user['nickname'];
		$share_data['original_id'] = 0;
		$share_data['user_id'] = $local_user['user_id'];
		$share_data['user_nickname'] = $local_user['nickname'];
		$share_data['total_comments'] = 0;
		$share_data['total_likes'] = 0;
		$share_data['total_clicks'] = 0;
		$share_data['total_forwarding'] = 0;
		$share_data['album_id'] = $this->spArgs('album_id');
		$share_data['category_id'] = $this->spArgs('category_id');

		$data['share'] = $share_data;
		$ptx_item = spClass('ptx_item');
		$ptx_item->linker['share']['enabled'] = true;
		$ptx_item->spLinker()->create($data);

		$ptx_album = spClass('ptx_album');
		$ptx_album->update_album_cover($share_data['album_id']);

		$event_dispatcher = spClass('event_dispatcher');
		if($data['share_type']=='video'){
			$event_dispatcher->invoke('post_video',$local_user['user_id']);
		}else{
			$event_dispatcher->invoke('post_share',$local_user['user_id']);
		}
	}

	private function save_share_upload(){

		$cover_url = $this->spArgs('cover_filename');
		$all_files = $this->spArgs('all_files');
		//$all_files_arr = explode('|', $all_files);
		$all_files_arr = unserialize(stripslashes($all_files));
		$length = array_length($all_files_arr);
		if($this->permission['other_permission']['upload_maxnum']&&$length>$this->permission['other_permission']['upload_maxnum']){
			ajax_failed_response(T('reach_upload_maxnum').$this->permission['other_permission']['upload_maxnum']);
		}
		$file_name = time().'';
		$date_dir = '/data/attachments/'.date("Y/m/d/");
		(!is_dir(APP_PATH.$date_dir))&&@mkdir(APP_PATH.$date_dir,0777,true);

		$this->save_upload_file($cover_url, $date_dir, $file_name, true);
		$img_array = array();
		foreach ($all_files_arr as $key=>$up_image){
			if($up_image&&trim($up_image['url'])!=''){
				if($up_image['cover']){
					$img_array[] = array('id'=>$key,'url'=>$date_dir.$file_name,'desc'=>delete_html($up_image['desc']),'cover'=>$up_image['cover']);
					continue;
				}
				$this->save_upload_file($up_image['url'], $date_dir, $file_name.'_'.$key, false);
				$img_array[] = array('id'=>$key,'url'=>$date_dir.$file_name.'_'.$key,'desc'=>delete_html($up_image['desc']),'cover'=>$up_image['cover']);
			}
		}
		$this->create_share_item($date_dir.$file_name,array_length($img_array),$img_array);
		return true;
	}

	private function save_upload_file($url,$date_dir,$file_name,$is_cover=false){
		$temp_dir = APP_PATH.'/data/attachments/tmp/';
		$source = $temp_dir.$url;
		//$file_name = time().'';
		$dest_file_path = APP_PATH.$date_dir.$file_name.'.jpg';
		@copy($source, $dest_file_path);
		file_exists($source) && unlink($source);

		$imagelib = spClass('ImageLib');
		$imagelib->create_thumb($dest_file_path, 'large', 600);
		$imagelib->crop_square($dest_file_path, 100,'square_like');
		if($is_cover){
			$imagelib->create_thumb($dest_file_path, 'middle', 200);
			$imagelib->create_thumb($dest_file_path, 'small', 150);
			$imagelib->crop_square($dest_file_path, 62);
		}
		file_exists($dest_file_path) && unlink($dest_file_path);
		return true;
	}


}