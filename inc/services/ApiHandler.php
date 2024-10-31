<?php

namespace AdsNS\Services;

use AdsNS\PrimeAds;

class ApiHandler{

	/**
	 * Удаляет элемент
	 * @param  string $table Название таблицы
	 */
	public static function delete($table, $where){
		global $wpdb;

		if($wpdb->delete($wpdb->prefix . $table, $where) === false)
		    $error = true;

		if(!$error){
		    $ret = array('result' => 'ok', 'ver' => ADS_VERSION);
		    self::_collectCss();
		    self::_collectJs();
		    self::_collectFonts();
		}else{
		    $ret = array('result' => 'error','error' => $wpdb->last_error, 'ver' => ADS_VERSION);
		}

		echo json_encode($ret);
	}

	/**
	 * Копирует картинку в локальное хранилище
	 */
	public static function uploadImg($img){
	    $file = file_get_contents(ADS_SERVICE_URL . 'uploads/' . $img);
	    if(!$file){
	    	$ch = curl_init(ADS_SERVICE_URL . 'uploads/' . $img);
	    	$fp = fopen(PrimeAds::$ADS_UPLOADS . $img, "w");
	    	$fp2 = fopen(PrimeAds::$ADS_UPLOADS_STATIC . $img, "w");

	    	curl_setopt($ch, CURLOPT_FILE, $fp);
	    	curl_setopt($ch, CURLOPT_HEADER, 0);

	    	curl_exec($ch);
	    	fclose($fp);

	    	curl_setopt($ch, CURLOPT_FILE, $fp2);
	    	curl_exec($ch);
	    	fclose($fp2);
	    	curl_close($ch);
	    }else{
	    	file_put_contents(PrimeAds::$ADS_UPLOADS . $img, $file);
	    	file_put_contents(PrimeAds::$ADS_UPLOADS_STATIC . $img, $file);
	    }
	}

	/**
	 * Обновляет/создаёт элемент
	 * @param  string $table Название таблицы
	 */
	public static function updateData($table, $resetShows = false){
	    global $wpdb;

	    $data = $_REQUEST['data'];
	    $id = $_REQUEST['id'];

	    foreach ($data as $key => $value) 
	    	$data[$key] = stripslashes($value);
	    
	    $error = false;
	    $errorText = '';

	    if($id > 0){
	    	$has = $wpdb->get_var("SELECT id from " . $wpdb->prefix . $table . " WHERE `id`=$id");
	    	if($has)
		        if($wpdb->update($wpdb->prefix .$table, $data, array('id' => $id)) === false){
		        	echo json_encode(array('result' => 'error','error' => 'Update: ' . $wpdb->last_error, 'ver' => ADS_VERSION));
		            return;
		        }
	    }
	    if(!isset($has) || !$has){
	        if($wpdb->insert($wpdb->prefix .$table, $data)){
	            $id = $wpdb->insert_id;
	        }else{
            	echo json_encode(array('result' => 'error','error' => 'Insert: ' . $wpdb->last_error, 'ver' => ADS_VERSION));
                return;
	        }
	    }

	    if($table == 'ads_simple_tizer_group'){ 
	    	//удаляем/создаём отношение с шаблонами
	    	if(!self::_groupTemplateRel($id)){
    			echo json_encode(array('result' => 'error','error' => 'Template Relation: ' . $wpdb->last_error, 'ver' => ADS_VERSION));
    		    return;
	    	}
	    	//удаляем/создаём отношение с тизерами
	    	if(!self::_groupTizerRel($id)){
    			echo json_encode(array('result' => 'error','error' => 'Tizer Relation: ' . $wpdb->last_error, 'ver' => ADS_VERSION));
    		    return;
	    	}
	    }


	    if($resetShows) 
	    	if(!self::_resetShows($data['position'])){
    			echo json_encode(array('result' => 'error','error' => 'Reset shows: ' . $wpdb->last_error, 'ver' => ADS_VERSION));
    		    return;
	    	}

	    self::_collectCss();
	    self::_collectJs();
	    self::collectClickJs();
	    self::_collectFonts();
	    ApiHandler::updateFakeAdsense(); 

	    echo json_encode( $ret = array('result' => 'ok','id' => $id, 'ver' => ADS_VERSION));
	}

