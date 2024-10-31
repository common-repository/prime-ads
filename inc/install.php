<?php
namespace AdsNS;
/**
 * инсталлятор
 * @global type $wpdb 
 */
function Install() {
    global $wpdb;

    $table = $wpdb->prefix . 'ads_code_block';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `is_active` int(1) NOT NULL DEFAULT '1',
        `position` int(2) NOT NULL DEFAULT '1',
        `views` int(11) NOT NULL DEFAULT '0',
        `clicks` int(11) NOT NULL DEFAULT '0',
        `template` text,
        `shows` float NOT NULL DEFAULT '0',
        `shows_ratio` float NOT NULL DEFAULT '1',
        `resolutions` text NOT NULL,
        `min_lenght` int(11) DEFAULT NULL,
        `in_rubrics` text NOT NULL,
        `ex_rubrics` text NOT NULL,
        `in_posts` text NOT NULL,
        `ex_posts` text NOT NULL,
        `tags` text NOT NULL,
        `country` text NOT NULL,
        `region` text NOT NULL,
        `city` text NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;");

    $table = $wpdb->prefix . 'ads_position';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `position` int(2) NOT NULL,
        `count` int(11) NOT NULL,
        `async` int(11) NOT NULL DEFAULT '0',
        `template` text NOT NULL,
        `css` longtext NOT NULL,
        `js` longtext NOT NULL,
        `priority` int(11) NOT NULL DEFAULT '0',
        `is_active` int(11) NOT NULL DEFAULT '1',
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;");

    $table = $wpdb->prefix . 'ads_simple_tizer';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `header` text NOT NULL,
        `text` text NOT NULL,
        `more` text NOT NULL,
        `image` text NOT NULL,
        `url` text NOT NULL,
        `is_active` int(1) NOT NULL DEFAULT '1',
        `forigin_id` int(11) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;");

    $table = $wpdb->prefix . 'ads_simple_tizer_group';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `is_active` int(1) NOT NULL DEFAULT '1',
        `position` int(2) NOT NULL DEFAULT '1',
        `template` text NOT NULL,
        `tizers` text NOT NULL,
        `rotation` int(11) NOT NULL,
        `shows` int(11) NOT NULL DEFAULT '0',
        `shows_ratio` float NOT NULL DEFAULT '1',
        `resolutions` text NOT NULL,
        `min_lenght` int(11) NOT NULL,
        `in_rubrics` text NOT NULL,
        `ex_rubrics` text NOT NULL,
        `in_posts` text NOT NULL,
        `ex_posts` text NOT NULL,
        `tags` text NOT NULL,
        `country` text NOT NULL,
        `region` text NOT NULL,
        `city` text NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;");

    $table = $wpdb->prefix . 'ads_text_block';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `is_active` int(1) NOT NULL DEFAULT '1',
        `position` int(2) NOT NULL DEFAULT '1',
        `views` int(11) NOT NULL DEFAULT '0',
        `clicks` int(11) NOT NULL DEFAULT '0',
        `template` text,
        `css` text NOT NULL,
        `js` text NOT NULL,
        `fonts` TEXT NOT NULL,
        `shows` float NOT NULL DEFAULT '0',
        `shows_ratio` float NOT NULL DEFAULT '1',
        `resolutions` text NOT NULL,
        `min_lenght` int(11) NOT NULL,
        `in_rubrics` text NOT NULL,
        `ex_rubrics` text NOT NULL,
        `in_posts` text NOT NULL,
        `ex_posts` text NOT NULL,
        `tags` text NOT NULL,
        `country` text NOT NULL,
        `region` text NOT NULL,
        `city` text NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;");

    $table = $wpdb->prefix . 'ads_adsense_block';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `is_active` int(1) NOT NULL DEFAULT '1',
          `position` int(2) NOT NULL DEFAULT '1',
          `views` int(11) NOT NULL DEFAULT '0',
          `clicks` int(11) NOT NULL DEFAULT '0',
          `template` text,
          `css` text NOT NULL,
          `js` longtext NOT NULL,
          `shows` float NOT NULL DEFAULT '0',
          `shows_ratio` float NOT NULL DEFAULT '1',
          `resolutions` text NOT NULL,
          `min_lenght` int(11) DEFAULT NULL,
          `in_rubrics` text NOT NULL,
          `ex_rubrics` text NOT NULL,
          `in_posts` text NOT NULL,
          `ex_posts` text NOT NULL,
          `tags` text NOT NULL,
          `country` text NOT NULL,
          `region` text NOT NULL,
          `city` text NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");

    $table = $wpdb->prefix . 'ads_template';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` int(11) NOT NULL,
            `template` longtext NOT NULL,
            `css` longtext NOT NULL,
            `js` longtext NOT NULL,
            `fonts` TEXT NOT NULL,
            `num_tizers` int(11) NOT NULL,
            `forigin_id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");

    $table = $wpdb->prefix . 'ads_template_shows';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `template` int(11) NOT NULL,
            `entity` int(11) NOT NULL,
            `shows` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");

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
    
    $state = get_option('ads-onoff');
    if(!$state) update_option('ads-onoff', 'on');

    delete_option('ads-flushed');
    update_option('ads-service-url', 'http://primeads.ru/');
    update_option('ads-db-verison', '1.2.0');
}

/**
 * деинсталлятор
 * @global type $wpdb 
 */
function Uninstall() {
    /*global $wpdb;

    $tables = array('code_block','position','simple_tizer','simple_tizer_group','text_block');

    foreach ($tables as $shortname) {
        $table = $wpdb->prefix . 'ads_' . $shortname;
        $wpdb->query("DROP TABLE `$table`");
    }

    delete_option('ads_access_key');*/
}
