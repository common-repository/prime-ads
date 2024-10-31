<?php

namespace AdsNS\Services;

use AdsNS\PrimeAds;

class AdsHelper{

	const ROTATION_USER = 1;
	const ROTATION_EQUALLY = 2;
	const ROTATION_RAND = 3;
	
	const POSITION_UNDER_HEADER = 1;
	const POSITION_UNDER_POST = 2;
	const POSITION_MANUAL = 3;
	const POSITION_POPUP = 4;
	const POSITION_FIXED = 5;
	const POSITION_AFTER_PAR = 6;
	const POSITION_AFTER_CHARS_NUM = 7;
	const POSITION_AFTER_SUBHEADER = 8;
	const POSITION_AFTER_IMAGE = 9;
	const POSITION_BEFORE_IMAGE = 10;
	const POSITION_BEFORE_PAR_FROM_END = 11;
	const POSITION_IMAGE_FLIP = 12;
	const POSITION_MOBILE_BOTTOM = 13;
	const POSITION_BEFORE_SUBHEADER = 14;
	const POSITION_ALL_IMAGE_FLIP = 15;
	const POSITION_BEFORE_COMMENTS = 16;
	const POSITION_AFTER_TOC = 17;
	const POSITION_BEFORE_FIRST_COMMENT_CACKLE = 18;
	const POSITION_LINK_IN_PERELINK = 19;
	const POSITION_BEFORE_TOC = 20;
	const POSITION_BEFORE_TEXTERPUB_CONTENT = 21;
	const POSITION_AFTER_TEXTERPUB_CONTENT = 22;
	const POSITION_AFTER_CACKLE_COMMENTS = 23;
	const POSITION_RIGHT_OF_TOC = 24;

	const TYPE_SIMPLE_TIZER_GROUP = 1;
	const TYPE_LINK_TEST = 1;

	private $_tables = array('ads_code_block', 'ads_adsense_block', 'ads_text_block', 'ads_comment', 'ads_simple_tizer_group');
	private $_functions = array('_getCodeBlock', '_getAdsenseBlock', '_getTextBlock', '_getComment', '_getSimpleTizerGroup');

	public $postLength;
	public $postId;
	public $catId;
	public $catParents;
	public $tags;
	public $country;
	public $region;
	public $city;
	public $referrerDomain;

	public $postSlug;

	public $positions = array();

	public $screenWidth = array();
	public $screenWidthInPx;
	public $adblockDetected = 0;
	public $device = array();

	public $notIncludeAds = false;

	public $rsyaIds = array();

	// содержимое позиций загружается скриптом асинхронно
	private $_asyncContentPositions = [18, 23];

	/**
	 * Формирует массив позиций
	 */
	public function preparePositions($turbo = false)
	{
		$this->positions = array();
		$positions = $this->_getPositions($turbo);
		foreach ($positions as $positionObj) 
			$this->positions[$positionObj->position][] = array(
				'id' => $positionObj->id,
				'count' => $positionObj->count,
				'async' => $positionObj->async,
				'position_data' => (!$positionObj->async ? $this->generateSyncPositionContnent($positionObj, $turbo) : ''),
			);
	}

	public function generateSyncPositionContnent($positionObj, $turbo = false)
	{
		/*global $wp_cache_mfunc_enabled, $wp_super_cache_late_init;
		if($wp_cache_mfunc_enabled && $wp_super_cache_late_init){
			return '<!-- POSITION_' . $positionObj->position . '_' . $positionObj->id . '_' . ($turbo ? 1 : 0) . ' -->';
		}*/
		return $this->generatePositionContent($positionObj, $turbo);
	}

	/**
	 * Возвращает готовую позицию по объекту
	 */
	public function generatePositionContent($positionObj, $turbo = false)
	{
		if(in_array($positionObj->position, $this->_asyncContentPositions)) return '';
			
		$blockData = $this->getBlockInPosition($positionObj->id, false, $turbo);

		if($positionObj->position == self::POSITION_POPUP){
			return $this->_generatePopup($positionObj, $blockData);
		}elseif($positionObj->position == self::POSITION_FIXED){
			return $this->_generateFixed($positionObj, $blockData);
		}elseif($positionObj->position == self::POSITION_MOBILE_BOTTOM){
			return $this->_generateMobileBottom($positionObj, $blockData);
		}elseif($positionObj->position == self::POSITION_LINK_IN_PERELINK){
			return $this->_generatePerelinkItem($positionObj, $blockData);
		}

		return $blockData;
	}

