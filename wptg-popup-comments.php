<?php

/**
 * Plugin Name: WPTG Popup Comments
 * Plugin URI: https://wptechgiants.com/
 * Description: A Plugin that displays post comments in a popup.
 * Version: 1.0
 * Author: WP Tech Giants
 * Author URI: https://wptechgiants.com/
 * Text Domain: wptg-popup-comments
 **/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPTG_Popup_Comments')) {
    class WPTG_Popup_Comments
    {
        public function __construct()
        {
            $this->wptg_public();
            if (is_admin()) {
                $this->wptg_admin();
            }
        }

        public function wptg_public()
        {
            require 'public/wptg-public.php';
            $wptg_public = new WPTG_Public();

            add_action('wp_enqueue_scripts', [$wptg_public, 'wptg_public_enqueue_scripts']);
            add_action('wp_ajax_ajaxcomments', [$wptg_public, 'wptg_public_submit_ajax_comment']);
            add_action('wp_ajax_nopriv_ajaxcomments', [$wptg_public, 'wptg_public_submit_ajax_comment']);
            add_action('wp_footer', [$wptg_public, 'wptg_footer_popup_wrap']);
            add_shortcode('wptg_popup_link', [$wptg_public, 'wptg_popup_shortcode']);
            add_action('wp_ajax_wptg_comment_wrapper', [$wptg_public, 'wptg_wptg_comment_wrapper']);
            add_action('wp_ajax_nopriv_wptg_comment_wrapper', [$wptg_public, 'wptg_wptg_comment_wrapper']);
        }

        public function wptg_admin()
        {
        }
    }
    new WPTG_Popup_Comments();
}