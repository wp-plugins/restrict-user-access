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
 * URL Module
 * 
 * Detects if current content is:
 * a) matching a URL or URL pattern
 *
 */
class WPCAModule_date extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'date',
			__('Dates',WPCACore::DOMAIN),
			false
		);
	}

	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return is_date();
	}

	/**
	 * Get data from context
	 *
	 * @global object $wpdb
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		global $wpdb;
		return $wpdb->prepare(
			"(date.meta_value IS NULL OR '%s' = date.meta_value)",
			"0000-00-00"
		);
	}

	/**
	 * Get authors
	 * 
	 * @global object $wpdb
	 * @since  1.0
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$data = array(
			'0000-00-00' => __('Date Archives', WPCACore::DOMAIN)
		);
		if(isset($args['include'])) {
			$data = array_intersect_key($data, array_flip($args['include']));
		}
		return $data;

	}
	
}
