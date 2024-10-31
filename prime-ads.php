<?php
/*
    Plugin Name: Prime Ads
    Description: Вывод рекламы с сервиса Prime Ads
    Version: 2.3.5
    Author: Proffit
    Author URI: http://proffit.guru/
    Plugin URI: https://wordpress.org/plugins/prime-ads/
 */

namespace AdsNS;

use AdsNS\Services\MvcHelper;
use AdsNS\Services\AdsHelper;
use AdsNS\Services\Includer;
use AdsNS\Services\WpHelper;
use AdsNS\Services\Migration;
use AdsNS\Services\ApiHandler;
use AdsNS\SxGeo\SxGeo;

define('ADS_VERSION', '2.3.5');
define('ADS_DB_VERSION', '2.3.3');
define('ADS_PATH', realpath(__DIR__));
define('ADS_SERVICE_URL', get_option('ads-service-url', 'http://primeads.ru/'));

require_once ADS_PATH . '/inc/autoloader.php';
require_once ADS_PATH . '/inc/SxGeo/SxGeo.php';
require_once ADS_PATH . '/inc/install.php';
require_once ADS_PATH . '/inc/widget.php';
require_once ADS_PATH . '/inc/metabox.php';

class PrimeAds extends MvcHelper {

    private $_adsHelper;
    public static $ADS_UPLOADS;
    public static $ADS_UPLOADS_URL;
    public static $ADS_UPLOADS_STATIC;
    public static $ADS_UPLOADS_STATIC_URL;

    /**
     * Инициализация плагина. Подключение к событиям
     */
    public function initForWP() 
    {
        if (function_exists('register_activation_hook') and function_exists('register_deactivation_hook')) {
            register_activation_hook(__FILE__, '\AdsNS\Install');
            register_deactivation_hook(__FILE__, '\AdsNS\Uninstall');
        }

        Migration::migrate();

        add_action('init', array(&$this, 'session'), 1);
        add_action('init', array(&$this, 'pluginInit'), 2);
        add_action('init', array(&$this, 'rewrites_init'), 1); 
        add_action('admin_menu', array(&$this, 'admin')); 
        add_action('wp_head', array(&$this, 'fakeAdsense'),0);
        add_action('wp_head', array(&$this, 'printFonts'),100);
        add_action('wp_footer', array(&$this, 'footerBlocks'),100);
        add_action('wp_footer', array(&$this, 'counterOut'));
        add_action('wp_footer', array(&$this, 'printClickScript'));
        add_action('the_content', array(&$this, 'content'),200);
        add_action('comment_form_before', array(&$this, 'beforeComments'));  
        add_action('query_vars', array(&$this, 'queryVars') );
        add_action('parse_request', array(&$this, 'parseRequest') );

        add_shortcode('primeads', array(&$this, 'primeadsShortcode'));

        add_action('widgets_init', array(&$this, 'ads_widget_init'));
        add_action('admin_notices', array(&$this, 'ads_admin_notices'));

        /*if(function_exists('add_cacheaction')){
            add_cacheaction('wpsc_cachedata', array(&$this, 'generateOrInsertSyncContent'));
            add_cacheaction('wpsc_cachedata_safety', array(&$this, 'dynamicOutputBufferTestSafety'));
        }*/
    }

    public function ads_admin_notices() {
        if(!file_exists(PrimeAds::$ADS_UPLOADS)) {
            if(!wp_mkdir_p(PrimeAds::$ADS_UPLOADS)) {
                echo '<div id="error" class="error"><p>Prime Ads: Не удалось создать каталог для загрузки. Создайте каталог вручную /wp-content/uploads/prime-ads.</p></div>';
            }
        }
        if(!file_exists(PrimeAds::$ADS_UPLOADS_STATIC)) {
            if(!wp_mkdir_p(PrimeAds::$ADS_UPLOADS_STATIC)) {
                echo '<div id="error" class="error"><p>Prime Ads: Не удалось создать каталог для загрузки. Создайте каталог вручную /wp-content/uploads/prime-ads.</p></div>';
            }
        }
    }

    public function ads_widget_init() {
        register_widget('PrimeAdsWidget');
    }

