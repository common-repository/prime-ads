<?php

namespace AdsNS\Services;

use AdsNs\PrimeAds;

class Migration{

	public static function migrate()
	{
		$last = get_option('ads-db-verison', '1.1.9');

		while (version_compare($last, ADS_DB_VERSION < 0)) {
			$last = self::doMigrate($last);
		}
	}

	private static function doMigrate($ver)
	{
		global $wpdb;
		if($ver == '1.1.9'){
			$table = $wpdb->prefix . 'ads_simple_tizer_stat';
			$wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
		        `id` int(11) NOT NULL AUTO_INCREMENT,
		        `tizer` int(11) NOT NULL,
		        `group` int(11) NOT NULL,
		        `template` int(11) NOT NULL,
		        `shows` int(11) NOT NULL,
		        `views` int(11) NOT NULL DEFAULT '0',
		        `clicks` int(11) NOT NULL DEFAULT '0',
		        `clicks_header` int(11) NOT NULL DEFAULT '0',
		        `clicks_text` int(11) NOT NULL DEFAULT '0',
		        `clicks_dop` int(11) NOT NULL DEFAULT '0',
		        `clicks_image` int(11) NOT NULL DEFAULT '0',
		        PRIMARY KEY (`id`)
		        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");
			
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer` ADD `forigin_id` INT(11) NOT NULL;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer` DROP `group`, DROP `views`, DROP `clicks`, DROP `clicks_header`, DROP `clicks_text`, DROP `clicks_dop`, DROP `clicks_image`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `tizers` TEXT NOT NULL AFTER `position`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `css` TEXT NOT NULL AFTER `template`, ADD `js` LONGTEXT NOT NULL AFTER `css`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_position` ADD `css` LONGTEXT NOT NULL AFTER `template`, ADD `js` LONGTEXT NOT NULL AFTER `css`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_template` ADD `css` LONGTEXT NOT NULL AFTER `template`, ADD `js` LONGTEXT NOT NULL AFTER `css`, ADD `fonts` TEXT NOT NULL AFTER `js`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `css` TEXT NOT NULL AFTER `template`, ADD `js` TEXT NOT NULL AFTER `css`, ADD `fonts` TEXT NOT NULL AFTER `js`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `tags` TEXT NOT NULL AFTER `ex_posts`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `tags` TEXT NOT NULL AFTER `ex_posts`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `tags` TEXT NOT NULL AFTER `ex_posts`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `tags` TEXT NOT NULL AFTER `ex_posts`;");

			$wpdb->query("DELETE FROM `" . $wpdb->prefix . "ads_simple_tizer`;");

			update_option('ads-db-verison', '1.2.0');
			return '1.2.0';
		}
		if($ver == '1.2.0'){
			$table = $wpdb->prefix . 'ads_comment';
			$wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`is_active` int(1) NOT NULL DEFAULT '1' COMMENT 'Флаг активности',
				`position` int(2) NOT NULL DEFAULT '1' COMMENT 'Позиция группы',
				`views` int(11) NOT NULL DEFAULT '0' COMMENT 'Показы',
				`clicks` int(11) NOT NULL DEFAULT '0' COMMENT 'Клики',
				`template` text,
				`image` text NOT NULL,
				`css` text NOT NULL,
				`js` text NOT NULL,
				`fonts` text NOT NULL,
				`shows` float NOT NULL DEFAULT '0' COMMENT 'Показы',
				`shows_ratio` float NOT NULL DEFAULT '1' COMMENT 'Коэффициент паказов',
				`resolutions` text NOT NULL COMMENT 'Разрешения экрана',
				`min_lenght` int(11) NOT NULL,
				`in_rubrics` text NOT NULL COMMENT 'Показывать в рубриках',
				`ex_rubrics` text NOT NULL COMMENT 'Исключая рубрики',
				`in_posts` text NOT NULL COMMENT 'Показывать в записях',
				`ex_posts` text NOT NULL COMMENT 'Исключая записи',
				`tags` text NOT NULL,
				`country` text NOT NULL,
				`region` text NOT NULL,
				`city` text NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

			update_option('ads-db-verison', '1.3.7');
			return '1.3.7';
		}
		if($ver == '1.3.7'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `pos` VARCHAR(255) NOT NULL AFTER `position`;");

			update_option('ads-db-verison', '1.5.1');
			return '1.5.1';
		}

		if($ver == '1.5.1'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `sticked_tizers` TEXT NOT NULL AFTER `tizers`;");

			update_option('ads-db-verison', '1.6.6');
			return '1.6.6';
		}

		if($ver == '1.6.6' || $ver == '1.7.2'){
			$table = $wpdb->prefix . 'ads_offer_post';
			$wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`post_id` int(11) NOT NULL COMMENT 'ID записи',
				`views` int(11) NOT NULL DEFAULT '0' COMMENT 'Показы',
				`clicks` int(11) NOT NULL DEFAULT '0' COMMENT 'Клики',
				`clicks_macros` text,
				`links` text,		
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

			update_option('ads-db-verison', '1.7.3');
			return '1.7.3';
		}

		if($ver == '1.7.3'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_offer_post` ADD `replacements` TEXT NOT NULL AFTER `links`;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_offer_post` ADD `no_ads` INT NOT NULL AFTER `replacements`;");

			update_option('ads-db-verison', '1.7.5');
			return '1.7.5';
		}

		if($ver == '1.7.5'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_template` ADD `prefix` VARCHAR(100) NOT NULL ;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `prefix` VARCHAR(100) NOT NULL ;");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `prefix` VARCHAR(100) NOT NULL ;");

			update_option('ads-db-verison', '1.7.9');
			return '1.7.9';
		}

		if($ver == '1.7.8' || $ver == '1.7.9'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `by_timetable` TINYINT NOT NULL DEFAULT '0' AFTER `city`, ADD `days_of_week` VARCHAR(255) NOT NULL DEFAULT '' AFTER `by_timetable`, ADD `from_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `days_of_week`, ADD `to_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `from_time`, ADD `not_show` TINYINT NOT NULL DEFAULT '0' AFTER `to_time`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `by_timetable` TINYINT NOT NULL DEFAULT '0' AFTER `city`, ADD `days_of_week` VARCHAR(255) NOT NULL DEFAULT '' AFTER `by_timetable`, ADD `from_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `days_of_week`, ADD `to_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `from_time`, ADD `not_show` TINYINT NOT NULL DEFAULT '0' AFTER `to_time`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `by_timetable` TINYINT NOT NULL DEFAULT '0' AFTER `city`, ADD `days_of_week` VARCHAR(255) NOT NULL DEFAULT '' AFTER `by_timetable`, ADD `from_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `days_of_week`, ADD `to_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `from_time`, ADD `not_show` TINYINT NOT NULL DEFAULT '0' AFTER `to_time`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `by_timetable` TINYINT NOT NULL DEFAULT '0' AFTER `city`, ADD `days_of_week` VARCHAR(255) NOT NULL DEFAULT '' AFTER `by_timetable`, ADD `from_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `days_of_week`, ADD `to_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `from_time`, ADD `not_show` TINYINT NOT NULL DEFAULT '0' AFTER `to_time`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `by_timetable` TINYINT NOT NULL DEFAULT '0' AFTER `city`, ADD `days_of_week` VARCHAR(255) NOT NULL DEFAULT '' AFTER `by_timetable`, ADD `from_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `days_of_week`, ADD `to_time` VARCHAR(20) NOT NULL DEFAULT '0' AFTER `from_time`, ADD `not_show` TINYINT NOT NULL DEFAULT '0' AFTER `to_time`");

			update_option('ads-db-verison', '1.9.9');
			return '1.9.9';
		}

		if($ver == '1.9.9'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` CHANGE  `to_time` `to_time` INT( 11 ) NOT NULL DEFAULT  '0';");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` CHANGE  `from_time` `from_time` INT( 11 ) NOT NULL DEFAULT  '0';");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` CHANGE  `to_time` `to_time` INT( 11 ) NOT NULL DEFAULT  '0';");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` CHANGE  `from_time` `from_time` INT( 11 ) NOT NULL DEFAULT  '0';");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` CHANGE  `to_time` `to_time` INT( 11 ) NOT NULL DEFAULT  '0';");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` CHANGE  `from_time` `from_time` INT( 11 ) NOT NULL DEFAULT  '0';");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` CHANGE  `to_time` `to_time` INT( 11 ) NOT NULL DEFAULT  '0';");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` CHANGE  `from_time` `from_time` INT( 11 ) NOT NULL DEFAULT  '0';");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` CHANGE  `to_time` `to_time` INT( 11 ) NOT NULL DEFAULT  '0';");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` CHANGE  `from_time` `from_time` INT( 11 ) NOT NULL DEFAULT  '0';");

			update_option('ads-db-verison', '2.0.2');
			return '2.0.2';
		}

		if($ver == '2.0.2'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `position_not_in_timetable` INT( 11 )");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `position_not_in_timetable` INT( 11 )");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `position_not_in_timetable` INT( 11 )");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `position_not_in_timetable` INT( 11 )");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `position_not_in_timetable` INT( 11 )");

			update_option('ads-db-verison', '2.0.4');
			return '2.0.4';
		}

		if($ver == '2.0.4'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `links_nets` text");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer` ADD `links_nets` text");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `links_nets` text");

			update_option('ads-db-verison', '2.1.3');
			return '2.1.3';
		}

		if($ver == '2.1.3'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `links_nets` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `links_nets` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `links_nets` text");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `name` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `name` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `name` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `name` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `name` text");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_position` ADD `name` text");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `max_res` VARCHAR(100)");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `pub_id` VARCHAR(100)");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `slot_id` VARCHAR(100)");

			update_option('ads-db-verison', '2.1.4');
			return '2.1.4';
		}

		if($ver == '2.1.4'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_position` ADD `is_turbo` TINYINT NOT NULL DEFAULT '0'");

			update_option('ads-db-verison', '2.1.8');
			return '2.1.8';
		}

		if($ver == '2.1.8'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `adblock_only` TINYINT NOT NULL DEFAULT '0'");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `adblock_only` TINYINT NOT NULL DEFAULT '0'");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `adblock_only` TINYINT NOT NULL DEFAULT '0'");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `adblock_only` TINYINT NOT NULL DEFAULT '0'");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `adblock_only` TINYINT NOT NULL DEFAULT '0'");

			update_option('ads-db-verison', '2.2.1');
			return '2.2.1';
		}

		if($ver == '2.2.1'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `devices` VARCHAR(255)");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `devices` VARCHAR(255)");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `devices` VARCHAR(255)");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `devices` VARCHAR(255)");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `devices` VARCHAR(255)");

			update_option('ads-db-verison', '2.2.2');
			return '2.2.2';
		}

		if($ver == '2.2.2'){
			add_action('plugins_loaded', function(){
				global $wp_cache_config_file;
				if(function_exists('wp_cache_replace_line')){
					wp_cache_replace_line('^ *\$wp_super_cache_late_init', "\$wp_super_cache_late_init = 1;", $wp_cache_config_file);

					wp_cache_replace_line('^ *\$wp_cache_mfunc_enabled', "\$wp_cache_mfunc_enabled = 1;", $wp_cache_config_file);
				}
			});

			update_option('ads-db-verison', '2.2.7');
			return '2.2.7';
		}

		if($ver == '2.2.7'){
			add_action('plugins_loaded', function(){
				global $wp_cache_config_file;
				if(function_exists('wp_cache_replace_line')){
					wp_cache_replace_line('^ *\$wp_super_cache_late_init', "\$wp_super_cache_late_init = 0;", $wp_cache_config_file);

					wp_cache_replace_line('^ *\$wp_cache_mfunc_enabled', "\$wp_cache_mfunc_enabled = 0;", $wp_cache_config_file);
				}
			});

			update_option('ads-db-verison', '2.3.0');
			return '2.3.0';
		}

		if($ver == '2.3.0'){
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `in_referrers` TEXT NOT NULL AFTER `ex_posts`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_adsense_block` ADD `ex_referrers` TEXT NOT NULL AFTER `in_referrers`");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `in_referrers` TEXT NOT NULL AFTER `ex_posts`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_code_block` ADD `ex_referrers` TEXT NOT NULL AFTER `in_referrers`");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `in_referrers` TEXT NOT NULL AFTER `ex_posts`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_comment` ADD `ex_referrers` TEXT NOT NULL AFTER `in_referrers`");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `in_referrers` TEXT NOT NULL AFTER `ex_posts`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_simple_tizer_group` ADD `ex_referrers` TEXT NOT NULL AFTER `in_referrers`");

			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `in_referrers` TEXT NOT NULL AFTER `ex_posts`");
			$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "ads_text_block` ADD `ex_referrers` TEXT NOT NULL AFTER `in_referrers`");

			PrimeAds::moveFolders();

			update_option('ads-db-verison', '2.3.3');
			return '2.3.3';
		}
	}
}
?>