	/**
	 * Возвращает готовую позицию по идентификатору позиции
	 */
	public function generatePositionContentByPositionId($id, $isShortcode = false, $turbo = false)
	{
		global $wpdb;
		if(is_array($id)){
			$positions = $wpdb->get_results(
				"SELECT * FROM " . $wpdb->prefix . "ads_position WHERE `is_active`=1 and `id` IN (" . implode(',', $id) . ")");
			$result = array();
			foreach ($positions as $position){
				$result[$position->id]['position_data'] = $this->generatePositionContent($position, $turbo);
				$result[$position->id]['position_js'] = $position->js;
			}
			return $result;
		}else{
			$position = $wpdb->get_row(
				"SELECT * FROM " . $wpdb->prefix . "ads_position WHERE `is_active`=1 and `id`=" . $id);

			if($isShortcode && $position->async == 1){
				return '<div class="prma-position-pointer" data-id="' . $position->id . '" data-place="' . $position->position . '"></div>';
			}

			return $this->generatePositionContent($position, $turbo);
		}
	}

	/**
	 * Возвращает скрипты позиции по идентификатору позиции
	 */
	public function generatePositionJsByPositionId($id)
	{
		global $wpdb;
		return $wpdb->get_var(
			"SELECT js FROM " . $wpdb->prefix . "ads_position WHERE `id`=" . $id);
	}

	/**
	 * Возвращает блок в позиции
	 */
	public function getBlockInPosition($position, $exclude = false, $turbo = false)
	{
		global $wpdb;
		$hour = date('G', time() + 3*60*60);
		if(!$position) return '';
		// мега сложный запрос на выборку одного элемента по всем критериям
		$sql = "SELECT id,method, shows FROM (";
		for ($i=0; $i < count($this->_tables); $i++) { 
			if($i > 0) $sql .= " UNION";

			$sql .= " SELECT id, tags, in_rubrics, ex_rubrics, in_posts, ex_posts, in_referrers, ex_referrers, min_lenght, country, region, city, resolutions, devices,adblock_only, by_timetable, days_of_week, from_time, to_time, not_show, is_active, position, position_not_in_timetable, shows*shows_ratio as shows, '" . $this->_functions[$i] . "' as method, ((`from_time`<`to_time` AND `from_time`<=" . $hour . " AND `to_time`>" . $hour . ") OR (`from_time`>`to_time` AND ((`from_time`<=" . $hour . " AND " . $hour . "<=23) OR (`to_time`>" . $hour . " AND " . $hour . ">=0))) OR (`from_time`=`to_time`)) as time_exp FROM " . $wpdb->prefix . $this->_tables[$i];
		}

		$widthCond = '';
		if($this->screenWidth){
			$widthCond = ' AND (`resolutions`=""';
			foreach ($this->screenWidth as $sWidth) {
				$widthCond .= ' OR `resolutions` LIKE "%[' . $sWidth . ']%"';
			}
			$widthCond .= ') ';
		}

		$deviceCond = '';
		if($this->device){
			$deviceCond = ' AND (`devices`="" OR `devices` is NULL';
			foreach ($this->device as $d) {
				$deviceCond .= ' OR `devices` LIKE "%[' . $d . ']%"';
			}
			$deviceCond .= ') ';
		}
			
		$sql .= ') AS t WHERE `is_active`=1 AND ((`by_timetable`=0 AND `position`=' . $position . ' ' . $widthCond . ' ' . $deviceCond . ') OR (`by_timetable`=1 AND ((`days_of_week` LIKE "%[' . date('w') . ']%" AND ((`not_show`=0 AND time_exp) OR (`not_show`=1 AND !time_exp)) AND `position`=' . $position . ' ' . $widthCond . ' ' . $deviceCond . ') OR (!(`days_of_week` LIKE "%[' . date('w') . ']%" AND ((`not_show`=0 AND time_exp) OR (`not_show`=1 AND !time_exp))) AND `position_not_in_timetable`=' . $position . '))))';

		if($this->catId){
			$parents_in = '';
			$parents_ex = '';
			if($this->catParents)
				foreach ($this->catParents as $parent){
					$parents_in .= ' OR `in_rubrics` LIKE "%[' . $parent . ']%"';
					$parents_ex .= ' And `ex_rubrics` NOT LIKE "%[' . $parent . ']%"';
				}
			$sql .= ' and (`in_rubrics`="" OR `in_rubrics` LIKE "%[' . $this->catId . ']%"' . $parents_in . ') and (`ex_rubrics`="" OR (`ex_rubrics` NOT LIKE "%[' . $this->catId . ']%"' . $parents_ex . '))';
		}
		if($this->postId)
			$sql .= ' and (`in_posts`="" OR `in_posts` LIKE "%[' . $this->postId . ']%") and (`ex_posts`="" OR `ex_posts` NOT LIKE "%[' . $this->postId . ']%")';
		if($this->referrerDomain)
			$sql .= ' and (`in_referrers`="" OR `in_referrers` LIKE "%[' . $this->referrerDomain . ']%") and (`ex_referrers`="" OR `ex_referrers` NOT LIKE "%[' . $this->referrerDomain . ']%")';
		if($this->postLength)
			$sql .= ' and `min_lenght`<=' . $this->postLength;
		if($this->country)
			$sql .= ' and (`country`="" OR `country` LIKE "%[' . $this->country . ']%")';
		if($this->region)
			$sql .= ' and (`region`="" OR `region` LIKE "%[' . $this->region . ']%")';
		if($this->city)
			$sql .= ' and (`city`="" OR `city` LIKE "%[' . $this->city . ']%")';
		if($exclude)
			$sql .= ' and id!=' . $exclude;
		
		if($this->tags){
			$sql .= ' and (`tags`=""';
			foreach ($this->tags as $tag) {
				$sql .= ' OR `tags` LIKE "%[' . $tag . ']%"';
			}
			$sql .= ')';
		}else{
			$sql .= ' and `tags`=""';
		}

		if(!$this->adblockDetected)
			$sql .= ' and `adblock_only`=0';

		$sql .= " order by";
		if($this->adblockDetected) $sql .=" adblock_only DESC, ";

		$sql.= " by_timetable DESC, IF(tags !='',0,1),IF(in_rubrics !='',0,1),IF(in_posts !='',0,1),IF(country !='',0,1),IF(region !='',0,1),IF(city !='',0,1),shows limit 1";
		$itemProps = $wpdb->get_row($sql);
		//echo $wpdb->last_error;
		if(!$itemProps) return '';

		return call_user_func(array($this, $itemProps->method), $itemProps->id, $turbo);
	}

