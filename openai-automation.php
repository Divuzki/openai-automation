<?php
/*
Plugin Name: OpenAI Automation
Description: Integrates OpenAI API for content automation.
Version: 1.0
Author: Divine Ikhuoria
*/

// Settings for API Key
function openai_automation_settings_init() {
    register_setting('openai_automation', 'openai_api_key');
    add_settings_section('openai_automation_section', 'OpenAI Settings', null, 'openai_automation');
    add_settings_field('openai_api_key', 'API Key', 'openai_api_key_callback', 'openai_automation', 'openai_automation_section');
}

function openai_api_key_callback() {
    $api_key = get_option('openai_api_key');
    echo '<input type="text" name="openai_api_key" value="' . esc_attr($api_key) . '" />';
}

add_action('admin_init', 'openai_automation_settings_init');

// Settings Page
function openai_automation_menu() {
    add_options_page('OpenAI Automation', 'OpenAI Automation', 'manage_options', 'openai-automation', 'openai_automation_options_page');
}

function openai_automation_options_page() {
    ?>
    <div class="wrap">
        <h1>OpenAI Automation Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('openai_automation');
            do_settings_sections('openai_automation');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', 'openai_automation_menu');

// Meta Box
function openai_automation_add_meta_box() {
    add_meta_box('openai_summary', 'OpenAI Summary', 'openai_summary_callback', 'post', 'side');
}

function openai_summary_callback($post) {
    wp_nonce_field('openai_summary_nonce', 'openai_summary_nonce');
    $summary = get_post_meta($post->ID, '_openai_summary', true);
    
    echo '<div class="openai-summary-container">';
    
    // Summary content area with conditional classes
    $summary_class = !empty($summary) ? 'has-summary' : 'empty-summary';
    echo '<div id="summary-content" class="' . esc_attr($summary_class) . '">';
    if (!empty($summary)) {
        echo '<p>' . esc_html($summary) . '</p>';
    } else {
        echo '<p><span class="dashicons dashicons-format-aside"></span> No summary generated yet.</p>';
    }
    echo '</div>';
    
    // Action buttons with icons
    echo '<div class="summary-actions">';
    echo '<button type="button" id="generate-summary" class="button button-primary"><span class="button-icon dashicons dashicons-text"></span>Generate Summary</button>';
    
    if (!empty($summary)) {
        echo '<button type="button" id="regenerate-summary" class="button"><span class="button-icon dashicons dashicons-update"></span>Regenerate Summary</button>';
    }
    
    echo '</div>';
    
    // Loading indicator
    echo '<div id="summary-loading" style="display: none;"><span class="dashicons dashicons-update"></span> Generating summary, please wait...</div>';
    
    echo '</div>'; // Close openai-summary-container
}

add_action('add_meta_boxes', 'openai_automation_add_meta_box');

// Enqueue Scripts
function openai_automation_enqueue_scripts($hook) {
    if ($hook != 'post.php' && $hook != 'post-new.php') {
        return;
    }
    
    // Enqueue WordPress dashicons
    wp_enqueue_style('dashicons');
    
    // Enqueue plugin scripts and styles
    wp_enqueue_style('openai-automation-css', plugin_dir_url(__FILE__) . 'openai-automation.css', array(), '1.0');
    wp_enqueue_script('openai-automation', plugin_dir_url(__FILE__) . 'openai-automation.js', array('jquery'), '1.0', true);
    
    // Localize script for AJAX
    wp_localize_script('openai-automation', 'openai_automation_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai_automation_nonce')
    ));
}

add_action('admin_enqueue_scripts', 'openai_automation_enqueue_scripts');

// AJAX Handler
function generate_openai_summary() {
    check_ajax_referer('openai_automation_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    
    // Get the post object instead of just the content field
    $post = get_post($post_id);
    
    // Check if we have a valid post
    if (!$post) {
        wp_send_json_error('Invalid post ID.');
    }
    
    // Get the latest content from the editor
    // This handles both published content and content being edited
    $post_content = $post->post_content;
    
    // If using Gutenberg, we might need to get content from post_content or blocks
    if (empty($post_content) && function_exists('has_blocks') && has_blocks($post)) {
        $blocks = parse_blocks($post->post_content);
        $post_content = '';
        foreach ($blocks as $block) {
            $post_content .= render_block($block);
        }
    }
    
    if (empty($post_content)) {
        wp_send_json_error('Post content is empty.');
    }
    $api_key = get_option('openai_api_key');
    if (empty($api_key)) {
        wp_send_json_error('API key not set.');
    }
    // Truncate content if it's too long for the API
    $max_content_length = 12000; // OpenAI has token limits
    if (strlen($post_content) > $max_content_length) {
        $post_content = substr($post_content, 0, $max_content_length);
    }
    
    // Set timeout for longer requests
    $response = wp_remote_post('https://api.openai.com/v1/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60, // Increase timeout for large content
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo-instruct', // More suitable for summarization
            'prompt' => 'Create a comprehensive summary of the following content, capturing the main points and key details: ' . $post_content,
            'max_tokens' => 1000, // Increased for better summaries
        ))
    ));
    if (is_wp_error($response)) {
        error_log('OpenAI API error: ' . $response->get_error_message());
        wp_send_json_error('Error connecting to OpenAI: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $error_message = wp_remote_retrieve_response_message($response);
        error_log('OpenAI API error: ' . $response_code . ' - ' . $error_message);
        wp_send_json_error('OpenAI API error: ' . $error_message);
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($body['choices'][0]['text'])) {
        error_log('OpenAI API unexpected response: ' . print_r($body, true));
        wp_send_json_error('Unexpected response from OpenAI. Please try again.');
    }
    
    if (isset($body['choices'][0]['text'])) {
        $summary = trim($body['choices'][0]['text']);
        update_post_meta($post_id, '_openai_summary', $summary);
        wp_send_json_success($summary);
    } else {
        error_log('OpenAI API no summary generated: ' . print_r($body, true));
        wp_send_json_error('Failed to generate summary. Please try again.');
    }
}

add_action('wp_ajax_generate_openai_summary', 'generate_openai_summary');
?>