	/**
	 * Обновляет/создаёт шаблон
	 */
	public static function updateTemplate(){
	    global $wpdb;

	    $data = $_REQUEST['data'];
	    $id = $data['forigin_id'];
	    $type = $data['type'];

	    foreach ($data as $key => $value) 
	    	$data[$key] = stripslashes($value);

	    $error = false;

	    $template_id = $wpdb->get_var("SELECT id from " . $wpdb->prefix . "ads_template WHERE `forigin_id`=$id AND `type`=$type");
	    if($template_id){
	    	if($wpdb->update($wpdb->prefix . 'ads_template', $data, array('id' => $template_id)) === false)
	    	    $error = true;
	    }else{
	    	if($wpdb->insert($wpdb->prefix . 'ads_template', $data)){
	    	    $id = $wpdb->insert_id;       
	    	}else{
	    	    $error = true;
	    	}
	    }
  
	    if(!$error){
	    	$wpdb->query("UPDATE " . $wpdb->prefix . "ads_template_shows SET `shows`=0");
	        $ret = array('result' => 'ok','id' => $id, 'ver' => ADS_VERSION);
	        self::_collectCss();
	        self::_collectJs();
	        self::_collectFonts();
	        self::collectClickJs();
	    }
	    else
	        $ret = array('result' => 'error','error' => $wpdb->last_error, 'ver' => ADS_VERSION);

	    echo json_encode($ret);
	}

	/**
	 * Обновляет/создаёт тизер
	 */
	public static function updateTizer(){
	    global $wpdb;

	    $data = $_REQUEST['data'];
	    $id = $data['forigin_id'];

	    foreach ($data as $key => $value) 
	    	$data[$key] = stripslashes($value);

	    $error = false;

	    $tizer_id = $wpdb->get_var("SELECT id from " . $wpdb->prefix . "ads_simple_tizer WHERE `forigin_id`=$id");
	    if($tizer_id){
	    	if($wpdb->update($wpdb->prefix . 'ads_simple_tizer', $data, array('id' => $tizer_id)) === false)
	    	    $error = true;
	    }else{
	    	if($wpdb->insert($wpdb->prefix . 'ads_simple_tizer', $data)){
	    	    $id = $wpdb->insert_id;       
	    	}else{
	    	    $error = true;
	    	}
	    }
	 
	    if(!$error){
	    	$wpdb->query("UPDATE " . $wpdb->prefix . "ads_simple_tizer_stat SET `shows`=0");
	        $ret = array('result' => 'ok','id' => $id, 'ver' => ADS_VERSION);
	    }
	    else
	        $ret = array('result' => 'error','error' => $wpdb->last_error, 'ver' => ADS_VERSION);

	    self::collectClickJs();

	    echo json_encode($ret);
	}

	/**
	 * Возвращает полную статистику
	 */
	public static function getStats(){
		$data = array();

		$data['simple_tizer'] = self::_getStat('ads_simple_tizer_stat');
		$data['code_block'] = self::_getStat('ads_code_block');
		$data['adsense_block'] = self::_getStat('ads_adsense_block');
		$data['text_block'] = self::_getStat('ads_text_block');
		$data['comment'] = self::_getStat('ads_comment');
		$data['offer_post'] = self::_getStat('ads_offer_post');
		$data['counter'] = array('current' => get_option('counter_current', 0), 'total' => get_option('counter_total', 0),'allpages_current' => get_option('counter_allpages_current', 0), 'allpages_total' => get_option('counter_allpages_total', 0));

		echo json_encode(array('result' => 'ok','data' => $data, 'ver' => ADS_VERSION));
	}

	/**
	 * Сбрасывает статистику
	 */
	public static function resetStats(){
		global $wpdb;
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_adsense_block SET `views`=0, `clicks`=0");
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_code_block SET `views`=0, `clicks`=0");
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_text_block SET `views`=0, `clicks`=0");
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_comment SET `views`=0, `clicks`=0");
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_offer_post SET `views`=0, `clicks`=0, `clicks_macros`=''");
		$wpdb->query("UPDATE " . $wpdb->prefix . "ads_simple_tizer_stat SET `views`=0, `clicks`=0, `clicks_header`=0, `clicks_text`=0, `clicks_dop`=0, `clicks_image`=0");

		update_option('counter_current', 0);
		update_option('counter_allpages_current', 0);

		echo json_encode(array('result' => 'ok', 'ver' => ADS_VERSION));
	}

