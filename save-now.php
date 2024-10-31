<?php
/*
Plugin Name: Save Now
Plugin URI: https://zaclab.com/save-now-wordpress-plugin/
Description: Easily download other installed plugins as ZIP files from your WordPress admin interface.
Version: 1.0
Author: Zaclab
Author URI: https://zaclab.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// Security check
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add download link to plugins page
function sn_add_download_link($links, $plugin_file) {
    $download_url = wp_nonce_url(admin_url('admin-ajax.php?action=sn_download_plugin&plugin=' . urlencode($plugin_file)), 'sn_download_plugin');
    $links[] = '<a href="' . esc_url($download_url) . '">' . esc_html__('Download', 'save-now') . '</a>';
    return $links;
}
add_filter('plugin_action_links', 'sn_add_download_link', 10, 2);
add_filter('network_admin_plugin_action_links', 'sn_add_download_link', 10, 2);

// Handle plugin download request
function sn_download_plugin() {
    if (!current_user_can('manage_options') || !check_admin_referer('sn_download_plugin')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'save-now'));
    }

    if (isset($_GET['plugin'])) {
        $plugin = sanitize_text_field(urldecode($_GET['plugin']));
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
        
        // Check if the specified plugin is valid
        if (!is_dir($plugin_dir)) {
            wp_die(esc_html__('Invalid plugin directory.', 'save-now'));
        }

        $plugin_name = basename($plugin_dir);
        $zip_file = tempnam(sys_get_temp_dir(), 'plugin_') . '.zip';

        if (create_zip($plugin_dir, $zip_file)) {
            // Use WP_Filesystem to read and delete the file
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            // Read the zip file
            $file_contents = $wp_filesystem->get_contents($zip_file);
            if ($file_contents === false) {
                wp_die(esc_html__('Could not read ZIP file.', 'save-now'));
            }

            // Send the file to the browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . esc_attr($plugin_name) . '.zip"');
            header('Content-Length: ' . strlen($file_contents));
            echo $file_contents;

            // Clean up the temp file
            $wp_filesystem->delete($zip_file);
            exit;
        } else {
            wp_die(esc_html__('Could not create ZIP file.', 'save-now'));
        }
    } else {
        wp_die(esc_html__('No plugin specified.', 'save-now'));
    }
}
add_action('wp_ajax_sn_download_plugin', 'sn_download_plugin');
add_action('wp_ajax_nopriv_sn_download_plugin', 'sn_download_plugin');

// Function to create ZIP file
function create_zip($source, $zip_file) {
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        return false;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen($source) + 1);
            $zip->addFile($file_path, $relative_path);
        }
    }
    return $zip->close();
}

// Enqueue admin styles
function sn_enqueue_admin_styles($hook) {
    if ($hook == 'plugins.php') {
        wp_enqueue_style('sn-admin-styles', plugin_dir_url(__FILE__) . 'admin-style.css');
    }
}
add_action('admin_enqueue_scripts', 'sn_enqueue_admin_styles');

// Add plugin logo to the plugins page
function sn_add_plugin_logo($plugin_meta, $plugin_file, $plugin_data, $status) {
    if ($plugin_file === plugin_basename(__FILE__)) {
        $logo_url = plugin_dir_url(__FILE__) . 'images/plugin-logo.png';
        $plugin_meta[] = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr__('Plugin Logo', 'save-now') . '" style="width: 32px; height: 32px;">';
    }
    return $plugin_meta;
}
add_filter('plugin_row_meta', 'sn_add_plugin_logo', 10, 4);
