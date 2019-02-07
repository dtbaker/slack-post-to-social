<?php

/**
 * Plugin Name: dtbaker Slack Social
 * Description: Post social shit from Slack
 * Plugin URI: https://dtbaker.net
 * Version: 1.0.1
 * Author: dtbaker
 * Author URI: https://dtbaker.net
 * Text Domain: dtbaker-slack-social
 */


class DtbakerSlackSocial {
	private static $instance = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	public function init() {
		add_action('wp_ajax_slack_social',[$this,'slack_social']);
		add_action('wp_ajax_nopriv_slack_social',[$this,'slack_social']);
	}

	public function slack_social(){


		mail('dtbaker@gmail.com','Post to social',var_export($_REQUEST,true));

		echo 'done';
		exit;
	}

}

DtbakerSlackSocial::get_instance()->init();