	/**
	 * Создаёт/обновляет файл подложного Adsense
	 */
	public static function updateFakeAdsense(){
		$ca = get_option('ads-ca-pub', null);
		if(!$ca){
			$ca = self::_getRand(16);
			update_option('ads-ca-pub', $ca);
		}
		$slot = get_option('ads-slot', null);
		if(!$slot){
			$slot = self::_getRand(10);
			update_option('ads-slot', $slot);
		}
		if(!file_exists(PrimeAds::$ADS_UPLOADS . 'gads.js')){
			$content = 'function gads()
{
	var a = 1;   var b = 1;   a = a + b;

	if (a + a === ' . $slot . ')
  	{
   		document.write(\'<script type="text/javascript"><!--\ngoogle_ad_client = "ca-pub-' . $ca . '";\ngoogle_ad_slot = "' . $slot . '";\ngoogle_ad_width = 700;\ngoogle_ad_height = 300;\n//-->\n</script>\n<script type="text/javascript"\nsrc="http://pagead2.googlesyndication.com/pagead/show_ads.js">\n</script>\');
	}
}';
			file_put_contents(PrimeAds::$ADS_UPLOADS . 'gads.js', $content);
		}
	}

	/**
	 * Генерирует псевдослучайное число
	 */
	private static function _getRand($count){
		$res = '';
		for ($i=0; $i < $count; $i++) {
			if($i > 0)
				$res .= mt_rand(0,9);
			else
				$res .= mt_rand(1,7);
		}
		return $res;
	}

	/**
	 * Собирает статистику по элементам
	 */
	private static function _getStat($table){
		global $wpdb;
		
		$data = array();
		if($table == 'ads_simple_tizer_stat'){
			$items = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . $table);
			foreach ($items as $item) 
				$data[] = array(
					'tizer' => $item->tizer,
					'group' => $item->group,
					'template' => $item->template,
					'views' => $item->views,
					'clicks' => $item->clicks,
					'clicks_header' => $item->clicks_header,
					'clicks_text' => $item->clicks_text,
					'clicks_dop' => $item->clicks_dop,
					'clicks_image' => $item->clicks_image,
				);
		}else if($table == 'ads_offer_post'){
			$items = $wpdb->get_results("SELECT id,views,clicks,clicks_macros FROM " . $wpdb->prefix . $table);
			foreach ($items as $item) 
				$data[$item->id] = array('views' => $item->views, 'clicks' => $item->clicks, 'clicks_macros' => $item->clicks_macros);
		}else{
			$items = $wpdb->get_results("SELECT id,views,clicks FROM " . $wpdb->prefix . $table);
			foreach ($items as $item) 
				$data[$item->id] = array('views' => $item->views, 'clicks' => $item->clicks);
		}

		return $data;
	}

	/**
	 * Обнуляет показы
	 */
	private function _resetShows($position){
		global $wpdb;

		$tables = array('ads_code_block', 'ads_adsense_block', 'ads_text_block', 'ads_comment', 'ads_simple_tizer_group');

		foreach ($tables as $table) 
			if($wpdb->update($wpdb->prefix . $table, array('shows' => 0), array('position' => $position)) === false)
				return false;
		return true;
	}