	/**
	 * Возвращает вид блока кода
	 */
	private function _getCodeBlock($id, $turbo = false)
	{
		global $wpdb;
		$block = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_code_block WHERE `id`=" . $id);

		$this->_updateViews(array($block->id), 'ads_code_block');
		$this->_updateShows(array($block->id), 'ads_code_block');

		$prefix = AdsHelper::getPrefix();

		if($turbo){
			if(!preg_match('#R-A-\d{6}-\d+#i', $block->template, $blockId)) return '';
				
			$blockId = $blockId[0];
			if(!in_array($blockId, $this->rsyaIds)) return '';
			return '<figure data-turbo-ad-id="ad_place_' . array_search($blockId, $this->rsyaIds) . '"></figure>';
		}

		return '<!--noindex--><div class="' . $prefix . '-code-block prma-count" data-rel="cb_' . $block->id .'" id="' . $prefix . '-code-block-' . $block->id .'">' . str_replace("</scr'+'ipt>'", "<\/scr'+'ipt>'", $block->template) . '</div><!--/noindex-->';
	}

	/**
	 * Возвращает вид адаптивного Adsense
	 */
	private function _getAdsenseBlock($id, $turbo = false)
	{
		global $wpdb;
		$block = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_adsense_block WHERE `id`=" . $id);

		if($turbo) return '';

		$this->_updateViews(array($block->id), 'ads_adsense_block');
		$this->_updateShows(array($block->id), 'ads_adsense_block');

		$prefix = AdsHelper::getPrefix();

		return '<!--noindex--><div class="' . $prefix . '-google-block prma-count" data-rel="asb_' . $block->id .'" id="' . $prefix . '-google-block-' . $block->id .'">' . $block->template . '</div><!--/noindex-->';
	}

