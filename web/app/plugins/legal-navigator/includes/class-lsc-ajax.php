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
			'upload_topic',
			'get_curated_experiences',
			'upload_curated_experience',
			'delete_curated_experience'
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
		if (!$locations) {
			$locations = [];
		}

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

		$resource_type = get_field('resource_type', $post->ID);

		$resource = [
			'overview' => $post->post_content,
			'name' => $post->post_title,
			'description' => $post->post_excerpt,
			'resourceType' => $resource_type,
			'url' => get_field('resource_url', $post->ID),
			'topicTags' => $prepared_topics,
			'organizationalUnit' => get_field('organizational_unit', $post->ID),
			'location' => $prepared_locations,
			'createdBy' => $created_by->display_name,
			'modifiedBy' => $modified_by->display_name,
			'display' => get_field('resource_display', $post->ID) ? 'Yes' : 'No',
			'ranking' => $prepared_ranking,
		];

		if ('Organizations' === $resource_type) {
			$resource['address'] = get_field('resource_address', $post->ID);
			$resource['telephone'] = get_field('resource_telephone', $post->ID);
			$resource['specialties'] = get_field('resource_specialties', $post->ID);
			$resource['qualifications'] = get_field('resource_qualifications', $post->ID);
			$resource['businessHours'] = get_field('resource_business_hours', $post->ID);
			$resource['resourceCategory'] = get_field('resource_category', $post->ID);
			$resource['eligibilityInformation'] = get_field('resource_eligibility_information', $post->ID);
		}

		if ($resource_uid = get_post_meta($post->ID, '_resource_id', true)) {
			$resource['id'] = $resource_uid;
		}

		$server = new LSC_Server();
		$result = $server->resource_request($server_id, 'insert', 'resources', [$resource]);

		if ($result) {
			update_post_meta($post->ID, '_resource_id', $result['id']);

			$updates = get_post_meta($post->ID, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$date = date('Y-m-d H:i:s');
			$updates[$server_id] = $date;
			update_post_meta($post->ID, '_server_updates', $updates);

			wp_send_json_success([
				'text' => 'The resource is successfully uploaded',
				'date' => wp_date('d/m/Y H:i', strtotime($date))
			]);
		}

		wp_send_json_error(['text' => 'Error during upload the resource']);
	}

	public static function upload_topic()
	{
		check_ajax_referer('upload-topic', 'security');

		$params = lsc_clean($_POST);
		$server_id = $params['server_id'];
		$term = get_term($params['term_id']);

		$created_by = get_user_by('id', get_term_meta($term->term_id, '_created_by', true));
		$modified_by = get_user_by('id', get_term_meta($term->term_id, '_modified_by', true));

		$locations = get_field('topic_locations', "category_{$term->term_id}");

		if (!$locations) {
			$locations = [];
		}

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
			'overview' => $term->description,
			'parentTopicId' => $term->parent ? [['id' => $term->parent]] : [],
			'keywords' => get_term_meta($term->term_id, 'keywords', true),
			'icon' => get_field('icon', "category_{$term->term_id}"),
			'name' => $term->name,
			'organizationalUnit' => get_term_meta($term->term_id, 'topic_organizational_unit', true),
			'location' => $prepared_locations,
			'createdBy' => $created_by->display_name,
			'modifiedBy' => $modified_by->display_name,
			'display' => get_term_meta($term->term_id, 'display', true) ? 'Yes' : 'No',
			'ranking' => get_term_meta($term->term_id, 'topic_ranking', true),
			'resourceType' => 'Topics',
		];

		if ($topic_uid = get_term_meta($term->term_id, '_topic_id', true)) {
			$topic['id'] = $topic_uid;
		}

		$server = new LSC_Server();
		$result = $server->resource_request($server_id, 'insert', 'topics', [$topic]);

		if ($result) {
			update_term_meta($term->term_id, '_topic_id', $result['id']);

			$updates = get_term_meta($term->term_id, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$date = date('Y-m-d H:i:s');
			$updates[$server_id] = $date;
			update_term_meta($term->term_id, '_server_updates', $updates);

			wp_send_json_success([
				'text' => 'The topic is successfully uploaded',
				'date' => wp_date('d/m/Y H:i', strtotime($date))
			]);
		}

		wp_send_json_error(['text' => 'Error during upload the topic']);
	}

	public static function get_curated_experiences()
	{
		check_ajax_referer('curated-experiences', 'security');

		$params = lsc_clean($_POST);

		$items = [];
		$server = new LSC_Server();
		$response = $server->get_curated_experiences($params['server_id'], $params['org_unit']);

		if ($response) {
			$items = $response;
		}

		wp_send_json($items);
	}

	public static function delete_curated_experience()
	{
		check_ajax_referer('curated-experiences', 'security');

		$params = lsc_clean($_POST);

		$server = new LSC_Server();
		$response = $server->delete_curated_experience($params['server_id'], $params['item']);

		if ($response) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	public static function upload_curated_experience()
	{
		check_ajax_referer('curated-experiences', 'security');

		$params = lsc_clean($_POST);
		$server_id = $params['server_id'];

		$form_data = [
			'name' => $params['name'],
			'description' => $params['description'],
			'file' => !empty($_FILES) ? $_FILES['templateFile'] : []
		];

		$server = new LSC_Server();
		$result = $server->upload_curated_experience($server_id, $form_data);

		if ($result) {
			if ($result['errorCode']) {
				if ($result['message']) {
					wp_send_json_error($result['message']);
				} else if (!empty($result['details'])) {
					wp_send_json_error($result['details']);
				}
			}

			wp_send_json_success($result['message']);
		}

		wp_send_json_error('Error during upload Curated Experiences');
	}
}
