<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
Plugin Name: Alt Text Generator AI
Plugin URI: https://alttextgeneratorai.com
Description: Automatically generates alt text for uploaded images.
Version: 1.0.6
Author: Bryam Loaiza
License: GPLv2
Terms of use: https://alttextgeneratorai.com/terms-of-use
*/

// Activation hook
register_activation_hook(__FILE__, 'alt_text_activate');
function alt_text_activate() {
    // Activation tasks if any
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'alt_text_deactivate');
function alt_text_deactivate() {
    // Deactivation tasks if any
}

// Add settings page
add_action('admin_menu', 'alt_text_add_settings_page');
function alt_text_add_settings_page() {
    add_menu_page('Alt Text Generator AI Settings', 'Alt Text Generator AI', 'manage_options', 'alt-text', 'alt_text_settings_page');
}

// Settings page content
function alt_text_settings_page() {
    $verified_status = get_option('alt_text_api_key_verified', false);
    $free_rewrites_left = '';
     
    if ($verified_status) {
        $free_rewrites_left = get_option('alt_text_free_rewrites_left', '');
    }

    if (isset($_POST['verify']) && wp_verify_nonce(wp_unslash(sanitize_text_field($_POST['_wpnonce'])), 'alt_text_verify_api_key')) {
        $api_key = sanitize_text_field($_POST['alt_text_api_key']);
        $website_domain = sanitize_text_field($_POST['alt_text_website_domain']);
        update_option('alt_text_api_key', $api_key);
        update_option('alt_text_website_domain', $website_domain);

        $saved_api_key = get_option('alt_text_api_key');
        echo '<div class="updated"><p>Saved API Key: ' . esc_html($saved_api_key) . '</p></div>';

        $saved_website_domain = get_option('alt_text_website_domain');
        echo '<div class="updated"><p>Saved Domain: ' . esc_html($saved_website_domain) . '</p></div>';

        $result = alt_text_verify_api_key($api_key);

        if ($result !== false) {
            update_option('alt_text_api_key_verified', true);
            update_option('alt_text_free_rewrites_left', $result);
            $free_rewrites_left = $result;
            echo '<div class="updated"><p>' . esc_html__('You are verified! Thanks') . '</p></div>';
        } else {
            update_option('alt_text_api_key_verified', false);
            $free_rewrites_left = 'N/A';
            echo '<div class="error"><p>' . esc_html__('Invalid API Key') . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h2>Alt Text Generator AI Settings</h2>
        <form method="post" action="">
            <?php wp_nonce_field('alt_text_verify_api_key'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key:</th>
                    <td><input type="text" name="alt_text_api_key" value="<?php echo esc_attr(get_option('alt_text_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Website Domain:</th>
                    <td><input type="text" name="alt_text_website_domain" value="<?php echo esc_attr(get_option('alt_text_website_domain')); ?>" />
                    <p>e.g. mywebsite.com</p>
                </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Credits:</th>
                    <td><?php echo esc_html($free_rewrites_left !== '' ? $free_rewrites_left : 'N/A'); ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Please click on verify to see current credits</th>
                </tr>
                <tr valign="top">
                    <th scope="row"><a href="https://alttextgeneratorai.com" target="_blank">alttextgeneratorai.com</a></th>
                </tr>
            </table>
            <?php submit_button('Verify', 'secondary', 'verify', false); ?>
        </form>
    </div>
    <?php
}

// Function to verify API key via HTTP call
function alt_text_verify_api_key($api_key) {
    $endpoint = 'https://alttextgeneratorai.com/api/verify';
    $data = array('apiKey' => $api_key);
    $response = wp_remote_post($endpoint, array(
        'body'        => wp_json_encode($data),
        'headers'     => array('Content-Type' => 'application/json'),
        'timeout'     => 30,
        'redirection' => 5,
    ));
    if (is_wp_error($response)) {
        error_log('HTTP Error: ' . $response->get_error_message());
        return false;
    }
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    if (isset($result['freeRewritesLeft'])) {
        return $result['freeRewritesLeft'];
    } else {
        return false;
    }
}

// Hook into the image upload process to generate alt text
add_filter('wp_generate_attachment_metadata', 'alt_text_process_images_on_raw_upload', 10, 2);
function alt_text_process_images_on_raw_upload($data, $attachment_id) {
    alt_text_generate($attachment_id);
    return $data;
}


// Function to generate alt text for an image
function alt_text_generate($attachment_id) {
    $image_url = wp_get_attachment_url($attachment_id); // Get the image URL
    $api_key = get_option('alt_text_api_key');
    $generated_alt_text = alt_text_generate_from_site_endpoint($image_url, $api_key);
    if ($generated_alt_text) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $generated_alt_text);
    }
    return $generated_alt_text;
}


// Function to trigger alt text generation via your site's endpoint
function alt_text_generate_from_site_endpoint($image_url, $api_key) {
    $current_year = date('Y');
    $current_month = date('m');
    $website_domain = get_option('alt_text_website_domain');
    $endpoint = 'https://alttextgeneratorai.com/api/wp';
    $data = array(
        'image' => $image_url,
        'wpkey' => $api_key,
        'year' => $current_year,
        'month' => $current_month,
        'website_domain' => $website_domain,
    );
    $response = wp_remote_post($endpoint, array(
        'body'        => wp_json_encode($data),
        'headers'     => array('Content-Type' => 'application/json'),
        'timeout'     => 30,
        'redirection' => 5,
    ));
    if (is_wp_error($response)) {
        error_log('HTTP Error: ' . $response->get_error_message());
        return 'Alt text generation failed.';
    }
    $body = wp_remote_retrieve_body($response);
    return $body;
}

// Add a custom button below the Alternative Text field in the Media Library
add_filter('attachment_fields_to_edit', 'alt_text_add_generate_button', 10, 2);
function alt_text_add_generate_button($form_fields, $post) {
    if ($post->post_type === 'attachment' && wp_attachment_is_image($post->ID)) {
        $form_fields['generate_alt_text_id'] = array(
            'input' => 'html',
            'html' => '<input type="hidden" class="alt_txt_attachment_id" value="' . esc_attr($post->ID) . '">'
        );
    }
    return $form_fields;
}


// Handle AJAX request to generate alt text
add_action('wp_ajax_alt_text_generate', 'alt_text_ajax_generate');
function alt_text_ajax_generate() {
    check_ajax_referer('alt_text_nonce', 'nonce');
    $attachment_id = intval($_POST['attachment_id']);
    if (!$attachment_id) {
        wp_send_json_error(array('message' => 'Invalid attachment ID.'));
    }

    // Generate the alt text for the image
    $generated_alt_text = alt_text_generate($attachment_id);
    //echo esc_html($generated_alt_text);
    if ($generated_alt_text) {
    // Update the alt text for the attachment
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $generated_alt_text);
    // Return success response
    wp_send_json_success(array('alt_text' => $generated_alt_text));
    } else {
    wp_send_json_error(array('message' => 'Failed to generate alt text.'));
    }
}


// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'alt_text_enqueue_admin_scripts');
function alt_text_enqueue_admin_scripts() {
    wp_enqueue_script('alt-text-admin-js', plugin_dir_url(__FILE__) . 'js/alt-text.js', array('jquery'), '1.7', true);
    
    wp_localize_script('alt-text-admin-js', 'altTextAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('alt_text_nonce')
    ));
}
