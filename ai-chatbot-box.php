<?php
/**
 * Plugin Name: AI Chatbot Box
 * Description: Floating AI chatbot using OpenRouter API.
 * Version: 1.0
 * Author: Kevin Victor Lepiten
 */

if (!defined('ABSPATH'))
    exit; // no direct access

// Enqueue scripts & styles
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ai-chatbot-style', plugin_dir_url(__FILE__) . 'css/chatbot.css');
    wp_enqueue_script('ai-chatbot-script', plugin_dir_url(__FILE__) . 'js/chatbot.js', ['jquery'], null, true);

    wp_localize_script('ai-chatbot-script', 'aiChatbot', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_chat_nonce')
    ]);
});

// Output chatbot HTML
add_action('wp_footer', function () {
    ?>
    <!-- Chatbot Toggle Button -->
    <div id="ai-chatbot-toggle">💬</div>

    <!-- Chatbot Window -->
    <div id="ai-chatbot" style="display:none;">
        <div id="ai-chatbot-header">
            🤖 Support Chatbot
            <span id="ai-chatbot-close">&times;</span>
        </div>
        <div id="ai-chatbot-messages"></div>
        <div id="ai-chatbot-input">
            <input type="text" id="ai-chatbot-text" placeholder="Type a message..." />
            <button id="ai-chatbot-send">Send</button>
        </div>
    </div>
    <?php
});


// AJAX handler
add_action('wp_ajax_ai_chatbot_send', 'ai_chatbot_send');
add_action('wp_ajax_nopriv_ai_chatbot_send', 'ai_chatbot_send');

function ai_chatbot_send()
{
    check_ajax_referer('ai_chat_nonce', 'nonce');

    $user_message = sanitize_text_field($_POST['message'] ?? '');
    $api_key = "YOUR API KEY";

    global $wpdb;
    $keywords = explode(" ", $user_message);
    $like_sql = implode(" OR ", array_map(function ($k) use ($wpdb) {
        return "post_content LIKE '%" . esc_sql($k) . "%'";
    }, $keywords));

    // --- Step 1: Search WP posts/pages ---
    $results = $wpdb->get_results("
        SELECT ID, post_title, guid, post_content
        FROM {$wpdb->prefix}posts
        WHERE ($like_sql)
        AND post_status = 'publish'
        AND post_type IN ('post', 'page')
        LIMIT 5
    ");

    $context = "";
    foreach ($results as $r) {
        $context .= $r->post_title . ": " . $r->guid . "\n";

        // --- Step 2: Fetch custom fields (like email/phone) ---
        $meta = get_post_meta($r->ID);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                // Only include email/phone looking fields
                if (preg_match("/email|mail/i", $key) || preg_match("/phone|contact/i", $key)) {
                    $context .= ucfirst($key) . ": " . $value . "\n";
                }
            }
        }

        // --- Step 3: Scan post content for emails/phones ---
        preg_match_all("/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}/i", $r->post_content, $emails);
        preg_match_all("/\+?[0-9]{7,15}/", $r->post_content, $phones);

        if (!empty($emails[0])) {
            $context .= "Emails: " . implode(", ", $emails[0]) . "\n";
        }
        if (!empty($phones[0])) {
            $context .= "Phones: " . implode(", ", $phones[0]) . "\n";
        }
    }

    // --- Step 4: Send to OpenRouter API ---
    $data = [
        "model" => "meta-llama/llama-3.1-8b-instruct",
        "messages" => [
            ["role" => "system", "content" => "You are a helpful assistant. Suggest relevant WordPress pages/posts and any email/phone contact if applicable."],
            ["role" => "user", "content" => "User asked: $user_message\n\nRelevant content and contacts:\n$context"]
        ]
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $reply = $result['choices'][0]['message']['content'] ?? 'Sorry, no response.';

    wp_send_json(['reply' => $reply]);
}