	public function updatePlugin(){

		$plugin_file = 'prime-ads/prime-ads.php';

		if(defined( 'DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS){
			echo json_encode(array('result' => 'error','error' => 'Установлена константа DISALLOW_FILE_MODS. Удалённое обновление невозможно.', 'ver' => ADS_VERSION));
			return;
		}

		include_once(ABSPATH . 'wp-admin/includes/admin.php');
		require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

		if (function_exists('get_site_transient'))
			delete_site_transient('update_plugins');
		else
			delete_transient('update_plugins');

		$is_active  = is_plugin_active($plugin_file);
		$is_active_network = is_plugin_active_for_network($plugin_file);

		$skin = new PluginUpgraderSkin();
		$upgrader = new \Plugin_Upgrader($skin);

		if (!self::_check_filesystem_access()){
			echo json_encode(array('result' => 'error','error' => 'Нет прав на запись в папку плагина. Удалённое обновление невозможно.', 'ver' => ADS_VERSION));
			return;
		}

		wp_update_plugins();

		ob_start();
		$result = $upgrader->upgrade($plugin_file);
		$data = ob_get_contents();
		ob_clean();

		if (is_wp_error($result)){
			echo json_encode(array('result' => 'error','error' => $result->get_error_message(), 'ver' => ADS_VERSION));
			return;
		}else if (!empty( $skin->error)){
			if(is_wp_error($skin->error)){
				echo json_encode(array('result' => 'error','error' => $skin->error->get_error_message(), 'ver' => ADS_VERSION));
				return;
			}
			echo json_encode(array('result' => 'error','error' => $upgrader->strings[$skin->error], 'ver' => ADS_VERSION));
			return;
		}else if ((!$result && !is_null($result )) || $data){
			echo json_encode(array('result' => 'error','error' => 'Непредвиденная ошибка. Удалённое обновление невозможно.', 'ver' => ADS_VERSION));
			return;
		}

		if ($is_active)
			activate_plugin($plugin_file, '', $is_active_network, true);

		self::_collectCss();
		self::_collectJs();
		self::collectClickJs();
		self::_collectFonts();
		ApiHandler::updateFakeAdsense();

		update_option('ads-link-hash', '');
		PrimeAds::checkHash(); 

		echo json_encode(array('result' => 'ok'));
	}

	private function _check_filesystem_access() {

		ob_start();
		$success = request_filesystem_credentials( '' );
		ob_end_clean();

		return (bool) $success;
	}

	private function _groupTemplateRel($id){
		global $wpdb;

		$data = $_REQUEST['data'];

    	$wpdb->delete($wpdb->prefix . 'ads_template_shows', array('entity' => $id));	
    	$templates = unserialize(stripslashes($data['template']));
    	if($templates && is_array($templates) && count($templates) > 0){
    		foreach ($templates as $template) {
    			if(!$wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "ads_template_shows WHERE `template`=$template and `entity`=$id")){
	    			if(!$wpdb->insert($wpdb->prefix .'ads_template_shows', array('template' => $template, 'entity' => $id))){
    				    return false;
	    			}	
    			}    			
    		}
    	}
    	return true;
	}

