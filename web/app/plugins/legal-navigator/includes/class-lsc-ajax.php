<?php

namespace LSC\Includes;

if (!defined('ABSPATH')) {
	die('-1');
}

class LSC_AJAX
{

	/**
	 * Hook in ajax handlers.
	 */
	public static function init()
	{
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events()
	{
		$ajax_events = array(
			'import_data',
			'upload_resource',
			'upload_topic'
		);

		foreach ($ajax_events as $ajax_event) {
			add_action('wp_ajax_lsc_' . $ajax_event, array(__CLASS__, $ajax_event));
		}
	}

	public static function import_data()
	{
		check_ajax_referer('import-data', 'security');

		$server = new LSC_Server();
		$errors = $server->import_data();

		$message = [
			'title' => 'Import successful',
			'message' => 'Data from Legal Navigator has imported.'
		];

		if ($errors) {
			$message['title'] = 'Import unsuccessful';
			$message['message'] = '';
			foreach ($errors as $error) {
				$message['message'] .= $error . '<br>';
			}
		}

		wp_send_json($message);
	}

	public static function upload_resource()
	{
		check_ajax_referer('upload-resource', 'security');

		$params = lsc_clean($_POST);
		$server_id = $params['server_id'];
		$post = get_post($params['post_id']);

		if (!$post) {
			wp_send_json_error('The post is not saved.');
		}

		$created_by = get_user_by('id', $post->post_author);
		$modified_by = get_user_by('id', get_post_meta($post->ID, '_modified_by', true));

		$locations = get_field('locations', $post->ID);
		$prepared_locations = [];
		foreach ($locations as $location) {
			$prepared_locations[] = [
				'zipCode' => $location['zip_code'],
				'city' => $location['city'],
				'county' => $location['county'],
				'state' => $location['state'],
			];
		}

		$topics = get_field('topics', $post->ID);
		$prepared_topics = [];
		$prepared_ranking = [];
		foreach ($topics as $topic) {
			$prepared_topics[] = [
				'id' => get_term_meta($topic['topic']->term_id, '_topic_id', true)
			];
			$prepared_ranking[$topic['topic']->name] = (int)$topic['ranking'];
		}

		$resource = [
			'id' => get_post_meta($post->ID, '_resource_id', true),
			'overview' => $post->post_content,
			'name' => $post->post_title,
			'description' => $post->post_excerpt,
			'resourceType' => get_field('resource_type', $post->ID),
			'url' => get_field('resource_url', $post->ID),
			'topicTags' => $prepared_topics,
			'organizationalUnit' => get_field('organizational_unit', $post->ID),
			'location' => $prepared_locations,
			'createdBy' => $created_by->display_name,
			'createdTimeStamp' => date('Y-m-d\TH:i:s.u\Z', strtotime($post->post_date)),
			'modifiedBy' => $modified_by->display_name,
			'modifiedTimeStamp' => date('Y-m-d\TH:i:s.u\Z', strtotime($post->post_modified)),
			'display' => $post->post_status === 'publish' ? 'Yes' : 'No',
			'ranking' => $prepared_ranking,
			'delete' => 'N',
			'xlsFileName' => null,
			'resourceCategory' => null
		];

		$server = new LSC_Server();
		$result = $server->resource_request($server_id, 'insert', 'resources', $resource);

		if ($result) {
			$updates = get_post_meta($post->ID, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$updates[$server_id] = date('Y-m-d H:i:s');

			update_post_meta($post->ID, '_server_updates', $updates);

			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

		wp_send_json($resource);
		// wp_send_json_success();
		// wp_send_json_error();
	}

	public static function upload_topic()
	{
		check_ajax_referer('upload-topic', 'security');

		$params = lsc_clean($_POST);
		$server_id = $params['server_id'];
		$term = get_term($params['term_id']);

		$created_by = get_user_by('id', get_post_meta($term->term_id, '_created_by', true));
		$modified_by = get_user_by('id', get_post_meta($term->term_id, '_modified_by', true));

		$locations = get_field('locations', "category_{$term->term_id}");
		$prepared_locations = [];
		foreach ($locations as $location) {
			$prepared_locations[] = [
				'zipCode' => $location['topic_zip_code'],
				'city' => $location['topic_city'],
				'county' => $location['topic_county'],
				'state' => $location['topic_state'],
			];
		}

		$topic = [
			'id' => get_term_meta($term->term_id, '_topic_id', true),
			'overview' => $term->description,
			'parentTopicId' => $term->parent ? [['id' => $term->parent]] : [],
			'keywords' => get_term_meta($term->term_id, 'keywords', true),
			'icon' => wp_get_attachment_url(get_term_meta($term->term_id, 'icon', true)),
			'name' => $term->name,
			'organizationalUnit' => get_term_meta($term->term_id, 'topic_organizational_unit', true),
			'location' => $prepared_locations,
			'createdBy' => $created_by->display_name,
			'createdTimeStamp' => date('Y-m-d\TH:i:s.u\Z', strtotime(get_term_meta($term->term_id, '_created_time', true))),
			'modifiedBy' => $modified_by->display_name,
			'modifiedTimeStamp' => date('Y-m-d\TH:i:s.u\Z', strtotime(get_term_meta($term->term_id, '_modified_time', true))),
			'display' => get_term_meta($term->term_id, 'display', true) ? 'Yes' : 'No',
			'ranking' => get_term_meta($term->term_id, 'topic_ranking', true),
			'resourceCategory' => null,
			'resourceType' => 'Topics',
			'url' => null,
			'topicTags' => [],
			'delete' => 'N',
			'xlsFileName' => null,
			'description' => null
		];

		$server = new LSC_Server();
		$result = $server->resource_request($server_id, 'insert', 'topics', $topic);

		if ($result) {
			$updates = get_term_meta($term->term_id, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$updates[$server_id] = date('Y-m-d H:i:s');

			update_term_meta($term->term_id, '_server_updates', $updates);

			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

		wp_send_json($topic);
		// wp_send_json_success();
		// wp_send_json_error();
	}
}
