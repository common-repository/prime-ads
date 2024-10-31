<?php

if($_POST['action'] == 'prma_counter_tick' || $_POST['action'] == 'prma_regclick'){
	$config = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/wp-config.php');
	preg_match('#define\(.DB_HOST., .(.*).\)#iU', $config, $matches);
	$host = $matches[1];
	preg_match('#define\(.DB_USER., .(.*).\)#iU', $config, $matches);
	$user = $matches[1];
	preg_match('#define\(.DB_PASSWORD., .(.*).\)#iU', $config, $matches);
	$pass = $matches[1];
	preg_match('#define\(.DB_NAME., .(.*).\)#iU', $config, $matches);
	$db = $matches[1];
	preg_match('#table_prefix\s*=\s*[\'\"](.*)[\'\"];#iU', $config, $matches);
	$prefix = $matches[1];

	$mysqli = new mysqli($host, $user, $pass, $db);
	if($mysqli->connect_error) die('db error');

	// Увеличиваем счётчики
	if($_POST['action'] == 'prma_counter_tick'){
		if($_POST['type'] == 'post')
		$mysqli->query("UPDATE " . $prefix . "options SET `option_value`=`option_value`+1 WHERE `option_name`='counter_total' OR `option_name`='counter_current'");

		$mysqli->query("UPDATE " . $prefix . "options SET `option_value`=`option_value`+1 WHERE `option_name`='counter_allpages_total' OR `option_name`='counter_allpages_current'");
	}

	// Регистрируем клики
	if($_POST['action'] == 'prma_regclick'){
		$rel = explode('_', $_POST['rel']);
		$field = 'clicks';
		switch ($rel[0]) {
		    case 'cb':
		        $table = 'ads_code_block';
		        break;
		    case 'asb':
		        $table = 'ads_adsense_block';
		        break;
		    case 'st':
		    	$table = 'ads_simple_tizer_stat';
		    	$field = 'clicks_' . $rel[2];
		    	break;
		}
		if($table){
			$id = $rel[1];
			if(strpos($id, '_')){
				$arr = explode('_', $id);
				$id = $arr[0];
			}
			$mysqli->query("UPDATE " . $prefix . $table . " SET `$field`=`$field`+1 WHERE id=" . $id);
		}
	}

	echo $mysqli->error;
	echo 'ok';
	$mysqli->close();
}else{
	require_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
	global $primeAds;

	define('DIRECT_AJAX', true);
	
	if(isset($_POST['action']) && $_POST['action'] == 'prma_load_block'){
		$primeAds->loadBlock();
	}
	if(isset($_POST['action']) && $_POST['action'] == 'prma_load_positions'){
		$primeAds->loadPositions();
	}
}