	private function _groupTizerRel($group){
		global $wpdb;

		$data = $_REQUEST['data'];
	
		$templates = unserialize(stripslashes($data['template']));
		$tizers = unserialize(stripslashes($data['tizers']));

		$ids = array();
		foreach ($tizers as $tizer) {
			foreach ($templates as $template) {
				$rel_id = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "ads_simple_tizer_stat WHERE `group`=$group AND `template`=$template AND `tizer`=$tizer");
				if(!$rel_id){
					if(!$wpdb->insert($wpdb->prefix .'ads_simple_tizer_stat', array('group' => $group, 'template' => $template, 'tizer' => $tizer))){
					    return false;
					}
					$rel_id = $wpdb->insert_id; 
				}
				$ids[] = $rel_id;
			}
		}
		// удаляем лишние связи
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "ads_simple_tizer_stat WHERE `group`=$group AND `id` NOT IN (" . implode(',', $ids) . ")");

		// удаляем тизеры без связей
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "ads_simple_tizer WHERE `forigin_id` NOT IN (SELECT DISTINCT tizer FROM " . $wpdb->prefix . "ads_simple_tizer_stat)");

		// сбрасываем показы
		$wpdb->update($wpdb->prefix . 'ads_simple_tizer_stat', array('shows' => 0), array('group' => $group));

    	return true;
	}

	private function _collectCss(){
		global $wpdb;
		$tables = array('ads_adsense_block', 'ads_position', 'ads_template', 'ads_text_block', 'ads_comment');

		$css = '';
		foreach ($tables as $table) {
			if($table == 'ads_template'){
				$res = $wpdb->get_results("SELECT css FROM " . $wpdb->prefix . $table);
			}else{
				$res = $wpdb->get_results("SELECT css FROM " . $wpdb->prefix . $table . " WHERE is_active=1");
			}

			if($res)
				foreach ($res as $r) 
					$css .= $r->css;
				
		}
		file_put_contents(ADS_PATH . '/jci/style.css', $css);
		copy(ADS_PATH . '/jci/style.css', PrimeAds::$ADS_UPLOADS . 'style.css'); 
	}

	private function _collectJs(){
		global $wpdb;
		$tables = array('ads_adsense_block', 'ads_position', 'ads_template', 'ads_text_block', 'ads_comment');

		$js = '';
		foreach ($tables as $table) {
			if($table == 'ads_template'){
				$res = $wpdb->get_results("SELECT js FROM " . $wpdb->prefix . $table);
			}else if ($table == 'ads_position'){
				$res = $wpdb->get_results("SELECT js FROM " . $wpdb->prefix . $table . " WHERE is_active=1 AND async=0");
			}else{
				$res = $wpdb->get_results("SELECT js FROM " . $wpdb->prefix . $table . " WHERE is_active=1");
			}
			
			if($res)
				foreach ($res as $r) 
					$js .= $r->js;
				
		}
		file_put_contents(ADS_PATH . '/jci/script.js', $js);
		copy(ADS_PATH . '/jci/script.js', PrimeAds::$ADS_UPLOADS . 'script.js'); 
	}

	public function collectClickJs($return = false){
		global $wpdb;

		$js = 'jQuery(document).ready(function($) {';
		$templates = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_template");
		if($templates){
			$url = '/' . get_option('ads-link-hash') . '/' . md5('simptizer' . date('d.m.Y')) . '/';
			foreach ($templates as $template) {
				$groups = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_simple_tizer_group WHERE template LIKE '%" . '"' . $template->forigin_id . '"' ."%'");
				if(!$groups) continue;
				foreach ($groups as $group) {
					$tizersIds = unserialize($group->tizers);
					$tizers = $wpdb->get_results("SELECT *,stat.id as rel_id FROM " . $wpdb->prefix . "ads_simple_tizer as st LEFT JOIN " . $wpdb->prefix . "ads_simple_tizer_stat as stat ON st.forigin_id=stat.tizer WHERE `is_active`=1 AND stat.tizer IN (" . implode(',', $tizersIds) . ")");
					if(!$tizers) continue;
					foreach ($tizers as $tizer) {
						$js .= "$('body').on('click', '#" . $template->prefix . $tizer->rel_id . $group->id . " span', {to:'" . $url . $tizer->rel_id . '_' . $group->id . '.html' . "'}, linkOut);\n";
					}
				}	
			}
		}
		$blocks = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_text_block");
		if($blocks){
			$url = '/' . get_option('ads-link-hash') . '/' . md5('textblock' . date('d.m.Y')) . '/';
			foreach ($blocks as $block) {
				if(!$block->prefix) continue;
				$template = $block->template;
				preg_match_all('#href="(.*)"#U', $template, $links);

				if(count($links) > 0){
					$js .= "$('body').on('click', '#" . $block->prefix . " span', function(){var it = this; var e = {data:{to:''}}; $('#" . $block->prefix . "').find('span').each(function(index, el) {if(el == it){";
					foreach ($links[1] as $key => $value){					
						$js .= "if(index == " . $key . ") e.data.to = '" . $url . $block->id . '_' .($key+1) . '.html' . "';";
					}		
					$js .= "}});linkOut(e);});\n";
				}
			}
		}

		$comments = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_comment");
		if($comments){
			$url = '/' . get_option('ads-link-hash') . '/' . md5('comment' . date('d.m.Y')) . '/';
			foreach ($comments as $comment) {
				if(!$comment->prefix) continue;
				$template = $comment->template;
				preg_match_all('#href="(.*)"#U', $template, $links);

				if(count($links) > 0){
					$js .= "$('body').on('click', '#" . $comment->prefix . " span', function(){var it = this; var e = {data:{to:''}}; $('#" . $comment->prefix . "').find('span').each(function(index, el) {if(el == it){";
					foreach ($links[1] as $key => $value){					
						$js .= "if(index == " . $key . ") e.data.to = '" . $url . $comment->id . '_' .($key+1) . '.html' . "';";
					}		
					$js .= "}});linkOut(e);});\n";
				}
			}
		}
		
		$js .= "});";
		if($return) return $js;

		file_put_contents(ADS_PATH . '/jci/jquery.lc.min.js', $js);
		copy(ADS_PATH . '/jci/jquery.lc.min.js', PrimeAds::$ADS_UPLOADS . 'jquery.lc.min.js'); 
	}

	private function _collectFonts(){
		global $wpdb;
		$tables = array('ads_template', 'ads_text_block', 'ads_comment');

		$fonts = '';
		foreach ($tables as $table) {
			if($table == 'ads_template'){
				$res = $wpdb->get_results("SELECT fonts FROM " . $wpdb->prefix . $table);
			}else{
				$res = $wpdb->get_results("SELECT fonts FROM " . $wpdb->prefix . $table . " WHERE is_active=1");
			}

			if($res)
				foreach ($res as $r) 
					$fonts .= $r->fonts;
				
		}
		$fonts = explode("\n", $fonts);
		$fonts = array_unique($fonts);
		file_put_contents(ADS_PATH . '/jci/fonts.txt', implode("\n",$fonts));
	}
}

?>