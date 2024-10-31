<?php

namespace AdsNS\Services;

class WpHelper{

	/**
	 * Возвращает сборную информацию по сайту
	 * @return [type] [description]
	 */
	public static function getSiteData()
	{
		$data = array();

		$data['rubrics'] = self::_getRubrics();
		$data['posts'] = self::_getPosts();
		$data['postTypes'] = self::_getPostTypes();
		$data['pages'] = self::_getPages();
		$data['tags'] = self::_getTags();

		echo json_encode(array('result' => 'ok','data' => $data));
	}

	/**
	 * Возвращает массив существующих рубрик
	 */
	private static function _getRubrics()
	{
		$data = array();

		$terms = get_terms("category");
		if(!$terms) return $data;
		foreach ($terms as $term)
			$data[$term->term_id] = self::_getRubricName($term, $term->name);

		asort($data);
		return $data;
	}

	private static function _getRubricName($term, $name)
	{
		if($term->parent == 0) return $name;
		$parent = get_term($term->parent, "category");
		if(!$parent) return $name;
		return self::_getRubricName($parent, $parent->name . '->' .$name);
	}

	/**
	 * Возвращает массив существующих меток
	 */
	private static function _getTags()
	{
		$data = array();

		$tags = get_tags();
		if(!$tags) return $data;
		foreach ($tags as $tag)
			$data[$tag->term_id] = $tag->name;

		return $data;
	}

	/**
	 * Возвращает массив существующих записей
	 */
	private static function _getPosts()
	{
		$data = array();

		$posts = get_posts(array('numberposts' => -1, 'post_status' => 'any','orderby' => 'title', 'order' => 'ASC'));
		if(!$posts) return $data;
		foreach ($posts as $post)
			$data[$post->ID] = $post->post_title;

		return $data;
	}

	/**
	 * Возвращает массив существующих пользовательских типов записей
	 */
	private static function _getPostTypes()
	{
		return get_option('ads-post-types', array());
	}

	/**
	 * Возвращает массив существующих страниц
	 */
	private static function _getPages()
	{
		$data = array();

		$posts = get_posts(array('numberposts' => -1, 'post_status' => 'any', 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC'));
		if(!$posts) return $data;
		foreach ($posts as $post)
			$data[$post->ID] = $post->post_title;

		return $data;
	}

	/**
	 * Сохраняет пользовательские типы записей для последующего получения
	 */
	public static function updatePostTypes()
	{
		$types = get_post_types( array( 'public' => true ), 'objects' ); 
		$data = array();
		foreach ($types as $slug => $type) 
			$data[$slug] = $type->labels->name;
		
		update_option('ads-post-types', $data);
	}

	public function toggleAds($state)
	{
		update_option('ads-onoff', $state);
		$cur_state = get_option('ads-onoff');

		return array('result' => 'ok','state' => $cur_state);
	}

	public static function getOfferPosts()
	{
		$params = array(
		    'posts_per_page' => -1, 'post_type' => 'post', 'post_status' => 'any', 'meta_query' => array(
			    array(
			        'key' => 'is_offer_post',
			        'value' =>'on'
			    ),
			)
		);

		$posts = get_posts($params);
		$data = array();

		if(!$posts) return array('result' => 'ok', 'data' => array());

		foreach ($posts as $post) {
			preg_match_all('#\{.*\}#iU', $post->post_content, $macroses);
			$data[] = array('id' => $post->ID, 'title' => $post->post_title, 'macroses' => $macroses[0], 'url' => get_the_permalink($post->ID));
		}

		return array('result' => 'ok', 'data' => $data);
	}
}

?>