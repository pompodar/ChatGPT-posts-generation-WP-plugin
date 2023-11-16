<?php
/*
Plugin Name: Chat GPT Pages Generator
Description: Plugin to generate WP pages using OpenAI API.
Version: 1.0
Author: Sviat
*/

// Add admin menu item
function ai_content_generator_menu() {
    add_menu_page('AI Content Generator', 'AI Content Generator', 'manage_options', 'ai-content-generator', 'ai_content_generator_page');
}

add_action('admin_menu', 'ai_content_generator_menu');

// Function to send a chat-based completion request to OpenAI
function chat_completion_with_openai($messages, $model, $api_key) {
    $url = 'https://api.openai.com/v1/chat/completions';  
    $headers = array(
        "Authorization: Bearer  {$api_key}",
        "Content-Type: application/json"
    );
    
    $data = array(
        "model" => $model,
        "messages" => $messages,
        "max_tokens" => 1600,
        
    );
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    $result = curl_exec($curl);
    
    if (curl_errno($curl)) {
        echo 'Error:' . curl_error($curl);
    } else {
        return json_decode($result)->choices[0]->message->content;
    }
    
    curl_close($curl); 
}

// Create the admin page
function ai_content_generator_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission
    if (isset($_POST['generate_post'])) {
        $model = sanitize_text_field($_POST['model']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $title_prompt = sanitize_text_field($_POST['title_prompt']);
        $content_prompt = sanitize_text_field($_POST['content_prompt']);
        $meta_desc_prompt = sanitize_text_field($_POST['meta_desc_prompt']);

        // Save form values in options
        update_option('ai_content_generator_model', $model);
        update_option('ai_content_generator_api_key', $api_key);
        update_option('ai_content_generator_title_prompt', $title_prompt);
        update_option('ai_content_generator_content_prompt', $content_prompt);
        update_option('ai_content_generator_meta_desc_prompt', $meta_desc_prompt);

        // Create chat messages using the provided prompts for title and content
        $title_messages = array(
            array(
                'role' => 'user',
                'content' => $title_prompt
            ),
        );

        $content_messages = array(
            array(
                'role' => 'user',
                'content' => $content_prompt
            ),
        );

        $meta_desc_messages = array(
            array(
                'role' => 'user',
                'content' => $meta_desc_prompt
            ),
        );

        // Send a chat-based completion request to OpenAI
        $title = chat_completion_with_openai($title_messages, $model, $api_key);

        $content = chat_completion_with_openai($content_messages, $model, $api_key);

        $meta_desc = chat_completion_with_openai($meta_desc_messages, $model, $api_key);

        if ($title && $content) {
            // Create a new post with the extracted title and content
            $new_post = array(
                'post_title' => $title,
                'post_content' => wp_kses_post($content),
                'post_status'  => 'draft', // Set the status to 'draft' for a new page
                'post_type'    => 'page', // Set the post type to 'page' for a new page
            );

            $new_post_id = wp_insert_post($new_post);

            if ($new_post_id) {
                // Set the Yoast SEO meta description
                update_post_meta($new_post_id, '_yoast_wpseo_metadesc', $meta_desc);

                // Update post to sync from post meta to yoast indexable
                $new_post = array(
                    'ID' => $new_post_id
                );

                wp_update_post( $new_post );  
                              
                // Display a success message
                echo '<p>Page created with title: ' . esc_html($title) . '</p>';
                echo '<hr>';
                echo '<p>and content: ' . esc_html($content) . '</p>';
            } else {
                echo '<p>Error creating page for: ' . esc_html($title) . '</p>';
            }
        } else {
            echo '<p>Error generating page</p>';
        }
    }

    // Retrieve values from options or set default values
    $model = get_option('ai_content_generator_model', '');
    $api_key = get_option('ai_content_generator_api_key', '');
    $title_prompt = get_option('ai_content_generator_title_prompt', '');
    $content_prompt = get_option('ai_content_generator_content_prompt', '');
    $meta_desc_prompt = get_option('ai_content_generator_meta_desc_prompt', '');

    // Display the form
    ?>
<div class="wrap">
    <h2>Generate Page</h2>
    <form method="post" action="">
        <label for="model">Specify ChatGPT Model:</label>
        <input type="text" name="model" value="<?php echo esc_attr($model); ?>" required><br><br>

        <label for="api_key">Your API Key:</label>
        <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" required><br><br>

        <label for="title_prompt">Title Prompt:</label>
        <textarea name="title_prompt" rows="2" cols="50"
            style="width: 500px;"><?php echo esc_textarea($title_prompt); ?></textarea><br><br>

        <label for="content_prompt">Content Prompt:</label>
        <textarea name="content_prompt" rows="5" cols="50"
            style="width: 500px; height: 100px;"><?php echo esc_textarea($content_prompt); ?></textarea><br><br>

        <label for="content_prompt">Meta Description Prompt:</label>
        <textarea name="meta_desc_prompt" rows="5" cols="50"
            style="width: 500px; height: 100px;"><?php echo esc_textarea($meta_desc_prompt); ?></textarea><br><br>

        <input type="submit" name="generate_post" class="button-primary" value="Generate Page">
    </form>
</div>
<?php
}
?>