<?php

/**
 * Plugin Name: dtbaker Slack Social
 * Description: Post social shit from Slack
 * Plugin URI: https://dtbaker.net
 * Version: 1.0.0
 * Author: dtbaker
 * Author URI: https://dtbaker.net
 * Text Domain: dtbaker-slack-social
 */


class DtbakerSlackSocial {

	public function init() {
		// Hooks into WordPress startup actions
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	public function rest_api_init() {
		// This opens up a `POST` endpoint at /wp-json/dtbaker-slack-social/v1/submit on the website.
		register_rest_route( 'dtbaker-slack-social/v1', '/submit', array(
			'methods'  => [ 'POST' ],
			'callback' => [ $this, 'api_callback' ],
		) );
	}

	public function api_callback() {
		// When someone pushes that button in Slack, entire message object ends up here.
		// Get rid of default WordPress slashes, and decode the json into an array so we can easily process it.
		$payload = json_decode( stripslashes_deep( $_POST['payload'] ), true );
		// Check payload contains enough information to process.
		if ( $payload && is_array( $payload ) && ! empty( $payload['response_url'] ) && ! empty( $payload['action_ts'] ) ) {
			// Create a placeholder draft post to store our data.
			$id = wp_insert_post( [
				'post_title'   => $payload['action_ts'],
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_content' => ''
			] );
			if ( $id && ! is_wp_error( $id ) ) {
				// Blog post created successfully, throw entire payload into a post meta field for later processing.
				add_post_meta( $id, 'slack_payload', $payload );
				// Let the user know it's working (not really needed unless we're importing large files?)
				// $this->slack_response( $payload, 'Thanks, we\'re drafting your WordPress post now!' );
				// Convert the draft post into pending post.
				$this->process_slack_post( $id );
				wp_send_json_success( 'ok' );
			}
		}

		mail('dtbaker@gmail.com','error',var_export($payload,true));

		$this->slack_response( $payload, 'Sorry :( we failed to schedule this post for social. :( Ask Dave.' );
		wp_send_json_error( 'nup' );
	}

	public function process_slack_post( $post_id ) {

		if ( $post_id && $post = get_post( $post_id ) ) {
			$payload = get_post_meta( $post_id, 'slack_payload', true );

			// These callback_id's are configured in the slack app settings. We can have different button actions.
			switch ( $payload['callback_id'] ) {
				case 'blog_post':
					// Add it to a slack category for easy querying from within WordPress.
					wp_set_object_terms( $post_id, 'slack', 'category' );

					// Tag the post with the channel name, except for certain channels.
					$channel = $payload['channel']['name'];
					switch ( $channel ) {
						case 'general':
							// todo: later we want to do different things for different channels.
							break;
					}
					wp_set_post_tags( $post_id, $channel );

					// Figure out a title to use from the post message data.
					$title = '';
					if ( ! empty( $payload['message']['text'] ) ) {
						$title = $payload['message']['text'];
					} else if ( ! empty( $payload['message']['attachments'] ) ) {
						foreach ( $payload['message']['attachments'] as $file ) {
							$title = $file['title'];
						}
					} else if ( ! empty( $payload['message']['files'] ) ) {
						foreach ( $payload['message']['files'] as $file ) {
							$title = $file['title'];
						}
					}

					// todo: Figure out what post content to generate based on message payload?
					// todo: look up slack username based on $payload['message']['user'] ID. So we can say "Hey Dave said ... on slack"
					$post_text = 'A message was posted on slack.';
					$post_text .= "\n\n\nDebug text for now:\n\n";
					$post_text .= '<pre>' . print_r( $payload['message'], true ) . '</pre>';

					wp_update_post( [
						'ID'           => $post_id,
						'post_title'   => $title,
						'post_name'    => $title,
						'post_content' => $post_text,
						'post_status'  => 'pending',
					] );

					$edit_url = get_edit_post_link( $post_id, 'slack' );
					if ( ! $edit_url ) {
						$edit_url = 'https://gctechspace.org/wp-admin/edit.php?post_status=pending&post_type=post';
					}
					$this->slack_response( $payload, "Thanks, we've drafted a WordPress blog post here for you: \n" . $edit_url );
					break;
			}
		}
	}

	public function slack_response( $payload, $message ) {
		if ( ! empty( $payload['response_url'] ) && $message ) {
			$ch   = curl_init( $payload['response_url'] );
			$data = http_build_query( [
				"payload" => json_encode( [
					"response_type"    => "ephemeral",
					"replace_original" => false,
					"text"             => $message
				] ),
			] );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 8 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_exec( $ch );
		}
	}

}

$slack = new DtbakerSlackSocial();
$slack->init();
