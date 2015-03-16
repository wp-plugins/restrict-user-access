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
 * Post Type Module
 *
 * Detects if current content is:
 * a) specific post type or specific post
 * b) specific post type archive or home
 * 
 */
class WPCAModule_post_type extends WPCAModule_Base {
	
	/**
	 * Registered public post types
	 * 
	 * @var array
	 */
	private $_post_types;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('post_type',__('Post Types',WPCACore::DOMAIN), true);
		$this->type_display = true;
		$this->searchable = true;
		
		add_action('transition_post_status',
			array(&$this,'post_ancestry_check'),10,3);

	}

	/**
	 * Display module in Screen Settings
	 *
	 * @since   1.0
	 * @param   array    $columns
	 * @return  array
	 */
	public function metabox_preferences($columns) {
		foreach ($this->_post_types()->get_all() as $post_type) {
			$columns['box-'.$this->id.'-'.$post_type->name] = $post_type->label;
		}
		return $columns;
	}

	/**
	 * Get content for sidebar editor
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$args = wp_parse_args($args, array(
			'include'        => '',
			'post_type'      => 'post',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'paged'          => 1,
			'posts_per_page' => 20,
			'search'         => ''
		));
		extract($args);

		$exclude = array();
		if ($args['post_type'] == 'page' && 'page' == get_option('show_on_front')) {
			$exclude[] = get_option('page_on_front');
			$exclude[] = get_option('page_for_posts');
		}

		//WordPress searches in title and content by default
		//We want to search in title and slug
		if($args['search']) {
			$exclude_query = '';
			if(!empty($exclude)) {
				$exclude_query = " AND ID NOT IN (".implode(",", $exclude).")";
			}

			//Using unprepared (safe) exclude because WP is not good at parsing arrays
			global $wpdb;
			$posts = $wpdb->get_results($wpdb->prepare("
				SELECT ID, post_title, post_type, post_parent
				FROM $wpdb->posts
				WHERE post_type = '%s' AND (post_title LIKE '%s' OR post_name LIKE '%s') AND post_status IN('publish','private','future')
				".$exclude_query."
				ORDER BY post_title ASC
				LIMIT 0,20
				",
				$args['post_type'],
				"%".$args['search']."%",
				"%".$args['search']."%"
			));
			$total_pages = 1;
			$total_items = $args['posts_per_page'];
		} else {
			//WP3.1 does not support (array) as post_status
			$query = new WP_Query(array(
				'posts_per_page'         => $args['posts_per_page'],
				'post_type'              => $args['post_type'],
				'post_status'            => 'publish,private,future',
				'post__in'               => $args['include'],
				'exclude'                => $exclude,
				'orderby'                => $args['orderby'],
				'order'                  => $args['order'],
				'paged'                  => $args['paged'],
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false
			));
			$posts = $query->posts;
			$total_pages = $query->max_num_pages;
			$total_items = $query->found_posts;
		}

		$this->pagination = array(
			'paged'       => $args['paged'],
			'per_page'    => $args['posts_per_page'],
			'total_pages' => $total_pages,
			'total_items' => $total_items
		);

		return $posts;
	}

	/**
	 * Get registered public post types
	 *
	 * @since   1.0
	 * @return  array
	 */
	protected function _post_types() {
		if(!$this->_post_types) {
			$this->_post_types = new WPCAPostTypeManager();
			// List public post types
			foreach (get_post_types(array('public' => true), 'objects') as $post_type) {
				$this->_post_types->add($post_type);
			}
		}
		return $this->_post_types;
	}

	/**
	 * Print saved condition data for a group
	 *
	 * @since   1.0
	 * @param   int    $post_id
	 * @return  void
	 */
	public function print_group_data($post_id) {
		$ids = get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id);
		
		if($ids) {
			$lookup = array_flip((array)$ids);
			foreach($this->_post_types()->get_all() as $post_type) {
				$posts = $this->_get_content(array('include' => $ids, 'posts_per_page' => -1, 'post_type' => $post_type->name, 'orderby' => 'title', 'order' => 'ASC'));
				if($posts || isset($lookup[$post_type->name]) || isset($lookup[WPCACore::PREFIX.'sub_' . $post_type->name])) {
					echo '<div class="cas-condition cas-condition-'.$this->id.'-'.$post_type->name.'">';
					echo '<h4>'.$post_type->label.'</h4>';
					echo '<ul>';
					if(isset($lookup[WPCACore::PREFIX.'sub_' . $post_type->name])) {
						echo '<li><label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.WPCACore::PREFIX.'sub_' . $post_type->name . '" checked="checked" /> ' . __('Automatically select new children of a selected ancestor', WPCACore::DOMAIN) . '</label></li>' . "\n";
					}
					if(isset($lookup[$post_type->name])) {
						echo '<li><label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.$post_type->name.'" checked="checked" /> '.$post_type->labels->all_items.'</label></li>' . "\n";
					}
					if($posts) {
						echo $this->term_checklist($post_type->name, $posts, false, $ids);	
					}					
					echo '</ul>';
					echo '</div>';	
				}
			}

		}

	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return ((is_singular() || is_home()) && !is_front_page()) || is_post_type_archive();
	}

	/**
	 * Get data from context
	 *
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		if(is_singular()) {
			return array(
				get_post_type(),
				get_the_ID()
			);
		}
		global $post_type;
		// Home has post as default post type
		if(!$post_type) $post_type = 'post';
		return array(
			$post_type
		);
	}

	/**
	 * Meta box content
	 * 
	 * @global WP_Post $post
	 * @since  1.0
	 * @return void 
	 */
	public function meta_box_content() {
		global $post;

		$screen = get_current_screen();
		$hidden_columns  = get_hidden_columns( $screen->id );

		foreach ($this->_post_types()->get_all() as $post_type) {

			$recent_posts = $this->_get_content(array('post_type' => $post_type->name));

			$panels = "";
			if($post_type->hierarchical) {
				$panels .= '<ul><li>' . "\n";
				$panels .= '<label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.WPCACore::PREFIX.'sub_' . $post_type->name . '" /> ' . __('Automatically add new children of a selected ancestor', WPCACore::DOMAIN) . '</label>' . "\n";
				$panels .= '</li></ul>' . "\n";
			}
			
			if($this->type_display) {
				$archive_label = $post_type->has_archive ? "/".sprintf(__("%s Archives",WPCACore::DOMAIN),$post_type->labels->singular_name) : "";
				$archive_label = $post_type->name == "post" ? "/".__("Blog Page",WPCACore::DOMAIN) : $archive_label;
				$panels .= '<ul><li>' . "\n";
				$panels .= '<label><input class="cas-chk-all" type="checkbox" name="cas_condition['.$this->id.'][]" value="' . $post_type->name . '" /> ' . $post_type->labels->all_items . $archive_label . '</label>' . "\n";
				$panels .= '</li></ul>' . "\n";
			}

			if (!$recent_posts) {
				$panels .= '<p>' . __('No items.') . '</p>';
			} else {
				//No need to use two queries before knowing there are items
				if(count($recent_posts) < 20) {
					$posts = $recent_posts;
				} else {
					$posts = $this->_get_content(array(
						'post_type' => $post_type->name,
						'orderby' => 'title',
						'order' => 'ASC'
					));
				}

				$tabs = array();
				$tabs['most-recent'] = array(
					'title' => __('Most Recent'),
					'status' => true,
					'content' => $this->term_checklist($post_type->name, $recent_posts)
				);
				$tabs['all'] = array(
					'title' => __('View All'),
					'status' => false,
					'content' => $this->term_checklist($post_type->name, $posts, true)
				);
				if($this->searchable) {
					$tabs['search'] = array(
						'title' => __('Search'),
						'status' => false,
						'content' => '',
						'content_before' => '<p><input data-cas-item_object="'.$post_type->name.'" class="cas-autocomplete-' . $this->id . ' cas-autocomplete quick-search" id="cas-autocomplete-' . $this->id . '-' . $post_type->name . '" type="search" name="cas-autocomplete" value="" placeholder="'.__('Search').'" autocomplete="off" /><span class="spinner"></span></p>'
					);
				}

				$panels .= $this->create_tab_panels($this->id . '-' . $post_type->name,$tabs);
				
			}

			WPCAView::make("module.meta_box",array(
				'hidden'       => in_array('box-'.$this->id.'-'.$post_type->name, $hidden_columns) ? ' hide-if-js' : '',
				'id'           => $this->id.'-'. $post_type->name,
				'description'  => "",
				'name'         => $post_type->label,
				'panels'       => $panels
			))->render();

		}
	}

	/**
	 * Get checkboxes for sidebar edit screen
	 *
	 * @since   1.0
	 * @param   string          $item_object
	 * @param   array           $data
	 * @param   boolean         $pagination
	 * @param   array|boolean   $selected_data
	 * @return  string
	 */
	protected function term_checklist($item_object, $data, $pagination = false, $selected_data = array()) {

		$args['selected_terms'] = $selected_data;

		$return = WPCAWalker::make($this->id,'post_parent','ID','post_title')
		->walk($data,0,$args);

		if($pagination) {

			$paginate = paginate_links(array(
				'base'         => admin_url( 'admin-ajax.php').'%_%',
				'format'       => '?paged=%#%',
				'total'        => $this->pagination['total_pages'],
				'current'      => $this->pagination['paged'],
				'mid_size'     => 2,
				'end_size'     => 1,
				'prev_next'    => true,
				'prev_text'    => 'prev',
				'next_text'    => 'next',
				'add_args'     => array('item_object'=>$item_object),
			));
			$return = $paginate.$return.$paginate;
		}
		
		return $return;

	}

	/**
	 * Get content in HTML
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {
		$args = wp_parse_args($args, array(
			'item_object'    => 'post',
			'paged'          => 1,
			'search'         => ''
		));

		$post_type = get_post_type_object($args['item_object']);

		if(!$post_type) {
			return false;
		}

		$posts = $this->_get_content(array(
			'post_type' => $post_type->name,
			'orderby'   => 'title',
			'order'     => 'ASC',
			'paged'     => $args['paged'],
			'search'    => $args['search']
		));

		return $this->term_checklist($post_type->name, $posts, empty($args['search']));

	}

	
	/**
	 * Automatically select child of selected parent
	 *
	 * @since  1.0
	 * @param  string  $new_status 
	 * @param  string  $old_status 
	 * @param  WP_Post $post       
	 * @return void 
	 */
	public function post_ancestry_check($new_status, $old_status, $post) {
		
		if(!WPCACore::post_types()->has($post->post_type) && $post->post_type != WPCACore::TYPE_CONDITION_GROUP) {
			
			$status = array('publish','private','future');
			// Only new posts are relevant
			if(!in_array($old_status,$status) && in_array($new_status,$status)) {
				
				$post_type = get_post_type_object($post->post_type);
				if($post_type->hierarchical && $post_type->public && $post->post_parent) {
				
					// Get sidebars with post ancestor wanting to auto-select post
					$query = new WP_Query(array(
						'post_type'				=> WPCACore::TYPE_CONDITION_GROUP,
						'meta_query'			=> array(
							'relation'			=> 'AND',
							array(
								'key'			=> WPCACore::PREFIX . $this->id,
								'value'			=> WPCACore::PREFIX.'sub_' . $post->post_type,
								'compare'		=> '='
							),
							array(
								'key'			=> WPCACore::PREFIX . $this->id,
								'value'			=> get_ancestors($post->ID,$post->post_type),
								'type'			=> 'numeric',
								'compare'		=> 'IN'
							)
						)
					));
					if($query && $query->found_posts) {
						foreach($query->posts as $sidebar) {
							add_post_meta($sidebar->ID, WPCACore::PREFIX.$this->id, $post->ID);
						}
					}
				}
			}	
		}	
	}

}