	/**
	 * Возвращает вид блока текста
	 */
	private function _getTextBlock($id, $turbo = false)
	{
		global $wpdb;
		$block = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_text_block WHERE `id`=" . $id);

		$this->_updateViews(array($block->id), 'ads_text_block');
		$this->_updateShows(array($block->id), 'ads_text_block');

		$template = $block->template;

		preg_match_all('#href="(.*)"#U', $template, $links);

		if(count($links) > 0){
			foreach ($links[0] as $key => $value){
				if($turbo){
					$template = preg_replace("#" . preg_quote($links[1][$key],'#') . "#U", (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . '/out-away/textblock/' . $block->id . '_' .($key+1) . '.html', $template);
				}else{
					$template = str_replace($value, '', $template);
				}
			}
		}
		if($turbo){
			$template = preg_replace('#<span( href[^>]+>[^<]+)</span>#iU', "<a$1</a>", $template);
			return '<table><tr><td>' . $template . '</td></tr></table>';
		}

		return '<!--noindex-->' . $template . '<!--/noindex-->';
	}

	/**
	 * Возвращает вид блока текста
	 */
	private function _getComment($id, $turbo = false)
	{
		global $wpdb;
		$block = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_comment WHERE `id`=" . $id);

		$this->_updateViews(array($block->id), 'ads_comment');
		$this->_updateShows(array($block->id), 'ads_comment');

		$template = $block->template;
		$template = str_replace("{{image}}", PrimeAds::$ADS_UPLOADS_URL.$block->image, $template);

		preg_match_all('#href="(.*)"#U', $template, $links);

		if(count($links) > 0)
			foreach ($links[0] as $key => $value){
				/*$template = preg_replace("#" . preg_quote($value,'#') . "#U", '/' . get_option('ads-link-hash') . '/' . md5('comment' . date('d.m.Y')) . '/' . $block->id . '_' .($key+1) . '.html', $template);*/
				$template = str_replace($value, '', $template);
			} 
				
		$folder = get_option('ads-folder');
		$template = str_replace('/prime-ads/', '/' . $folder . '/', $template);
		return '<!--noindex-->' . $template . '<!--/noindex-->';
	}

	/**
	 * Возвращает вид группы тизеров
	 */
	private function _getSimpleTizerGroup($id, $turbo = false)
	{
		global $wpdb;
		$out = '';
		$tizers_turbo = array();

		$group = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_simple_tizer_group WHERE `id`=" . $id);

		$templateObj = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_template WHERE `forigin_id`=(SELECT template from " . $wpdb->prefix . "ads_template_shows WHERE `entity`=$id order by shows limit 1) AND `type`=" . self::TYPE_SIMPLE_TIZER_GROUP);
		if(!$templateObj) return $out;

		$tizersIds = unserialize($group->tizers);
		if(!$tizersIds) $tizersIds = array();
		$tizersIds[] = 0;

		$sql = "SELECT *,stat.id as rel_id FROM " . $wpdb->prefix . "ads_simple_tizer as st LEFT JOIN " . $wpdb->prefix . "ads_simple_tizer_stat as stat ON st.forigin_id=stat.tizer WHERE `is_active`=1 AND `group`=$id AND stat.tizer IN (" . implode(',', $tizersIds) . ")";

		$has = array();
		$tizers = array();
		if($group->sticked_tizers){
			$tizers = $wpdb->get_results($sql . ' AND st.forigin_id IN (' . $group->sticked_tizers . ') ORDER BY rand() limit ' . $templateObj->num_tizers);
		}

		if(count($tizers) < $templateObj->num_tizers){
			if($tizers)
				foreach ($tizers as $tizer)
					$has[] = $tizer->rel_id;

			if(count($has) == 0) $has[] = 0;

			$def_sql = $sql;
			$sql .= ' AND stat.id NOT IN (' . implode(',',$has) . ')';

			switch ($group->rotation) {
				case self::ROTATION_RAND:
					$sql .= " ORDER BY rand()";
					break;
				case self::ROTATION_EQUALLY:
					$sql .= " ORDER BY `shows`";
					break;
				case self::ROTATION_USER:
					$viewed = isset($_SESSION['ads.simple_tizer']) ? isset($_SESSION['ads.simple_tizer']) : 0;					
					$sql .= " AND stat.id NOT IN (" . $viewed . ") ORDER BY `shows`,rand()";
					break;	
			}
			$tizers = array_merge($tizers, $wpdb->get_results($sql . ' limit ' . ($templateObj->num_tizers - count($tizers))));
		}
		
		if(count($tizers) < $templateObj->num_tizers && $group->rotation == self::ROTATION_USER){
			$has = array();
			foreach ($tizers as $tizer)
				$has[] = $tizer->rel_id;
			if(count($has) == 0) $has[] = 0;
			
			$tizers = array_merge($tizers, $wpdb->get_results($def_sql . ' AND stat.id NOT IN (' . implode(',',$has) . ') ORDER BY `shows`,rand() limit ' . ($templateObj->num_tizers - count($tizers))));
		}
		if(count($tizers) > 0){

			$template = $templateObj->template;

			$num = 1;
			//$hash_url = get_option('ads-link-hash') . '|' . md5('simptizer' . date('d.m.Y')) . '|';
			foreach ($tizers as $tizer) {
				$template = str_replace("{{image_$num}}", PrimeAds::$ADS_UPLOADS_URL.$tizer->image, $template);
				if($turbo){
					$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . '/out-away/simptizer/' . $tizer->rel_id . '_' . $group->id . '.html';
					$ttizer = (array) $tizer;
					$ttizer['url'] = $url;
					$ttizer['image'] = PrimeAds::$ADS_UPLOADS_STATIC_URL . $tizer->image;
					$tizers_turbo[] = $ttizer;
				}
				
				//$template = str_replace("{{url_$num}}", $url, $template);
				$template = str_replace("{{header_$num}}", $tizer->header, $template);
				$template = str_replace("{{text_$num}}", $tizer->text, $template);
				$template = str_replace("{{more_$num}}", $tizer->more, $template);
				$template = str_replace("{{id_$num}}", $tizer->rel_id . $group->id, $template);
				//$template = str_replace("{{group_id}}", 'group-' . $group->id, $template);
				$num++;
			}

			// удаляем с шаблона отсутствующие тизеры
			if(--$num < $templateObj->num_tizers)
				for($i = ++$num; $i <= $templateObj->num_tizers; $i++)
					$template = preg_replace('#<!--tizer-' . $i . '-->.*<!--tizer-' . $i . '-end-->#iUs', '', $template);
			
	
			$ids = array();
			foreach ($tizers as $tizer)
				$ids[] = $tizer->rel_id;

			$this->_updateViews($ids,'ads_simple_tizer_stat');
			$this->_updateShows($ids,'ads_simple_tizer_stat');
			$this->_updateShows(array($group->id), 'ads_simple_tizer_group');
			$this->_updateTemplateShows($group->id, $templateObj->forigin_id);

			if($group->rotation == self::ROTATION_USER)
				$this->_updateSession($ids, 'simple_tizer');

			$out =  $group->css . "\n" . '<!--noindex-->' . $template . '<!--/noindex-->';
		}

		if($turbo){
			$tout = '';
			foreach ($tizers_turbo as $tizer) {
				$tout .= '<table><tr><td><a href="' . $tizer['url'] . '">' . $tizer['header'] . '</a><br/>';
				$tout .= '<a href="' . $tizer['url'] . '"><img src="' . $tizer['image'] . '"/></a><br/>';
				$tout .= '<a href="' . $tizer['url'] . '">' . $tizer['text'] . '</a><br/>';
				$tout .= '<a href="' . $tizer['url'] . '">' . $tizer['dop'] . '</a></td></tr></table>';
			}
			return $tout;
		}

		$folder = get_option('ads-folder');
		$out = preg_replace('#<!--tizer-\d+-->|<!--tizer-\d+-end-->#iU', '', $out);
		$out = str_replace('/prime-ads/', '/' . $folder . '/', $out);

		return $out;
	}

	/**
	 * Возвращает объекты позиции
	 */
	private function _getPositions($turbo = false)
	{
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM " . $wpdb->prefix . "ads_position WHERE `is_active`=1 AND `position`!=3 AND `position`!=5 AND `is_turbo`=" . ($turbo ? "1" : 0) . " order by priority DESC"); // кроме позиций 'вручную' и 'фиксированный'
	}

	/**
	 * Возвращает всплывающее окно
	 */
	private function _generatePopup($positionObj, $blockData)
	{
		if($blockData == '') return '';
		$data = str_replace("{{block_data}}", $blockData, $positionObj->template);
		return $data;
	}

	/**
	 * Возвращает блок внизу экрана (мобильный блок)
	 */
	private function _generateMobileBottom($positionObj, $blockData)
	{
		if($blockData == '') return '';
		$data = str_replace("{{block_data}}", $blockData, $positionObj->template);
		if($this->screenWidthInPx)
			$width = $this->screenWidthInPx . 'px';
		else
			$width = '100%';

		$data = str_replace("{{width}}", $width, $data);
		return $data;
	}

	/**
	 * Возвращает блок с фиксацией
	 */
	private function _generateFixed($positionObj, $blockData)
	{	
		if($blockData == '') return '';
		$data = str_replace("{{block_data}}", $blockData, $positionObj->template);
		return $data;
	}

	/**
	 * Возвращает картинку с переворотом
	 */
	public static function generateFlipImage($image, $key, $positionData, $async = false)
	{
		$prefix = AdsHelper::getPrefix();
		if($positionData == '' && !$async) return $image;
		$data = '<div id="' . $prefix . '-filp-' . self::POSITION_IMAGE_FLIP . '-' . $key . '">';
		$data .= '<div id="' . $prefix . '-filp-' . self::POSITION_IMAGE_FLIP . '-' . $key . '-front" class="front">';
		$data .= $image;
		$data .= '</div><div id="' . $prefix . '-filp-' . self::POSITION_IMAGE_FLIP . '-' . $key . '-back" style="display:none;" class="back">';
		$data .= $positionData;
		$data .= '</div></div>';
		$data .= '<script type="text/javascript">
					jQuery(document).ready(function($) {
						$("#' . $prefix . '-filp-' . self::POSITION_IMAGE_FLIP . '-' . $key . '").flip({trigger:"hover"});
						$("#' . $prefix . '-filp-' . self::POSITION_IMAGE_FLIP . '-' . $key . '-back").show();
					});
				</script>';
		return $data;
	}

	/**
	 * Возвращает сслыку для вставки в блок перелинка
	 */
	private function _generatePerelinkItem($positionObj, $blockData)
	{	
		if($blockData == '') return '';

		preg_match('# id="(.*)"#iU', $blockData, $id);
		if(!$id || !isset($id[1])) return $blockData;
		preg_match('#<span.*</span>#iU', $blockData, $item);
		if($item){
			return '<li id="' . $id[1] . '"><a>' . $item[0] . '</a></li>';
		}
		return '';
	}

	/**
	 * Обновляет количество просмотров элемента
	 */
	private function _updateViews($ids, $table)
	{
		global $wpdb;
		$wpdb->query(
			"UPDATE " . $wpdb->prefix . $table . " SET `views`=`views`+1 WHERE id IN (" . implode(',',$ids) .")");
	}

	/**
	 * Обновляет количество показов элемента
	 */
	private function _updateShows($ids, $table)
	{
		global $wpdb;
		$wpdb->query(
			"UPDATE " . $wpdb->prefix . $table . " SET `shows`=`shows`+1 WHERE id IN (" . implode(',',$ids) .")");
	}

	/**
	 * Пишет данные в сессию для ротации по пользователю
	 */
	private function _updateSession($ids, $name)
	{
		if(isset($_SESSION['ads.' . $name]))
			$ids = array_unique(array_merge($ids, explode(',',$_SESSION['ads.simple_tizer'])));
		
		$_SESSION['ads.simple_tizer'] = implode(',', $ids);
	}

	/**
	 * Обновляет количество показов элемента
	 */
	private function _updateTemplateShows($entity, $template)
	{
		global $wpdb;
		$wpdb->query(
			"UPDATE " . $wpdb->prefix . "ads_template_shows SET `shows`=`shows`+1 WHERE `entity`=$entity AND `template`=$template");
	}

	/**
	 * Регистрирует переход по ссылке
	 */
	public function registerClick($table, $id, $field = 'clicks')
	{
		global $wpdb;
		if(strpos($id, '_')){
			$arr = explode('_', $id);
			$id = $arr[0];
		}
		if($table == 'ads_offer_post'){
			$clicked_posts = isset($_SESSION['ads_clicked_posts']) ? unserialize($_SESSION['ads_clicked_posts']): array();
			if(in_array($id, $clicked_posts)) return;

			$this->_registerOfferPostClick($id, $arr[1]);
			$clicked_posts[] = $id;
			$_SESSION['ads_clicked_posts'] = serialize($clicked_posts);
		}
		if($table == 'ads_simple_tizer') $table .= '_stat';
		$wpdb->query(
			"UPDATE " . $wpdb->prefix . $table . " SET `$field`=`$field`+1 WHERE id=" . $id);
	}

	/**
	 * Регистрирует переход по ссылке (Ajax)
	 */
	public function registerAjaxClick($rel)
	{
		global $wpdb;
		$field = 'clicks';
		$rel = explode('_', $rel);
		switch ($rel[0]) {
		    case 'cb':
		        $table = 'ads_code_block';
		        break;
		    case 'asb':
		        $table = 'ads_adsense_block';
		        break;
		    case 'st':
		    	$table = 'ads_simple_tizer';
		    	$field = 'clicks_' . $rel[2];
		    	break;
		}
		if(!$table) return;
		$this->registerClick($table, $rel[1], $field);
	}

	/**
	 * Регистрирует закрытие блока (Ajax)
	 */
	public function registerAjaxClose($id)
	{
		if(!isset($_SESSION['ads.closed']))
			$_SESSION['ads.closed'] = array();
		
		$_SESSION['ads.closed'][] = $id;
		$_SESSION['ads.closed'] = array_unique($_SESSION['ads.closed']);

		$_SESSION['ads.closed.time'][$id] = time();
	}

	/**
	 * Возвращает ссылку элемента
	 */
	public function getLink($table, $id)
	{
		global $wpdb;
		if($table == 'ads_text_block' || $table == 'ads_comment'){
			$id = explode('_', $id);
			$num = $id[1] - 1;
			$text = $wpdb->get_var(
				"SELECT template FROM " . $wpdb->prefix . $table . " WHERE id=" . intVal($id[0]));

			preg_match_all('#href="(.*)"#U', $text, $links);

			$url = isset($links[1][$num]) ? $links[1][$num] : 'http://' . $_SERVER['SERVER_NAME'];
			$url = str_replace('&amp;', '&', $url);
			if(strpos($url, 'coded:') !== false){
				$decoded = base64_decode(substr($url, 6));
				if($decoded) $url = $decoded;
			}
		}else if($table == 'ads_simple_tizer'){
			$id = explode('_', $id);
			$tizer = $wpdb->get_row(
				"SELECT * FROM " . $wpdb->prefix . $table . " WHERE `forigin_id`=(SELECT tizer FROM " . $wpdb->prefix . "ads_simple_tizer_stat WHERE id=" . intVal($id[0]) . ")");
			$group = $wpdb->get_row(
				"SELECT * FROM " . $wpdb->prefix . "ads_simple_tizer_group WHERE id=" . intVal($id[1]));
			$url = $tizer->url;
		}else if($table == 'ads_offer_post'){
			$id = explode('_', $id);
			$offer = $wpdb->get_row(
				"SELECT * FROM " . $wpdb->prefix . "ads_offer_post WHERE `id`=" . intVal($id[0]));
			$links = unserialize($offer->links);

			$url = isset($links[intVal($id[1])]) ? $links[intVal($id[1])] : 'не задана';
		}else{
			$url = $wpdb->get_var(
				"SELECT url FROM " . $wpdb->prefix . $table . " WHERE `id`=" . intVal($id));
		}

		$name = explode('.', str_replace(['http://','https://'], '', $_SERVER['SERVER_NAME']));
		$site = $name[0] != 'www' ? $name[0] : $name[1];

		if($url == 'не задана') return 'http://' . $_SERVER['SERVER_NAME'];
		if($urlArray = unserialize($url)){
			$linksArr = array_flip($urlArray);
			ksort($linksArr);
			$link = array_shift($linksArr);
			$urlArray[$link]++;
			if($table == 'ads_text_block' || $table == 'ads_comment'){
				if(isset($links[1][$num])){
					$text = str_replace($links[1][$num], 'coded:' . base64_encode(serialize($urlArray)), $text);
					$wpdb->update($wpdb->prefix .$table, array('template' => $text), array('id' => intVal($id[0])));
				}
			}else{
				$wpdb->update($wpdb->prefix .$table, array('url' => serialize($urlArray)), array('forigin_id' => $tizer->forigin_id));
			}
			$url = $link;
		}
		if($table == 'ads_simple_tizer' || isset($group))
			$url = str_replace('{pos}', $group->pos . '.' . $group->id, $url);

		require_once 'Mobile_Detect.php';
		$detect = new \Mobile_Detect;

		$deviceType = $detect->isMobile() ? 'mob' : 'desk';
		return str_replace(array('{site}','{purl}','{devtype}'), array($site, $this->postSlug, $deviceType), $url);
		
	}

	public function setScreenWidth($width)
	{
		if($width < 360) $this->screenWidth = array('<360');
		if($width >= 360 && $width < 600) $this->screenWidth = array('360-600');
		if($width >= 600 && $width < 768) $this->screenWidth = array('600-767');
		if($width >= 768 && $width < 1024) $this->screenWidth = array('768-1023');
		if($width >= 1024) $this->screenWidth = array('>1024');
		if($width >= 1280) $this->screenWidth[] = '>1280';
		if($width >= 1440) $this->screenWidth[] = '>1440';
		if($width >= 1920) $this->screenWidth[] = '>1920';
	}

	public function setDevice()
	{
		require_once 'Mobile_Detect.php';
		$detect = new \Mobile_Detect;

		if($detect->isMobile() && !$detect->isTablet()) $this->device[] = 'mobile';
		if($detect->isTablet()) $this->device[] = 'tablet';
		if(!$detect->isMobile()) $this->device[] = 'desktop';
	}

	public function setReferrerDomain()
	{
		$referrer = $_SERVER['HTTP_REFERER'];
		if($referrer){
			preg_match('#https*:\/.*[\.\/]([^\.]+\.[^\.\/\?]+)#i', $referrer, $referrerDomain);
			if(isset($referrerDomain[1])) $this->referrerDomain = $referrerDomain[1];
		}
	}

	public function getPostAndCat()
	{
		$url = wp_get_referer();
		$postId = url_to_postid($url); // ищем средствами wordpress

		if(!$postId){ // по имени поста
			$url = trim($url, '/');
			$url = substr($url, strrpos($url, '/') + 1);
			$parts = explode('.', $url);
			$url = $parts[0];
			$query = new \WP_Query(array('name' => $url));
	        if (!empty( $query->posts ) && $query->is_singular)
	            $postId = $query->post->ID;
		}

		if($postId){
			$this->postId = $postId;
			$category = get_the_category($postId); 
			if($category && isset($category[0]))
			    $this->catId = $category[0]->cat_ID;
			$this->getParentsOfCat();
			$tags = get_the_tags($postId);
			if($tags) foreach($tags as $tag) $this->tags[] = $tag->term_id;

			$post = get_post($postId);
			if($post){
				$this->postLength = mb_strlen(strip_tags($post->post_content));
				$this->postSlug = $post->post_name;
			}
		}
	}

	/**
	 * Возвращает транслитерацию строки
	 */
	private function _translit($str) 
	{
	    $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');
	    $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', '-');
	    return str_replace($rus, $lat, $str);
	}

	public static function getPrefix()
	{
		$name = explode('.', str_replace(['http://','https://'], '', $_SERVER['SERVER_NAME']));
        $prefix = $name[0] != 'www' ? $name[0] : $name[1];
        if(is_numeric(mb_substr($prefix, 0,1))) $prefix = 'p' . $prefix;
        return $prefix;
	}

	public function getParentsOfCat()
	{
		if(!$this->catId) return;
		$parents = $this->_getCatParent($this->catId, array());
		if(count($parents) > 0) $this->catParents = $parents;
	}

	private function _getCatParent($catId, $parents)
	{
		$cat = get_category($catId);
		if(!$cat) return $parents;
		if($cat->parent == 0) return $parents;
		$parents[] = $cat->parent;
		return $this->_getCatParent($cat->parent, $parents);
	}

	public function prepareOfferContent($content)
	{
		global $wpdb;
		$offer = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_offer_post WHERE `post_id`=" . $this->postId . " limit 1");
		if(!$offer) return $content;

		if($offer->no_ads) $this->notIncludeAds = true;

		$replacements = unserialize($offer->replacements);
		if(!$replacements) return $content;

		require_once 'Mobile_Detect.php';
		$detect = new \Mobile_Detect;

		$deviceType = $detect->isMobile() ? 'mob' : 'desk';
		$name = explode('.', str_replace(['http://','https://'], '', $_SERVER['SERVER_NAME']));
		$site = $name[0] != 'www' ? $name[0] : $name[1];

		foreach ($replacements as $macros => $replace) {
			$replace = preg_replace("#\{link_(\d+)\}#i", '/' . get_option('ads-link-hash') . '/' . md5('post' . date('d.m.Y')) . '/' . $offer->id . "_$1.html", $replace);
			$replace = str_replace(array('{site}','{purl}','{devtype}'), array($site, $this->postSlug, $deviceType), $replace);
			$content = str_replace($macros, $replace, $content);
		}

		$this->_updateViews(array($offer->id), 'ads_offer_post');

		return $content.$script;
	}

	private function _registerOfferPostClick($id, $link_num)
	{
		global $wpdb;
		$offer = $wpdb->get_row(
			"SELECT * FROM " . $wpdb->prefix . "ads_offer_post WHERE `id`=" . $id);
		if(!$offer) return $content;

		$replacements = unserialize($offer->replacements);
		if(!$replacements) return $content;

		$clicks_macros = unserialize($offer->clicks_macros);
		if(!$clicks_macros) $clicks_macros = array();

		foreach ($replacements as $macros => $replace) {
			if(strpos($replace, '{link_' . $link_num . '}') === false) continue;
			if(!isset($clicks_macros[$macros]))
				$clicks_macros[$macros] = 0;

			$clicks_macros[$macros]++;
			$wpdb->update($wpdb->prefix . "ads_offer_post", array('clicks_macros' => serialize($clicks_macros)), array('id' => $id));
		}
	}
}

?>