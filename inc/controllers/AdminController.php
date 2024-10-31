<?php

namespace AdsNS\Controllers;
use AdsNS\Services\ApiHandler;

class AdminController{

    public function indexAction() {
        global $wpdb;
        $params = array(
            "ads_access_key" => get_option("ads_access_key"),
        );
        
        if($_POST['submit-key']){
            foreach($params as $k => $v){
                if(isset($_POST[$k])){
                    $value = stripslashes_deep($_POST[$k]);
                    update_option($k, $value);
                    $params[$k] = $value;
                }
            }
            if(!$params['ads_access_key']){
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_adsense_block SET `is_active`=-1 WHERE `is_active`=0");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_code_block SET `is_active`=-1 WHERE `is_active`=0");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_text_block SET `is_active`=-1 WHERE `is_active`=0");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_simple_tizer SET `is_active`=-1 WHERE `is_active`=0");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_comment SET `is_active`=-1 WHERE `is_active`=0");
            }else{
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_adsense_block SET `is_active`=0 WHERE `is_active`=-1");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_code_block SET `is_active`=0 WHERE `is_active`=-1");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_text_block SET `is_active`=0 WHERE `is_active`=-1");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_simple_tizer SET `is_active`=0 WHERE `is_active`=-1");
                $wpdb->query("UPDATE " . $wpdb->prefix . "ads_comment SET `is_active`=0 WHERE `is_active`=-1");
            }
        }

        if(!$params['ads_access_key']){
            
            $params['adsenses'] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_adsense_block WHERE `is_active`!=-1");
            $params['codes'] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_code_block WHERE `is_active`!=-1");
            $params['texts'] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_text_block WHERE `is_active`!=-1");
            $params['tizers'] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_simple_tizer WHERE `is_active`!=-1");
            $params['comments'] = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_comment WHERE `is_active`!=-1");

            $positions = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_position");
            $params['positions'] = [];
            if($positions){
                foreach ($positions as $p) {
                    $params['positions'][$p->id] = $p->name;
                }
            }

            $cats = get_categories();
            $params['cats'] = [];
            if($cats){
                foreach ($cats as $cat) {
                    $params['cats'][$cat->term_id] = $cat->name;
                }
            }

            $groups = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "ads_simple_tizer_group");
            $params['groups'] = [];
            if($groups){
                foreach ($groups as $g) {
                    $g->tizers = unserialize($g->tizers);
                    if(!$g->tizers) continue;
                    foreach ($g->tizers as $tizerId) {
                        if(isset($params['groups'][$tizerId]))
                            $params['groups'][$tizerId] .= ', ' . $g->name;
                        else
                            $params['groups'][$tizerId] = $g->name;
                    }
                }
            }
        }

        if($_POST['submit']){
            if($params['adsenses']){
                foreach ($params['adsenses'] as $item) {
                    $arrValue = $_POST['adsense'][$item->id];
                    $write = false;
                    if($arrValue['pub_id'] != $arrValue['new_pub_id'] || $arrValue['slot_id'] != $arrValue['new_slot_id']){
                        $item->js = str_replace(array($arrValue['pub_id'], $arrValue['slot_id']), array($arrValue['new_pub_id'], $arrValue['new_slot_id']), $item->js);
                        $item->pub_id = $arrValue['new_pub_id'];
                        $item->slot_id = $arrValue['new_slot_id'];
                        $write = true;
                    }
                    if($arrValue['is_active'] && !isset($arrValue['new_is_active'])){
                        $item->is_active = 0;
                        $write = true;
                    }
                    if(!$arrValue['is_active'] && isset($arrValue['new_is_active'])){
                        $item->is_active = 1;
                        $write = true;
                    }
                    if($write)
                        $wpdb->replace($wpdb->prefix . "ads_adsense_block", (array)$item);
                }
            }
            if($params['codes']){
                foreach ($params['codes'] as $item) {
                    $arrValue = $_POST['code'][$item->id];
                    $write = false;
                    if($arrValue['code'] != $item->template){
                        $item->template = $arrValue['code'];
                        $write = true;
                    }
                    if($arrValue['is_active'] && !isset($arrValue['new_is_active'])){
                        $item->is_active = 0;
                        $write = true;
                    }
                    if(!$arrValue['is_active'] && isset($arrValue['new_is_active'])){
                        $item->is_active = 1;
                        $write = true;
                    }
                    if($write)
                        $wpdb->replace($wpdb->prefix . "ads_code_block", (array)$item);
                }
            }
            if($params['texts']){
                foreach ($params['texts'] as $item) {
                    $arrValue = $_POST['text'][$item->id];
                    $write = false;

                    preg_match_all('#href="(.*)"#U', $item->template, $links);
                    if($links[1]){
                        foreach ($links[1] as $n => $link) {
                            $source = $link;
                            if(strpos($link, 'coded:') !== false){
                                $decoded = base64_decode(substr($link, 6));
                                if($decoded) $link = $decoded;
                            }  
                            
                            if(!$link) continue;
                            if($urlArray = unserialize($link)){
                                foreach (array_keys($urlArray) as $i => $url) {
                                    if($arrValue[$n][$i]['was'] != $arrValue[$n][$i]['new']){
                                        unset($urlArray[$url]);
                                        $urlArray[$arrValue[$n][$i]['new']] = 0;
                                        $write = true;
                                    }  
                                }
                                if($write){
                                    $item->template = str_replace($source, 'coded:' . base64_encode(serialize($urlArray)), $item->template);
                                }
                            }else{
                                if($arrValue[$n]['was'] != $arrValue[$n]['new']){
                                    $item->template = str_replace($arrValue[$n]['was'], $arrValue[$n]['new'], $item->template);
                                    $write = true;
                                }
                            }
                        }
                    }

                    if($arrValue['is_active'] && !isset($arrValue['new_is_active'])){
                        $item->is_active = 0;
                        $write = true;
                    }
                    if(!$arrValue['is_active'] && isset($arrValue['new_is_active'])){
                        $item->is_active = 1;
                        $write = true;
                    }
                    if($write)
                        $wpdb->replace($wpdb->prefix . "ads_text_block", (array)$item);
                }
            }
            if($params['tizers']){
                foreach ($params['tizers'] as $item) {
                    $arrValue = $_POST['tizer'][$item->id];
                    $write = false;

                    $link = $item->url;
                    if($urlArray = unserialize($link)){
                        foreach (array_keys($urlArray) as $i => $url) {
                            if($arrValue[$i]['was'] != $arrValue[$i]['new']){
                                unset($urlArray[$url]);
                                $urlArray[$arrValue[$i]['new']] = 0;
                                $write = true;
                            }  
                        }
                        if($write){
                            $item->url = serialize($urlArray);
                        }
                    }else{
                        if($arrValue['was'] != $arrValue['new']){
                            $item->url =  $arrValue['new'];
                            $write = true;
                        }
                    }

                    if($arrValue['is_active'] && !isset($arrValue['new_is_active'])){
                        $item->is_active = 0;
                        $write = true;
                    }
                    if(!$arrValue['is_active'] && isset($arrValue['new_is_active'])){
                        $item->is_active = 1;
                        $write = true;
                    }
                    if($write)
                        $wpdb->replace($wpdb->prefix . "ads_simple_tizer", (array)$item);
                }
            }
            if($params['comments']){
                foreach ($params['comments'] as $item) {
                    $arrValue = $_POST['comment'][$item->id];
                    $write = false;

                    preg_match_all('#href="(.*)"#U', $item->template, $links);
                    if($links[1]){
                        foreach ($links[1] as $n => $link) {
                            $source = $link;
                            if(strpos($link, 'coded:') !== false){
                                $decoded = base64_decode(substr($link, 6));
                                if($decoded) $link = $decoded;
                            }  
                            
                            if(!$link) continue;
                            if($urlArray = unserialize($link)){
                                foreach (array_keys($urlArray) as $i => $url) {
                                    if($arrValue[$n][$i]['was'] != $arrValue[$n][$i]['new']){
                                        unset($urlArray[$url]);
                                        $urlArray[$arrValue[$n][$i]['new']] = 0;
                                        $write = true;
                                    }  
                                }
                                if($write){
                                    $item->template = str_replace($source, 'coded:' . base64_encode(serialize($urlArray)), $item->template);
                                }
                            }else{
                                if($arrValue[$n]['was'] != $arrValue[$n]['new']){
                                    $item->template = str_replace($arrValue[$n]['was'], $arrValue[$n]['new'], $item->template);
                                    $write = true;
                                }
                            }
                        }
                    }

                    if($arrValue['is_active'] && !isset($arrValue['new_is_active'])){
                        $item->is_active = 0;
                        $write = true;
                    }
                    if(!$arrValue['is_active'] && isset($arrValue['new_is_active'])){
                        $item->is_active = 1;
                        $write = true;
                    }
                    if($write)
                        $wpdb->replace($wpdb->prefix . "ads_comment", (array)$item);
                }
            }
            delete_option('ads-clicks-checked');
            ApiHandler::collectClickJs();
        }
        
        return $params;
    }

}