<?php
namespace AdsNS\Controllers;

use AdsNS\Services\ApiHandler;
use AdsNS\Services\WpHelper;

class ApiController {

    public function init(){
        if(!$this->_checkAccessKey()){
           $this->outWrongKeyMess();   
           return false;
        }
        return true;
    }

    private function _checkAccessKey(){
        $key = get_option("ads_access_key");
        if(!$key || $key == '') return false;

        $checksums = array(
            md5($key . date('d', time() - 43000)),
            md5($key . date('d')),
            md5($key . date('d', time() + 43000))
        );
        return in_array($_REQUEST['key'], $checksums);
    }

    private function outWrongKeyMess(){
        echo json_encode(array('result' => 'error', 'error' => 'Несоответствие ключа или ключ не задан!'));
    }

    public function check_accessAction(){
        $ret = array('result' => 'ok', 'ver' => ADS_VERSION);
        echo json_encode($ret);
        
        return false;
    }

    public function get_statsAction(){
        ApiHandler::getStats();  
        return false;
    }

    public function reset_statsAction(){
        ApiHandler::resetStats();  
        return false;
    }

    public function update_simple_tizerAction(){
        $data = $_REQUEST['data'];
        if($data['image'] != '')
            ApiHandler::uploadImg($data['image']);

        ApiHandler::updateTizer();   
        return false;
    }

    public function delete_simple_tizerAction(){

        ApiHandler::delete('ads_simple_tizer', array('forigin_id' => $_REQUEST['id']));   
        return false;
    }

    public function update_simple_tizer_groupAction(){
        ApiHandler::updateData('ads_simple_tizer_group', true);   
        return false;
    }

    public function delete_simple_tizer_groupAction(){
        ApiHandler::delete('ads_simple_tizer_group', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_code_blockAction(){
        ApiHandler::updateData('ads_code_block', true);   
        return false;
    }

    public function delete_code_blockAction(){
        ApiHandler::delete('ads_code_block', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_adsense_blockAction(){
        ApiHandler::updateData('ads_adsense_block', true);  
        return false;
    }

    public function delete_adsense_blockAction(){
        ApiHandler::delete('ads_adsense_block', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_text_blockAction(){
        ApiHandler::updateData('ads_text_block', true);   
        return false;
    }

    public function delete_text_blockAction(){
        ApiHandler::delete('ads_text_block', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_commentAction(){
        $data = $_REQUEST['data'];
        if($data['image'] != '')
            ApiHandler::uploadImg($data['image']);

        ApiHandler::updateData('ads_comment', true);   
        return false;
    }

    public function delete_commentAction(){
        ApiHandler::delete('ads_comment', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_positionAction(){
        ApiHandler::updateData('ads_position');   
        return false;
    }

    public function delete_positionAction(){
        ApiHandler::delete('ads_position', array('id' => $_REQUEST['id']));   
        return false;
    }

    public function update_templateAction(){
        ApiHandler::updateTemplate();   
        return false;
    }

    public function delete_templateAction(){
        ApiHandler::delete('ads_template', array('forigin_id' => $_REQUEST['id'], 'type' => $_REQUEST['type']));    
        return false;
    }

    public function update_offer_postAction(){
        ApiHandler::updateData('ads_offer_post', true);   
        return false;
    }

    public function get_site_dataAction(){
        WpHelper::getSiteData();
        return false;
    }

    public function toggle_adsAction(){
        $state = $_REQUEST['state'];
        $ret = WpHelper::toggleAds($state);

        echo json_encode($ret);
        
        return false;
    }

    public function update_pluginAction(){
        ApiHandler::updatePlugin();   
        return false;
    }

    public function clear_cacheAction(){
        if(function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
        if(function_exists('w3tc_flush_all')) 
            update_option('ads-flush-w3tc', true);
        update_option('ads-flush-wp-rocket', true);
        
        echo json_encode(array('result' => 'ok', 'ver' => ADS_VERSION));
        return false;
    }

    public function get_offer_postsAction(){
        $ret = WpHelper::getOfferPosts();

        echo json_encode($ret);
        
        return false;
    }

    public function header_footer_dataAction(){
        $header = $_REQUEST['header'];
        $footer = $_REQUEST['footer'];
        update_option('ads-header', $header);
        update_option('ads-footer', $footer);

        $ret = array('result' => 'ok', 'ver' => ADS_VERSION);
        echo json_encode($ret);
        
        return false;
    }
}
