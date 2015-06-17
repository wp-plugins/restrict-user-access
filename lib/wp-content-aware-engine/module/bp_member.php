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
 * BuddyPress Member Page Module
 * 
 * Detects if current content is:
 * a) a specific buddypress member page
 *
 */
class WPCAModule_bp_member extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('bp_member',__('BuddyPress Members',WPCACore::DOMAIN));
		
		add_filter('wpca/module/static/in-context', array(&$this,'static_is_content'));
		
	}

	/**
	 * Get content for sidebar editor
	 * 
	 * @global  object    $bp
	 * @since   1.0
	 * @param   array     $args
	 * @return  array
	 */
	protected function _get_content($args = array()) {
		global $bp;

		$content = array();

		if(isset($bp->loaded_components)) {
			$components = $bp->loaded_components;
			unset($components['members'],$components['xprofile']);
			$components['profile'] = 'profile';
			
			
			foreach((array)$components as $name) {
				$content[$name] = ucfirst($name);
			}
		}

		if(isset($args['include'])) {
			$content = array_intersect_key($content,array_flip($args['include']));
		}
		
		return $content;
	}
	
	/**
	 * Determine if content is relevant
	 * 
	 * @global object  $bp
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		global $bp;
		return isset($bp->displayed_user->domain) && $bp->displayed_user->domain;
	}

	/**
	 * Get data from context
	 *
	 * @global object $bp
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		global $bp;
		$data = array();
		if(isset($bp->current_component)) {
			$data[] = $bp->current_component;
			if(isset($bp->current_action)) {
				$data[] = $bp->current_component."-".$bp->current_action;
			}
		}
		return $data;
	}
	
	/**
	 * Meta box content
	 * 
	 * @global WP_Post $post
	 * @global object  $bp
	 * @since  1.0
	 * @return void 
	 */
	public function meta_box_content() {
		global $post, $bp;

		if(!$bp->bp_options_nav) {
			return;
		}

		$screen = get_current_screen();

		$hidden_columns  = get_hidden_columns( $screen->id );
		$id = 'box-'.$this->id;
		$hidden = in_array($id, $hidden_columns) ? ' hide-if-js' : '';

		echo '<li id="'.$id.'" class="manage-column column-'.$id.' control-section accordion-section'.$hidden.'">';
		echo '<h3 class="accordion-section-title" title="'.$this->name.'" tabindex="0">'.$this->name.'</h3>'."\n";
		echo '<div class="accordion-section-content cas-rule-content" data-cas-module="'.$this->id.'" id="cas-'.$this->id.'">';

		$field = $this->id;

		$tab_content = "";

		foreach ($this->_get_content() as $id => $name) {
			$tab_content .= '<li class="cas-'.$this->id.'-'.$id.'"><label class="selectit"><input type="checkbox" name="cas_condition[' . $field . '][]" value="' . $id . '" /> ' . $name . '</label></li>' . "\n";
			if(isset($bp->bp_options_nav[$id])) {
				$tab_content .= '<li><ul class="children">';
				foreach($bp->bp_options_nav[$id] as $child) {
					$tab_content .= '<li class="cas-'.$this->id.'-'.$id.'-'.$child['slug'].'"><label class="selectit"><input type="checkbox" name="cas_condition[' . $field . '][]" value="' . $id . '-'. $child['slug'].'" /> ' . $child['name'] . '</label></li>' . "\n";
				}
				$tab_content .= '</ul></li>';
			}
			
		}

		$tabs['all'] = array(
			'title'   => __('View All'),
			'status'  => true,
			'content' => $tab_content
		);

		echo $this->create_tab_panels($this->id,$tabs);

		echo '<p class="button-controls">';

		echo '<span class="add-to-group"><input data-cas-condition="'.$this->id.'" data-cas-module="'.$this->id.'" type="button" name="cas-condition-add" class="js-cas-condition-add button" value="'.__('Add to Group',WPCACore::DOMAIN).'"></span>';

		echo '</p>';

		echo '</div>'."\n";
		echo '</li>';
	}
	
	/**
	 * Avoid collision with content of static module
	 * Somehow buddypress pages pass is_404()
	 *
	 * @since  1.0
	 * @param  boolean $content 
	 * @return boolean          
	 */
	public function static_is_content($content) {
		return $content && !$this->in_context();
	}
	
}