    public function rewrites_init()
    {
        $current_link_hash = get_option('ads-link-hash');
        $link_hash = md5('prime-ads' . date('d.m.Y'));
        add_rewrite_rule(
            $link_hash . '/([\w-]+)/([0-9_]+)\.html$',
            'index.php?ads_type=$matches[1]&ads_id=$matches[2]',
            'top' );
        add_rewrite_rule(
            'out-away/([\w-]+)/([0-9_]+)\.html$',
            'index.php?ads_type=$matches[1]&ads_id=$matches[2]',
            'top' );
        $ads_flushed = get_option('ads-flushed');
        if(!$ads_flushed || $ads_flushed != 'yes'){
            flush_rewrite_rules();
            $value = $ads_flushed ? $ads_flushed : 0;
            $value++;
            if($value == 5) $value = 'yes';
            update_option('ads-flushed', $value);
        }
    }

    public static function checkHash()
    {
        $current_link_hash = get_option('ads-link-hash');
        $link_hash = md5('prime-ads' . date('d.m.Y'));
        $update_scripts = false;
        if($current_link_hash != $link_hash){
            update_option('ads-link-hash', $link_hash);
            PrimeAds::generateTypes();
            PrimeAds::moveFolders();
            delete_option('ads-flushed');
            delete_option('ads-clicks-checked');
            $update_scripts = true;
        }
        $upload_dir = wp_upload_dir ();
        $folder = get_option('ads-folder');
        self::$ADS_UPLOADS = $upload_dir['basedir'] . ($folder ? '/' . $folder . '/' : '/prime-ads/');
        self::$ADS_UPLOADS_URL = $upload_dir ['baseurl'] . ($folder ? '/' . $folder . '/' : '/prime-ads/');
        self::$ADS_UPLOADS_STATIC = $upload_dir['basedir'] . '/prime-static/';
        self::$ADS_UPLOADS_STATIC_URL = $upload_dir ['baseurl'] . '/prime-static/';
        if($update_scripts) {
            ApiHandler::collectClickJs();
            if(function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
            if(function_exists('w3tc_flush_all')) 
                update_option('ads-flush-w3tc', true);
            update_option('ads-flush-wp-rocket', true);
        }
    }

    public static function checkClicksFile()
    {
        if(get_option('ads-clicks-checked')) return;
        $content = file_get_contents(PrimeAds::$ADS_UPLOADS . 'jquery.lc.min.js');
        if(!trim($content)){
            ApiHandler::collectClickJs();
        }else{
            $js = ApiHandler::collectClickJs(true);
            if($js != $content){
                ApiHandler::collectClickJs();
            }else{
                update_option('ads-clicks-checked', true);
            }
        }
    }

    /**
     * Добавлени переменных в обработчик запроса
     */
    public function queryVars( $query_vars )
    {
        $query_vars[] = 'ads_type';
        $query_vars[] = 'ads_id';
        return $query_vars;
    }

    /**
     * Обработка клика по ссылке
     */
    function parseRequest( &$wp )
    {
        $types = array('simptizer' => 'ads_simple_tizer', 'textblock' => 'ads_text_block', 'comment' => 'ads_comment', 'post' => 'ads_offer_post');
        if(!isset($types[$wp->query_vars['ads_type']])){
            $types = get_option('ads-types');
            if(!$types){
                $this->generateTypes();
                $types = get_option('ads-types');
            }
        }

        if (array_key_exists('ads_type', $wp->query_vars) &&
                array_key_exists('ads_id', $wp->query_vars)){
            $this->_adsHelper->registerClick($types[$wp->query_vars['ads_type']], $wp->query_vars['ads_id']);
            $this->_adsHelper->getPostAndCat();
            $link = $this->_adsHelper->getLink($types[$wp->query_vars['ads_type']], $wp->query_vars['ads_id']);
            if($link)
                header('Location: ' . $link);

            exit();
        }
        return;
    }

    /**
     * Инициализация сессий
     */
    public function session()
    {
        if(!session_id()) session_start();
    }

    public function printFonts()
    {
        include (ADS_PATH . '/jci/fonts.txt');
        echo json_decode(get_option('ads-header'));
    }

    /**
     * Основная инициализация плагина.
     * Подключение скриптов, создание объекта-помощника
     */
    public function pluginInit()
    {
        $onoff_state = get_option('ads-onoff');
        if(!is_admin() && $onoff_state != 'off'){
            $upload_dir = wp_upload_dir();
            $folder = get_option('ads-folder');
            wp_enqueue_script('prma-cookie', $upload_dir['baseurl'] . '/' . $folder . '/js.cookie.js', array('jquery'), "2.1.3", true);
            wp_enqueue_script('prma-script', $upload_dir['baseurl'] . '/' . $folder . '/script.js', array('jquery', 'prma-functions'), "1.0.0", false);
            wp_enqueue_script('prma-functions', $upload_dir['baseurl'] . '/' . $folder . '/functions.js', array('jquery', 'prma-cookie'), "1.0.2", false);
            wp_enqueue_script('prma-fixed', $upload_dir['baseurl'] . '/' . $folder . '/jquery-scrolltofixed.js', array('jquery'), "1.0.7", true);
            wp_enqueue_script('prma-flip', $upload_dir['baseurl'] . '/' . $folder . '/jquery.flip.min.js', array('jquery'), "1.0.20", true);
            wp_enqueue_script('prma-iframetracker', $upload_dir['baseurl'] . '/' . $folder . '/jquery.iframetracker.js', array('jquery'), "1.1.0", true);
            
            //wp_localize_script('prma-functions', 'prma_ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'prma_ajax_url' => $upload_dir['baseurl'] . '/' . $folder . '/direct.php'));
            
            wp_localize_script('prma-functions', 'prma_ajax_object', array('ajax_url' => $upload_dir['baseurl'] . '/' . $folder . '/direct.php', 'prma_ajax_url' => $upload_dir['baseurl'] . '/' . $folder . '/direct.php'));

            wp_enqueue_style('prma-style', $upload_dir['baseurl'] . '/' . $folder . '/style.css', array(), "1.0.0", false);
        }

        add_action('wp_ajax_prma_regclick', array(&$this, 'regClick'));
        add_action('wp_ajax_nopriv_prma_regclick', array(&$this, 'regClick'));
        add_action('wp_ajax_prma_regclose', array(&$this, 'regClose'));
        add_action('wp_ajax_nopriv_prma_regclose', array(&$this, 'regClose'));
        add_action('wp_ajax_prma_load_positions', array(&$this, 'loadPositions'));
        add_action('wp_ajax_nopriv_prma_load_positions', array(&$this, 'loadPositions'));
        add_action('wp_ajax_prma_load_block', array(&$this, 'loadBlock'));
        add_action('wp_ajax_nopriv_prma_load_block', array(&$this, 'loadBlock'));
        add_action('wp_ajax_prma_counter_tick', array(&$this, 'counterTick'));
        add_action('wp_ajax_nopriv_prma_counter_tick', array(&$this, 'counterTick'));

        $this->_adsHelper = new AdsHelper();

        $SxGeo = new SxGeo(ADS_PATH . '/inc/SxGeo/SxGeoCity.dat'); 
        if($geo = $SxGeo->getCityFull($_SERVER['REMOTE_ADDR'])){
        	$this->_adsHelper->country = $geo['country']['id'];
        	$this->_adsHelper->region = $geo['region']['id'];
        	$this->_adsHelper->city = $geo['city']['id'];
        }
        unset($SxGeo);
    }

    /**
     * Обработка клика на элементе без ссылки, переданного Ajax
     */
    public function regClick()
    {
        $this->_adsHelper->registerAjaxClick($_POST['rel']);
        wp_die();
    }

    /**
     * Обработка клика на элементе без ссылки, переданного Ajax
     */
    public function regClose()
    {
        $this->_adsHelper->registerAjaxClose($_POST['id']);
        wp_die();
    }

    /**
     * Асинхронная загрузка позиций Ajax
     */
    public function loadPositions()
    {
        if(strpos($_SERVER['HTTP_REFERER'], 'iframe-toloka.com')){
            return;
        }
        $this->_adsHelper->setScreenWidth(intVal($_POST['width']));
        $this->_adsHelper->setDevice();
        $this->_adsHelper->getPostAndCat();
        $this->_adsHelper->screenWidthInPx = intVal($_POST['width']);
        $this->_adsHelper->adblockDetected = intVal($_POST['adblockDetected']);
        $this->_adsHelper->setReferrerDomain();
        echo json_encode($this->_adsHelper->generatePositionContentByPositionId($_POST['id']));
        $this->_adsHelper->screenWidth = false;
        $this->_adsHelper->screenWidthInPx = false;

        if(!defined('DIRECT_AJAX')) wp_die();
    }

    /**
     * Асинхронная загрузка блока Ajax
     */
    public function loadBlock()
    {
        if(strpos($_SERVER['HTTP_REFERER'], 'iframe-toloka.com')){
            return;
        }
    	if(intVal($_POST['id']) > 0){
    		$this->_adsHelper->setScreenWidth(intVal($_POST['width']));
            $this->_adsHelper->setDevice();
            $this->_adsHelper->getPostAndCat();
            $this->_adsHelper->setReferrerDomain();
            echo json_encode(
            	array(
            		'block_data' => $this->_adsHelper->getBlockInPosition(intVal($_POST['id']), intVal(substr($_POST['exclude'], strrpos($_POST['exclude'], '-')+1)))
                    )
            	);
            $this->_adsHelper->screenWidth = false;
        }
        
        if(!defined('DIRECT_AJAX')) wp_die();
    }

    /**
     * Вывод страницы в админке
     */
    public function admin() 
    {
        if (function_exists('add_menu_page')) {
            add_options_page('Prime Ads', 'Prime Ads', 8, 'primeads', array(&$this, 'admin_ads'));
        }
    }
    
    /**
     * Обработка действий в админке
     */
    public function admin_ads()
    {
        $_GET['controller'] = isset($_GET['controller']) ? $_GET['controller'] : 'admin';
        $_GET['action'] = isset($_GET['action']) ? $_GET['action'] : 'index';
      
        $this->mvc($_GET['controller'], $_GET['action']);
    }

    /**
     * Обработчик вывода контента
     */
    public function content($content)
    {
    	global $post;
        $onoff_state = get_option('ads-onoff');
        if($onoff_state == 'off') return $content;
    	if($post->post_type != 'post') return $content;

        if(strpos($_SERVER['HTTP_REFERER'], 'iframe-toloka.com'))
            return $content;

        $this->_adsHelper->postId = get_the_ID();
        $this->_adsHelper->postSlug = $post->post_name;

        if(get_post_meta(get_the_ID(), 'is_offer_post', true))
            $content = $this->_adsHelper->prepareOfferContent($content);

        if($this->_adsHelper->notIncludeAds) return $content;

        $category = get_the_category(); 
        if($category && isset($category[0]))
            $this->_adsHelper->catId = $category[0]->cat_ID; 
        $this->_adsHelper->getParentsOfCat();
        
        $tags = get_the_tags();
        if($tags) foreach($tags as $tag) $this->_adsHelper->tags[] = $tag->term_id;

        $this->_adsHelper->setDevice();
        $this->_adsHelper->setReferrerDomain();

    	$this->_adsHelper->postLength = mb_strlen(strip_tags($content));
    	$this->_adsHelper->preparePositions();

    	$includer = new Includer();
    	return $includer->doInclude($this->_adsHelper->positions, $content);
    }

    /**
     * Обработчик вывода контента ленты Turbo
     */
    public function turboContent($content)
    {
        global $post;
        $onoff_state = get_option('ads-onoff');
        if($onoff_state == 'off') return $content;
        if($post->post_type != 'post') return $content;

        $this->_adsHelper->postId = get_the_ID();
        $this->_adsHelper->postSlug = $post->post_name;

        if(get_post_meta(get_the_ID(), 'is_offer_post', true))
            $content = $this->_adsHelper->prepareOfferContent($content);

        if($this->_adsHelper->notIncludeAds) return $content;

        $category = get_the_category(); 
        if($category && isset($category[0]))
            $this->_adsHelper->catId = $category[0]->cat_ID; 
        $this->_adsHelper->getParentsOfCat();
        
        $tags = get_the_tags();
        if($tags) foreach($tags as $tag) $this->_adsHelper->tags[] = $tag->term_id;

        $this->_adsHelper->postLength = mb_strlen(strip_tags($content));
        $this->_adsHelper->preparePositions(true);

        $includer = new Includer();
        return $includer->doInclude($this->_adsHelper->positions, $content);
    }

    public function getRsayIds()
    {
        global $wpdb;
        $blocks = $wpdb->get_results(
            "SELECT cb.* FROM " . $wpdb->prefix . "ads_code_block as cb left join " . $wpdb->prefix . "ads_position as p on cb.position=p.id WHERE `is_turbo`=1");
        if(!$blocks) return array();
        $ids = array();

        foreach ($blocks as $block) {
            if(!preg_match('#R-A-\d{6}-\d+#i', $block->template, $blockId)) continue;
            $ids[] = $blockId[0];
        }
        $ids = array_unique($ids);
        $this->_adsHelper->rsyaIds = $ids;
        return $ids;
    }

    /**
     * Блоки перед комментариями
     */
    public function beforeComments()
    {
        $onoff_state = get_option('ads-onoff');
        if($onoff_state == 'off') return;
        if($this->_adsHelper->notIncludeAds) return;
        if(strpos($_SERVER['HTTP_REFERER'], 'iframe-toloka.com')) return;

        if(isset($this->_adsHelper->positions[AdsHelper::POSITION_BEFORE_COMMENTS]))
            foreach ($this->_adsHelper->positions[AdsHelper::POSITION_BEFORE_COMMENTS] as $key => $value){ 
                if($value['async']){
                    echo '<div class="prma-position-pointer" data-id="' . $value['id'] . '" data-place="' . AdsHelper::POSITION_BEFORE_COMMENTS . '"></div>';
                }else{
                    echo $value['position_data'];
                }
            }
    }

    /**
     * Генерация хешей для типов элементов ссылок
     */
    public static function generateTypes()
    {
        $types = array('simptizer' => 'ads_simple_tizer', 'textblock' => 'ads_text_block', 'comment' => 'ads_comment', 'post' => 'ads_offer_post');
        $generated = array();
        foreach ($types as $type => $value) {
            $generated[md5($type . date('d.m.Y'))] = $value;
        }
        update_option('ads-types', $generated);
    }

    /**
     * Меняем названия папок скриптов, стилей и каритонок
     */
    public static function moveFolders()
    {
        $old_folder = get_option('ads-folder');
        $new_folder = md5('prime-ads' . date('d.m.Y'));
        $upload_dir = wp_upload_dir();
        $jci_folder = $upload_dir['basedir'] . '/' . $new_folder;
        $jci_src = ADS_PATH . '/jci';
        if(!$old_folder){
            rename($upload_dir['basedir'] . '/prime-ads', $upload_dir['basedir'] . '/' . $new_folder);
        }else{
            rename($upload_dir['basedir'] . '/' . $old_folder, $upload_dir['basedir'] . '/' . $new_folder);
        }
        $dir = opendir($jci_src); 
        @mkdir($jci_folder); 
        while(false !== ($file = readdir($dir))) { 
            if (($file != '.') && ($file != '..') && ($file != 'jquery.lc.min.js')) { 
                copy($jci_src . '/' . $file, $jci_folder . '/' . $file); 
            } 
        } 
        closedir($dir); 
        copy(ADS_PATH . '/inc/direct.php', $jci_folder . '/direct.php'); 

        update_option('ads-folder', $new_folder);
    }

    /**
     * Блоки в футер
     */
    public function footerBlocks()
    {
        $onoff_state = get_option('ads-onoff');
        if($onoff_state == 'off') return;
        if($this->_adsHelper->notIncludeAds) return;
        if(strpos($_SERVER['HTTP_REFERER'], 'iframe-toloka.com')) return;

        //unset($_SESSION['ads.closed']);
        //unset($_SESSION['ads.closed.time']);
        $closed = array();
    	if(isset($this->_adsHelper->positions[AdsHelper::POSITION_POPUP]))
    		foreach ($this->_adsHelper->positions[AdsHelper::POSITION_POPUP] as $key => $value){ 
                if(isset($_SESSION['ads.closed']) && in_array($value['id'], $_SESSION['ads.closed']) && 
                    (!isset($_SESSION['ads.closed.time'][$value['id']]) || $_SESSION['ads.closed.time'][$value['id']] > time() - 3600)) $closed[] = $value['id'];
    			if($value['async']){
    				echo '<div class="prma-position-pointer" data-id="' . $value['id'] . '" data-place="' . AdsHelper::POSITION_POPUP . '"></div>';
    			}else{
    				echo $value['position_data'];
    			}
            }
    	if(isset($this->_adsHelper->positions[AdsHelper::POSITION_MOBILE_BOTTOM]))
            foreach ($this->_adsHelper->positions[AdsHelper::POSITION_MOBILE_BOTTOM] as $key => $value){
                if(isset($_SESSION['ads.closed']) && in_array($value['id'], $_SESSION['ads.closed']) && 
                    (!isset($_SESSION['ads.closed.time'][$value['id']]) || $_SESSION['ads.closed.time'][$value['id']] > time() - 3600))  $closed[] = $value['id'];
                if($value['async']){
                    echo '<div class="prma-position-pointer" data-id="' . $value['id'] . '" data-place="' . AdsHelper::POSITION_MOBILE_BOTTOM . '"></div>';
                }else{
                    echo $value['position_data'];
                }
            }
        if(isset($this->_adsHelper->positions[AdsHelper::POSITION_BEFORE_FIRST_COMMENT_CACKLE]))
            foreach ($this->_adsHelper->positions[AdsHelper::POSITION_BEFORE_FIRST_COMMENT_CACKLE] as $key => $value){
                if($value['async']){
                    echo '<div class="prma-position-pointer" data-id="' . $value['id'] . '" data-place="' . AdsHelper::POSITION_MOBILE_BOTTOM . '"></div>';
                }
            }
        if(isset($this->_adsHelper->positions[AdsHelper::POSITION_AFTER_CACKLE_COMMENTS]))
            foreach ($this->_adsHelper->positions[AdsHelper::POSITION_AFTER_CACKLE_COMMENTS] as $key => $value){
                if($value['async']){
                    echo '<div class="prma-position-pointer" data-id="' . $value['id'] . '" data-place="' . AdsHelper::POSITION_MOBILE_BOTTOM . '"></div>';
                }
            }
    }

    public function counterOut()
    {
        global $post;
        if(is_single() && $post && $post->post_type == 'post'){
            echo '<script type="text/javascript">counterTick("post");</script>';
        }else{
            echo '<script type="text/javascript">counterTick("allpages");</script>';
        }
    }

    public function counterTick()
    {
        if($_POST['type'] == 'post'){
            $counter_total = get_option('counter_total', 0);
            update_option('counter_total', ++$counter_total);

            $counter_current = get_option('counter_current',0);
            update_option('counter_current', ++$counter_current);
        }

        $counter_total = get_option('counter_allpages_total', 0);
        update_option('counter_allpages_total', ++$counter_total);

        $counter_current = get_option('counter_allpages_current',0);
        update_option('counter_allpages_current', ++$counter_current);
        
        wp_die();
    }

    /**
     * Обработка шорткода
     */
    public function primeadsShortcode($atts) 
    {
        global $post;
        $onoff_state = get_option('ads-onoff');
        if($onoff_state == 'off') return;

        if(array_key_exists('pos', $atts) && intVal($atts['pos']) > 0){
            if($post){
                $category = get_the_category(); 
                if($category && isset($category[0]))
                    $this->_adsHelper->catId = $category[0]->cat_ID; 
                $this->_adsHelper->getParentsOfCat();
                $this->_adsHelper->postId = get_the_ID();
                $tags = get_the_tags();
                if($tags) foreach($tags as $tag) $this->_adsHelper->tags[] = $tag->term_id;

                $this->_adsHelper->postSlug = $post->post_name;

                $this->_adsHelper->postLength = mb_strlen(strip_tags($content));
            }
            return str_replace("</scr'+'ipt>'", "<\/scr'+'ipt>'", $this->_adsHelper->generatePositionContentByPositionId(intVal($atts['pos']), true));
        }         
    }

    public function fakeAdsense()
    {
        if(file_exists(PrimeAds::$ADS_UPLOADS . 'gads.js')){
            echo '<script type="text/javascript" src="' . PrimeAds::$ADS_UPLOADS_URL . 'gads.js' . '"></script>' . "\n";
            echo '<script type="text/javascript">var a' .  mt_rand(2145, 2954) . '; gads(); var b' . mt_rand(5105, 5853) . ';</script>' . "\n";
        }
    }

    public function printClickScript()
    {
        if(file_exists(PrimeAds::$ADS_UPLOADS . 'jquery.lc.min.js')){
            echo '<script>(function(){i=document.createElement("script");i.type="text/javascript";i.async="true";i.src="' . PrimeAds::$ADS_UPLOADS_URL . 'jquery.lc.min.js";var s=document.getElementsByTagName("body")[0];s.appendChild(i);})();</script>' . "\n";
        }
        echo json_decode(get_option('ads-footer'));
    }

    public function generateOrInsertSyncContent($cachedata = 0)
    {
        if ($cachedata === 0) {
            define('DYNAMIC_OB_TEXT', true);
        }else{
            preg_match_all('#POSITION_(\d+)_(\d+)_([0|1])#iU', $cachedata, $positions);
            if($positions){
                $category = get_the_category(); 
                if($category && isset($category[0]))
                    $this->_adsHelper->catId = $category[0]->cat_ID; 
                $this->_adsHelper->getParentsOfCat();
                $tags = get_the_tags();
                if($tags) foreach($tags as $tag) $this->_adsHelper->tags[] = $tag->term_id;
                $this->_adsHelper->setDevice();
                $this->_adsHelper->postLength = mb_strlen(strip_tags($content));

                foreach ($positions[2] as $n => $pId) {
                    $data = $this->_adsHelper->generatePositionContentByPositionId($pId, false, $positions[3][$n]);
                    $cachedata = str_replace('<!-- POSITION_' . $positions[1][$n] . '_' . $pId . '_' . $positions[3][$n] . ' -->', $data, $cachedata);
                }
            }
            return $cachedata;
        }
    }
    
    public function dynamicOutputBufferTestSafety($safety) 
    {
        return 1;
    }
}

$primeAds = new PrimeAds();
PrimeAds::checkHash();

if(get_option('ads-onoff') != 'off') PrimeAds::checkClicksFile();

if(get_option('ads-flush-w3tc')){
    add_action('plugins_loaded', 'w3tc_flush_all', 100);
    add_action('plugins_loaded', 'w3tc_pgcache_flush', 100);
    add_action('plugins_loaded', 'w3tc_flush_posts', 100);
    add_action('plugins_loaded', 'w3tc_objectcache_flush', 100);
    add_action('plugins_loaded', 'ads_w3tc_flush', 101);
    delete_option('ads-flush-w3tc');
    ads_w3tc_flush();
}

if(get_option('ads-flush-wp-rocket')){
    add_action('plugins_loaded', function(){
        if(function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            rocket_clean_minify();
            rocket_clean_cache_busting();

            $options                   = get_option( WP_ROCKET_SLUG );
            $options['minify_css_key'] = create_rocket_uniqid();
            $options['minify_js_key']  = create_rocket_uniqid();
            remove_all_filters( 'update_option_' . WP_ROCKET_SLUG );
            update_option( WP_ROCKET_SLUG, $options );

            rocket_dismiss_box( 'rocket_warning_plugin_modification' );
        }
    }, 100);
    delete_option('ads-flush-wp-rocket');
}

function ads_w3tc_flush(){
    w3tc_flush_all();
    w3tc_flush_posts();
    $w3 = \W3TC\Dispatcher::component( 'CacheFlush' );
    $w3->dbcache_flush();
    $w3->minifycache_flush();
    $w3->objectcache_flush();
    $w3->opcache_flush();
    $w3->browsercache_flush();
    $pgcache_cleanup = \W3TC\Dispatcher::component( 'PgCache_Plugin_Admin' );
    $pgcache_cleanup->cleanup();
    $pgcacheflush = \W3TC\Dispatcher::component( 'PgCache_Flush' );
    $pgcacheflush->flush();
    $pgcacheflush->flush_post_cleanup();
}

if(isset($_SERVER['REQUEST_URI']) and strpos($_SERVER['REQUEST_URI'], '/primeads_api') === 0){
    define('ADS_CRON', true);
    require( ABSPATH . WPINC . '/pluggable.php' );
    
    $primeAds->mvc('api', $_GET['action']);
    exit();
}else{
    $primeAds->initForWP();
}
