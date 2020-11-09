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
			'upload_post',
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

	public static function upload_post()
	{
		check_ajax_referer('upload-post', 'security');

		$params = $_POST;

		if (!$params['post_id']) {
			wp_send_json_error('The post is not saved.');
		}

		$post_type = get_post_type($params['post_id']);

		switch ($post_type) {
			case 'post':
				LSC_AJAX::upload_resource($params);
				break;
			case 'page':
				LSC_AJAX::upload_page($params);
				break;
		}
	}

	private static function upload_resource($params)
	{
		$post = get_post($params['post_id']);

		if (!$post) {
			wp_send_json_error('The post is not saved.');
		}

		$scope_id = $params['scope_id'];

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

		if ($resource_uid = get_post_meta($post->ID, '_remote_id', true)) {
			$resource['id'] = $resource_uid;
		}

		$server = new LSC_Server();
		$result = $server->api_request($scope_id, 'topics-resources/resources/documents/upsert', [$resource]);

		if ($result) {
			update_post_meta($post->ID, '_remote_id', $result['id']);

			$updates = get_post_meta($post->ID, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$date = date('Y-m-d H:i:s');
			$updates[$scope_id] = $date;
			update_post_meta($post->ID, '_server_updates', $updates);

			wp_send_json_success([
				'text' => 'The resource is successfully uploaded',
				'date' => wp_date('d/m/Y H:i', strtotime($date))
			]);
		}

		wp_send_json_error(['text' => 'Error during upload the resource']);
	}

	private static function upload_page($params)
	{
		$post = get_post($params['post_id']);

		if (!$post) {
			wp_send_json_error('The post is not saved.');
		}

		$scope_id = $params['scope_id'];

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

		$page_type = get_field('page_type', $post->ID);

		$page = [
			'id' => get_field('_remote_id', $post->ID),
			'location' => $prepared_locations,
			'name' => $page_type,
			'organizationalUnit' => get_field('organizational_unit', $post->ID),
		];

		$page_fields = [];
		$page_slug = '';

		switch ($page_type) {
			case 'HelpAndFAQPage':
				$page_fields = [
					'description' => get_field('help_and_faq_page_description', $post->ID),
					'image' => [
						'source' => get_field('help_and_faq_page_image_source', $post->ID),
						'altText' => get_field('help_and_faq_page_image_alt', $post->ID)
					],
					'faqs' => get_field('help_and_faq_page_faqs', $post->ID)
				];
				$page_slug = 'help-and-faq';
				break;
			case 'PrivacyNoticePage':
				$page_fields = [
					'description' => get_field('privacy_notice_page_description', $post->ID),
					'image' => [
						'source' => get_field('privacy_notice_page_image_source', $post->ID),
						'altText' => get_field('privacy_notice_page_image_alt', $post->ID)
					],
					'details' => get_field('privacy_notice_page_details', $post->ID)
				];
				$page_slug = 'privacy';
				break;
			case 'PersonalizedActionPlanPage':
				$sponsors = get_field('personalized_action_plan_page_sponsors', $post->ID);
				$prepared_sponsors = [];
				foreach ($sponsors as $sponsor) {
					$prepared_sponsors[] = [
						'source' => base64_encode(file_get_contents(wp_get_original_image_path($sponsor['image']['id']))),
						'altText' => $sponsor['image']['alt'],
					];
				}
				$page_fields = [
					'description' => get_field('personalized_action_plan_page_description', $post->ID),
					'sponsors' => $prepared_sponsors
				];
				$page_slug = 'personalizedplan';
				break;
			case 'HomePage':
				$steps = get_field('home_page_guided_assistant_overview_steps', $post->ID);
				$prepared_steps = [];
				foreach ($steps as $k => $step) {
					$prepared_steps[] = [
						'order' => $k + 1,
						'description' => $step['description']
					];
				}

				$slides = get_field('home_page_carousel', $post->ID);
				$prepared_slides = [];
				foreach ($slides as $slider) {
					$prepared_slides[] = [
						'quote' => $slider['quote'],
						'author' => $slider['author'],
						'location' => $slider['location'],
						'image' => [
							'source' => base64_encode(file_get_contents(wp_get_original_image_path($slider['image']['id']))),
							'altText' => $slider['image']['alt'],
						],
					];
				}

				$sponsors = get_field('home_page_sponsor_overview_sponsors', $post->ID);
				$prepared_sponsors = [];
				foreach ($sponsors as $sponsor) {
					$prepared_sponsors[] = [
						'source' => base64_encode(file_get_contents(wp_get_original_image_path($sponsor['image']['id']))),
						'altText' => $sponsor['image']['alt'],
					];
				}

				$page_fields = [
					'hero' => [
						'heading' => get_field('home_page_hero_heading', $post->ID),
						'description' => [
							'text' => get_field('home_page_hero_description', $post->ID),
							'textWithLink' => [
								'urlText' => get_field('home_page_hero_text_with_link_url_text', $post->ID),
								'url' => get_field('home_page_hero_text_with_link_url', $post->ID)
							]
						],
						'image' => [
							'source' => get_field('home_page_hero_image_source', $post->ID),
							'altText' => get_field('home_page_hero_image_alt', $post->ID)
						]
					],
					'guidedAssistantOverview' => [
						'heading' => get_field('home_page_guided_assistant_overview_heading', $post->ID),
						'description' => [
							'text' =>
							get_field('home_page_guided_assistant_overview_description', $post->ID),
							'textWithLink' => [
								'urlText' =>
								get_field('home_page_guided_assistant_overview_text_with_link_url_text', $post->ID),
								'url' =>
								get_field('home_page_guided_assistant_overview_text_with_link_url', $post->ID),
							],
							'steps' => $prepared_steps
						],
						'button' => [
							'buttonText' => get_field('home_page_guided_assistant_overview_button_text', $post->ID),
							'buttonAltText' => get_field('home_page_guided_assistant_overview_button_alt_text', $post->ID),
							'buttonLink' => get_field('home_page_guided_assistant_overview_button_link', $post->ID),
						],
						'image' => [
							'source' => get_field('home_page_guided_assistant_overview_image_source', $post->ID),
							'altText' => get_field('home_page_guided_assistant_overview_image_alt', $post->ID)
						]
					],
					'topicAndResources' => [
						'heading' => get_field('home_page_topic_and_resources_heading', $post->ID),
						'button' => [
							'buttonText' => get_field('home_page_topic_and_resources_button_text', $post->ID),
							'buttonAltText' => get_field('home_page_topic_and_resources_button_alt_text', $post->ID),
							'buttonLink' => get_field('home_page_topic_and_resources_button_link', $post->ID)
						]
					],
					'carousel' => [
						'slides' => $prepared_slides
					],
					'sponsorOverview' => [
						'heading' => get_field('home_page_sponsor_overview_heading', $post->ID),
						'description' => get_field('home_page_sponsor_overview_description', $post->ID),
						'sponsors' => $prepared_sponsors,
						'button' => [
							'buttonText' => get_field('home_page_sponsor_overview_button_text', $post->ID),
							'buttonAltText' => get_field('home_page_sponsor_overview_button_alt_text', $post->ID),
							'buttonLink' => get_field('home_page_sponsor_overview_button_link', $post->ID),
						]
					],
					'privacy' => [
						'heading' => get_field('home_page_privacy_heading', $post->ID),
						'description' => get_field('home_page_privacy_description', $post->ID),
						'button' => [
							'buttonText' => get_field('home_page_privacy_button_text', $post->ID),
							'buttonAltText' => get_field('home_page_privacy_button_alt_text', $post->ID),
							'buttonLink' => get_field('home_page_privacy_button_link', $post->ID),
						],
						'image' => [
							'source' => get_field('home_page_privacy_image_source', $post->ID),
							'altText' => get_field('home_page_privacy_image_alt', $post->ID),
						]
					],
					'helpText' => [
						'beginningText' => get_field('home_page_help_text_beginning_text', $post->ID),
						'phoneNumber' => get_field('home_page_help_text_phone_number', $post->ID),
						'endingText' => get_field('home_page_help_text_ending_text', $post->ID),
					]
				];
				$page_slug = 'home';
				break;
			case 'AboutPage':
				$sponsors = get_field('about_page_mission_sponsors', $post->ID);
				$prepared_sponsors = [];
				foreach ($sponsors as $sponsor) {
					$prepared_sponsors[] = [
						'source' => base64_encode(file_get_contents(wp_get_original_image_path($sponsor['image']['id']))),
						'altText' => $sponsor['image']['alt'],
					];
				}

				$service_image = get_field('about_page_service_image', $post->ID);
				$privacy_promise_image = get_field('about_page_privacy_promise_image', $post->ID);

				$news = get_field('about_page_in_the_news_news', $post->ID);
				$prepared_news = [];
				foreach ($news as $news_item) {
					$prepared_news[] = [
						'title' => $news_item['title'],
						'description' => $news_item['description'],
						'url' => $news_item['url'],
						'image' => [
							'source' => base64_encode(file_get_contents(wp_get_original_image_path($news_item['image']['id']))),
							'altText' => $news_item['image']['alt'],
						],
					];
				}

				$page_fields = [
					'aboutImage' => [
						'source' => get_field('about_page_image_source', $post->ID),
						'altText' => get_field('about_page_image_alt', $post->ID),
					],
					'mission' => [
						'sponsors' => $prepared_sponsors,
						'title' => get_field('about_page_mission_title', $post->ID),
						'description' => get_field('about_page_mission_description', $post->ID)
					],
					'service' => [
						'title' => get_field('about_page_service_title', $post->ID),
						'description' => get_field('about_page_service_description', $post->ID),
						'image' => [
							'source' => base64_encode(file_get_contents(wp_get_original_image_path($service_image['id']))),
							'altText' => $service_image['alt'],
						],
						'guidedAssistantButton' => [
							'buttonText' => get_field('about_page_service_guided_assistant_button_text', $post->ID),
							'buttonAltText' => get_field('about_page_service_guided_assistant_button_alt_text', $post->ID),
							'buttonLink' => get_field('about_page_service_guided_assistant_button_link', $post->ID),
						],
						'topicsAndResourcesButton' => [
							'buttonText' => get_field('about_page_service_topics_and_resources_button_text', $post->ID),
							'buttonAltText' => get_field('about_page_service_topics_and_resources_button_alt_text', $post->ID),
							'buttonLink' => get_field('about_page_service_topics_and_resources_button_link', $post->ID),
						],
					],
					'privacyPromise' => [
						'title' => get_field('about_page_privacy_promise_title', $post->ID),
						'description' => get_field('about_page_privacy_promise_description', $post->ID),
						'image' => [
							'source' => base64_encode(file_get_contents(wp_get_original_image_path($privacy_promise_image['id']))),
							'altText' => $privacy_promise_image['alt'],
						],
						'privacyPromiseButton' => [
							'buttonText' => get_field('about_page_privacy_promise_button_text', $post->ID),
							'buttonAltText' => get_field('about_page_privacy_promise_button_alt_text', $post->ID),
							'buttonLink' => get_field('about_page_privacy_promise_button_link', $post->ID),
						],
					],
					'contactUs' => [
						'title' => get_field('about_page_contact_us_title', $post->ID),
						'description' => get_field('about_page_contact_us_description', $post->ID),
						'email' => get_field('about_page_contact_us_email', $post->ID),
					],
					'mediaInquiries' => [
						'title' => get_field('about_page_media_inquiries_title', $post->ID),
						'description' => get_field('about_page_media_inquiries_description', $post->ID),
						'email' => get_field('about_page_media_inquiries_email', $post->ID),
					],
					'inTheNews' => [
						'title' => get_field('about_page_in_the_news_title', $post->ID),
						'description' => get_field('about_page_in_the_news_description', $post->ID),
						'news' => $prepared_news,
					]
				];
				$page_slug = 'about';
				break;
		}

		if (!$page_slug) {
			wp_send_json_error(['text' => 'Error during upload the page']);
		}

		$page = array_merge($page, $page_fields);
		$server = new LSC_Server();
		$result = $server->api_request($scope_id, "static-resources/{$page_slug}/upsert", $page);

		if ($result) {
			update_post_meta($post->ID, '_remote_id', $result['id']);

			$updates = get_post_meta($post->ID, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$date = date('Y-m-d H:i:s');
			$updates[$scope_id] = $date;
			update_post_meta($post->ID, '_server_updates', $updates);

			wp_send_json_success([
				'text' => 'The page is successfully uploaded',
				'date' => wp_date('d/m/Y H:i', strtotime($date))
			]);
		}

		wp_send_json_error(['text' => 'Error during upload the page']);
	}

	public static function upload_topic()
	{
		check_ajax_referer('upload-topic', 'security');

		$params = $_POST;
		$scope_id = $params['scope_id'];
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
		$result = $server->api_request($scope_id, 'topics-resources/topics/documents/upsert', [$topic]);

		if ($result) {
			update_term_meta($term->term_id, '_topic_id', $result['id']);

			$updates = get_term_meta($term->term_id, '_server_updates', true);

			if (!$updates) {
				$updates = [];
			}

			$date = date('Y-m-d H:i:s');
			$updates[$scope_id] = $date;
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

		$params = $_POST;

		$items = [];
		$server = new LSC_Server();
		$response = $server->get_curated_experiences($params['scope_id'], $params['org_unit']);

		if ($response) {
			$items = $response;
		}

		wp_send_json($items);
	}

	public static function delete_curated_experience()
	{
		check_ajax_referer('curated-experiences', 'security');

		$params = $_POST;

		$server = new LSC_Server();
		$response = $server->delete_curated_experience($params['scope_id'], $params['item']);

		if ($response) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	public static function upload_curated_experience()
	{
		check_ajax_referer('curated-experiences', 'security');

		$params = $_POST;
		$scope_id = $params['scope_id'];

		$form_data = [
			'name' => $params['name'],
			'description' => $params['description'],
			'file' => !empty($_FILES) ? $_FILES['templateFile'] : []
		];

		$server = new LSC_Server();
		$result = $server->upload_curated_experience($scope_id, $form_data);

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
