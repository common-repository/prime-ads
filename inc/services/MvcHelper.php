<?php
namespace AdsNS\Services;

class MvcHelper{
    protected $plugin_page;
    protected $primeads = 'primeads';
    protected $viewVars = array();
    
    public function __construct() {
        $this->plugin_page = $_SERVER['PHP_SELF'] . '?page=' . $this->primeads;
    }
    
    public function addStyles($styles){
        if (!function_exists('wp_register_style') or !function_exists('wp_register_script')) {
            return false;
        }
        foreach($styles as $name => $link){
            if(strpos($link, 'http:') === false){
                $link = plugins_url($link, ADS_PATH."/x");
            }
            if(isset($_GET['link'])){
                var_dump($link);
                var_dump(file_get_contents($link));
            }
            wp_register_style($name, $link, array(), '1.1');
            wp_enqueue_style($name);
        }
    }
    
    public function addScripts($scripts){
        if (!function_exists('wp_register_style') or !function_exists('wp_register_script')) {
            return false;
        }
        foreach($scripts as $name => $link){
            if(strpos($link, 'http:') === false){
                $link = plugins_url($link, ADS_PATH."/x");
            }
            wp_register_script($name, $link);
            wp_enqueue_script($name);
        }
    }
    
    public function mvc($smcontroller, $smaction, $args = array()){
        if(!preg_match("/^([a-z]+)$/", $smcontroller) or !preg_match("/^([a-z_]+)$/", $smaction)){
            echo "wrong url";
            return;
        }
        $controller = ucfirst($smcontroller);
        $action = $smaction . "Action";
        $class = "\\AdsNS\\Controllers\\".$controller."Controller";

        $mvc = new $class();

        if(method_exists($mvc, 'init'))
            if(!call_user_func_array(array(&$mvc, 'init'), $args))
                return;

        if(method_exists($mvc, $action)){
            $vars = call_user_func_array(array(&$mvc, $action), $args);
            if($vars !== false){
                $this->render($smcontroller, $smaction, $vars);
            }
        }else{
            echo "wrong action $smaction at controller $class \n";
        }
    }
    
    protected function fetchMvc($smcontroller, $smaction, $args = array()){
        ob_start();
        $this->mvc($smcontroller, $smaction, $args);
        return ob_get_clean();
    }
    
    protected function render($smcontroller, $smaction, $vars = array()){
        # добавим новые переменные
        if($vars and is_array($vars)){
            $this->viewVars = $vars + $this->viewVars;
        }
        
        $view = ADS_PATH . '/inc/views/' . $smcontroller . '/' . $smaction . '.phtml';
        if (file_exists($view)) {
            extract($this->viewVars);
            include($view);
        } else {
            if(!defined('ADS_CRON')){
                echo "wrong view $view \n";
            }
        }
    }
    
    public function getRendered($c, $a, $vars = array()){
        ob_start();
        $this->render($c, $a, $vars);
        
        return ob_get_clean();

    }
    
    public function getMvcUrl($controller, $action, $query="", $keepold = false){
        if($keepold){
            $params = $_GET;
            unset($params['updated']);
            unset($params['page']);
        }else{
            $params = array(
                'controller' => $_GET['controller'],
                'action' => $_GET['action'],
            );
        }
        
        foreach(explode("&", $query) as $subquery){
            if(preg_match("/^(.*)=(.*)$/", $subquery, $match)){
                $params[$match[1]] = $match[2];
            }
        }
        
        
        if(!is_null($controller)){
            $params['controller'] = $controller;
        }
        if(!is_null($action)){
            $params['action'] = $action;
        }
        
        $paramsUrl = "";
        foreach($params as $key => $val){
            $paramsUrl .= "&$key=$val";
        }
        return $this->plugin_page . $paramsUrl;
    }

    protected function isMvcUrl($controller, $action){
        if($controller == $_GET['controller'] and $action == $_GET['action']){
            return true;
        }
        return false;
    }
    
}