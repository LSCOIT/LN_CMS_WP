<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
	die('-1');
}

class LSC_Assets
{

	/**
	 * Hook in tabs.
	 */
	public function __construct()
	{
		add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
	}

	/**
	 * Enqueue styles.
	 */
	public function admin_styles()
	{
		$screen         = get_current_screen();
		$screen_id      = $screen ? $screen->id : '';

		// Register admin styles
		wp_register_style('lsc-settings', lsc_asset_path('css/settings.css'), array('wp-jquery-ui-dialog'), LSC_VERSION);
		wp_register_style('lsc-resource', lsc_asset_path('css/resource.css'), null, LSC_VERSION);
		wp_register_style('lsc-topic', lsc_asset_path('css/topic.css'), null, LSC_VERSION);
		wp_register_style('lsc-curated-experiences', lsc_asset_path('css/curated-experiences.css'), null, LSC_VERSION);
		wp_register_style('datatables', '//cdn.datatables.net/v/dt/jqc-1.12.4/dt-1.10.21/r-2.2.5/datatables.min.css', ['lsc-curated-experiences'], null);

		// Sitewide menu CSS
		// wp_enqueue_style('game_portal_admin_menu');
		if ('settings_page_integration-settings' === $screen_id) {
			wp_enqueue_style('lsc-settings');
		}

		if ('post' === $screen_id) {
			wp_enqueue_style('lsc-resource');
		}

		if ('edit-category' === $screen_id) {
			wp_enqueue_style('lsc-topic');
		}

		if ('toplevel_page_curated-experiences' === $screen_id) {
			wp_enqueue_style('datatables');
			wp_enqueue_style('lsc-curated-experiences');
		}
	}


	/**
	 * Enqueue scripts.
	 */
	public function admin_scripts()
	{
		global $post;
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';

		// Register scripts
		wp_register_script('lsc-settings', lsc_asset_path('js/settings.js'), array('jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-ui-progressbar'), LSC_VERSION);
		wp_register_script('lsc-resource', lsc_asset_path('js/resource.js'), array('jquery'), LSC_VERSION);
		wp_register_script('lsc-topic', lsc_asset_path('js/topic.js'), array('jquery'), LSC_VERSION);
		wp_register_script('lsc-curated-experiences', lsc_asset_path('js/curated-experiences.js'), array('jquery'), LSC_VERSION);
		wp_register_script('datatables', '//cdn.datatables.net/v/dt/jqc-1.12.4/dt-1.10.21/r-2.2.5/datatables.min.js', array('jquery'), null);

		if ('settings_page_integration-settings' == $screen_id) {
			wp_enqueue_script('lsc-settings');

			$params = array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'import_data_nonce' => wp_create_nonce('import-data'),
				'erase_data_nonce' => wp_create_nonce('erase-data'),
			);

			wp_localize_script('lsc-settings', 'lsc_import_params', $params);
		}

		if ('post' === $screen_id) {
			wp_enqueue_script('lsc-resource');

			$params = array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'upload_resource_nonce' => wp_create_nonce('upload-resource'),
				'post_id' => $post->ID
			);

			wp_localize_script('lsc-resource', 'lsc_resource_params', $params);
		}

		if ('edit-category' === $screen_id && !empty($_REQUEST['tag_ID'])) {
			wp_enqueue_script('lsc-topic');

			$params = array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'upload_topic_nonce' => wp_create_nonce('upload-topic'),
				'term_id' => absint($_REQUEST['tag_ID'])
			);

			wp_localize_script('lsc-topic', 'lsc_topic_params', $params);
		}

		if ('toplevel_page_curated-experiences' === $screen_id) {
			wp_enqueue_script('datatables');
			wp_enqueue_script('lsc-curated-experiences');

			$params = array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'curated_experiences_nonce' => wp_create_nonce('curated-experiences')
			);

			wp_localize_script('lsc-curated-experiences', 'lsc_ce_params', $params);
		}
	}
}
