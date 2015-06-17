<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('WPCACore::VERSION')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

/**
 *
 * bbPress Module
 * 
 * Detects if current content is:
 * a) any or specific bbpress user profile
 *
 */
class WPCAModule_bbpress extends WPCAModule_author {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->id = 'bb_profile';
		$this->name = __('bbPress User Profiles',WPCACore::DOMAIN);
		
		add_filter('wpca/module/post_type/db-where', array(&$this,'add_forum_dependency'));
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return function_exists('bbp_is_single_user') && bbp_is_single_user();
	}

	/**
	 * Get data from context
	 * 
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		$data = array($this->id);
		if(function_exists('bbp_get_displayed_user_id')) {
			$data[] = bbp_get_displayed_user_id();
		}
		return $data;
	}
	
	/**
	 * Sidebars to be displayed with forums will also 
	 * be dislpayed with respective topics and replies
	 *
	 * @since  1.0
	 * @param  string $where 
	 * @return string 
	 */
	public function add_forum_dependency($where) {
		if(is_singular(array('topic','reply'))) {
			$data = array(
				get_post_type(),
				get_the_ID(),
				'forum'
			);
			if(function_exists('bbp_get_forum_id')) {
				$data[] = bbp_get_forum_id();
			}
			$where = "(post_type.meta_value IS NULL OR post_type.meta_value IN('".implode("','", $data)."'))";
		}
		return $where;
	}
	
}
