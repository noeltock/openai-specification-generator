<?php
/**
 * Plugin Name: OpenAPI Specification Generator
 * Description: A WordPress plugin to generate OpenAPI Specification 3.1 for REST API endpoints.
 * Version: 1.0
 * Author: Noel Tock
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'wp_openapi_generator_activate');
register_deactivation_hook(__FILE__, 'wp_openapi_generator_deactivate');

function wp_openapi_generator_activate() {
    $upload_dir = wp_upload_dir();
    $oas_dir = $upload_dir['basedir'] . '/openapi-spec';

    if (!file_exists($oas_dir)) {
        wp_mkdir_p($oas_dir);
    }

    // Create an empty .htaccess file to allow public access
    if (!file_exists($oas_dir . '/.htaccess')) {
        $htaccess_content = "Allow from all\n";
        file_put_contents($oas_dir . '/.htaccess', $htaccess_content);
    }

    // Create an empty index.php file to prevent directory listing
    if (!file_exists($oas_dir . '/index.php')) {
        file_put_contents($oas_dir . '/index.php', "<?php\n// Silence is golden.\n");
    }
}

function wp_openapi_generator_deactivate() {
    // No actions on deactivate for now, files are deleted in uninstall.php
}

// Discover and list all REST API endpoints
function wp_openapi_generator_discover_endpoints() {
    $endpoints = array();

    // Get all the registered REST routes
    $routes = rest_get_server()->get_routes();

    foreach ($routes as $route => $route_data) {
        foreach ($route_data as $handler) {
            if (isset($handler['callback']) && is_array($handler['callback'])) {
                // Filter out dynamic endpoints
                if (strpos($route, '(?P<') === false) {
                    // Check if the endpoint already exists in the array
                    $route_exists = false;
                    foreach ($endpoints as $existing_endpoint) {
                        if ($existing_endpoint['route'] === $route) {
                            $route_exists = true;
                            break;
                        }
                    }

                    // Add the endpoint if it doesn't exist
                    if (!$route_exists) {
                        $endpoints[] = array(
                            'route' => $route,
                            'methods' => $handler['methods'],
                        );
                    }
                }
            }
        }
    }

    return $endpoints;
}

function wp_openapi_generator_register_settings() {
    register_setting('wp_openapi_generator_options_group', 'wp_openapi_generator_endpoints', array('sanitize_callback' => 'wp_openapi_generator_sanitize_endpoints'));
    register_setting('wp_openapi_generator_options_group', 'wp_openapi_generator_filter_words', 'sanitize_textarea_field');
}

add_action('admin_init', 'wp_openapi_generator_register_settings');

function wp_openapi_generator_sanitize_endpoints($endpoints) {
    foreach ($endpoints as $route => $endpoint) {
        $endpoints[$route]['include'] = isset($endpoint['include']) ? 1 : 0;
        $endpoints[$route]['summary'] = sanitize_text_field($endpoint['summary']);
        $endpoints[$route]['description'] = wp_kses_post($endpoint['description']);
    }
    return $endpoints;
}

// Create the plugin settings page
function wp_openapi_generator_settings_page() {
    // Discover all REST API endpoints
    $endpoints = wp_openapi_generator_discover_endpoints();

    // Get the OAS URL from the saved JSON file
    $upload_dir = wp_upload_dir();
    $oas_url = $upload_dir['baseurl'] . '/openapi-spec/openapi-spec.json';

    // Get the filter words
    $filter_words = get_option('wp_openapi_generator_filter_words', "oembed\nblocks");

    // Filter the endpoints based on filter words
    // Filter the endpoints based on filter words
    $filter_words_array = preg_split('/\R/', $filter_words, -1, PREG_SPLIT_NO_EMPTY);
    $filtered_endpoints = array_filter($endpoints, function($endpoint) use ($filter_words_array) {
        foreach ($filter_words_array as $word) {
            if (stripos($endpoint['route'], $word) !== false) {
                return false;
            }
        }
        return true;
    });


    // Create the plugin settings page
    ?>
<div class="wrap">
    <h1><?php esc_html_e('OpenAPI Specification Generator', 'wp-openapi-generator'); ?></h1>
    <p>
        <?php esc_html_e('The current OpenAPI Specification JSON file is available at:', 'wp-openapi-generator'); ?>
        <a href="<?php echo esc_url($oas_url); ?>" target="_blank"><?php echo esc_html($oas_url); ?></a>
    </p>

    <h2 class="nav-tab-wrapper">
        <a href="#endpoints" class="nav-tab nav-tab-active"><?php esc_html_e('Endpoints', 'wp-openapi-generator'); ?></a>
        <a href="#filters" class="nav-tab"><?php esc_html_e('Filters', 'wp-openapi-generator'); ?></a>
    </h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wp-openapi-generator')); ?>">

        <?php settings_fields('wp_openapi_generator_options_group'); ?>
        <?php do_settings_sections('wp_openapi_generator_options_group'); ?>

        <div id="endpoints" class="wp-openapi-generator-tab-content" style="display: block;">
            <?php // Display the endpoints list and settings ?>

            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:100px"><?php esc_html_e('Include', 'wp-openapi-generator'); ?></th>
                        <th><?php esc_html_e('Endpoint', 'wp-openapi-generator'); ?></th>
                        <th><?php esc_html_e('Summary', 'wp-openapi-generator'); ?></th>
                        <th><?php esc_html_e('Description', 'wp-openapi-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_endpoints as $endpoint): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="wp_openapi_generator_endpoints[<?php echo esc_attr($endpoint['route']); ?>][include]" value="1" <?php
                        $endpoints_settings = get_option('wp_openapi_generator_endpoints');
                        echo checked(isset($endpoints_settings[$endpoint['route']]['include']) ? $endpoints_settings[$endpoint['route']]['include'] : false, true);
                        ?>>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(rest_url($endpoint['route'])); ?>" target="_blank"><?php echo esc_html($endpoint['route']); ?></a>
                        </td>
                        <td>
                            <textarea style="width:90%" name="wp_openapi_generator_endpoints[<?php echo esc_attr($endpoint['route']); ?>][summary]"> <?php echo esc_textarea(isset($endpoints_settings[$endpoint['route']]['summary']) ? $endpoints_settings[$endpoint['route']]['summary'] : ''); ?></textarea>
                        </td>
                        <td>
                            <textarea style="width:90%" name="wp_openapi_generator_endpoints[<?php echo esc_attr($endpoint['route']); ?>][description]"><?php echo esc_textarea(isset($endpoints_settings[$endpoint['route']]['description']) ? $endpoints_settings[$endpoint['route']]['description'] : ''); ?></textarea>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="filters" class="wp-openapi-generator-tab-content" style="display: none;">
            <?php // Display the filters textarea ?>

            <h2><?php esc_html_e('Filter Endpoints', 'wp-openapi-generator'); ?></h2>
            <p><?php esc_html_e('Enter one filter word per line. Endpoints containing any of these words will be excluded from the list.', 'wp-openapi-generator'); ?></p>
            <textarea name="wp_openapi_generator_filter_words" rows="10" cols="50"><?php echo esc_textarea($filter_words); ?></textarea>
        </div>

        <?php wp_nonce_field('wp_openapi_generator_generate_oas', 'wp_openapi_generator_generate_oas_nonce'); ?>
        <?php submit_button(__('Save Changes', 'wp-openapi-generator'), 'primary', 'submit', false); ?>

        <input type="submit" name="generate_file" id="generate_file" class="button button-primary" value="<?php esc_attr_e('Generate File', 'wp-openapi-generator'); ?>">


        <?php

        if (isset($_POST['submit']) && check_admin_referer('wp_openapi_generator_generate_oas', 'wp_openapi_generator_generate_oas_nonce')) {
            // Your existing code for saving changes

        } elseif (isset($_POST['generate_file']) && check_admin_referer('wp_openapi_generator_generate_oas', 'wp_openapi_generator_generate_oas_nonce')) {
            echo 'Generating file...'; // Add this line
            $oas_json = wp_openapi_generator_generate_oas();
            $oas_url = wp_openapi_generator_save_oas_json($oas_json);
            // Save the OAS URL in the database for easy access.
            update_option('wp_openapi_generator_oas_url', $oas_url);
        }

?>

</form>

    <script>
        (function($) {
            $(document).ready(function() {
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    $('.wp-openapi-generator-tab-content').hide();
                    $($(this).attr('href')).show();
                });
            });
        })(jQuery);
    </script>
</div>
<?php
}

function wp_openapi_generator_generate_oas() {
    // Discover all REST API endpoints
    $endpoints = wp_openapi_generator_discover_endpoints();

    // Initialize the OpenAPI Specification 3.0 JSON structure
    $oas = [
        'openapi' => '3.0.1',
        'info' => [
            'title' => get_bloginfo('name') . ' API',
            'description' => 'Your API description goes here.',
            'version' => '1.0.0',
        ],
        'servers' => [
            [
                'url' => get_rest_url(),
            ],
        ],
        'paths' => [],
    ];

    // Populate the 'paths' field of the OAS JSON structure
    foreach ($endpoints as $endpoint) {
        $route = $endpoint['route'];
        $methods = $endpoint['methods'];

        $settings = get_option("wp_openapi_generator_endpoints");

        if (isset($settings[$route]['include']) && $settings[$route]['include']) {
            $summary = isset($settings[$route]['summary']) ? $settings[$route]['summary'] : '';

            $description = isset($settings[$route]['description']) ? $settings[$route]['description'] : '';

            if (!isset($oas['paths'][$route])) {
                $oas['paths'][$route] = [];
            }

            foreach ($methods as $method) {
                $oas['paths'][$route][strtolower($method)] = [
                    'summary' => $summary,
                    'description' => $description,
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/' . str_replace('/', '_', $route) . '_' . strtolower($method) . '_response',
                                    ],
                                ],
                            ],
                        ],
                        'default' => [
                            'description' => 'An error occurred',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/error_response',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
                $oas['paths'][$route] = array_filter($oas['paths'][$route]); // Add this line
            }
        }
    }

    // Add components to the OAS JSON structure
    $oas['components'] = [
        'schemas' => [
            'error_response' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'A description of the error that occurred.',
                    ],
                ],
            ],
        ],
    ];

    // return json_encode($oas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $oas_json = json_encode($oas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return $oas_json;
}

// Save the generated OAS JSON in a publicly accessible folder
function wp_openapi_generator_save_oas_json($oas_json) {
    $upload_dir = wp_upload_dir();
    $oas_dir = $upload_dir['basedir'] . '/openapi-spec';
    $oas_file = $oas_dir . '/openapi-spec.json';

    //Check permissions
    if (!file_exists($oas_dir)) {
        wp_mkdir_p($oas_dir);
        @chmod($oas_dir, 0755); // Set folder permissions
    }    

    // Save the OAS JSON to the file
    $result = file_put_contents($oas_file, $oas_json);
if (false === $result) {
    error_log('Error writing file: ' . $oas_file);
} else {
    error_log('File successfully written: ' . $oas_file);
}

    // Return the public URL to access the OAS JSON
    return $upload_dir['baseurl'] . '/openapi-spec/openapi-spec.json';
}

// Add the plugin settings page to the WordPress admin menu
add_action('admin_menu', function () {
    add_options_page(
        'OpenAI Specification Generator',
        'OpenAI Specification Generator',
        'manage_options',
        'wp-openapi-generator',
        'wp_openapi_generator_settings_page'
    );
});

// Register settings fields
add_action('admin_init', function () {
    // Register the settings
    register_setting('wp_openapi_generator_settings', 'wp_openapi_generator_endpoints');

    // Add a section for endpoint settings
    add_settings_section(
        'wp_openapi_generator_endpoint_section',
        __('Endpoints', 'wp-openapi-generator'),
        function () {
            esc_html_e('Provide a summary and description for each endpoint.', 'wp-openapi-generator');
        },
        'wp_openapi_generator_settings'
    );
});


// Add "Generate" button functionality
add_action('admin_post_generate_oas', function () {
    // Check if the user has the required capability to generate the OAS JSON
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-openapi-generator'));
    }

    // Generate the OAS JSON
    $oas_json = wp_openapi_generator_generate_oas();

    // Save the OAS JSON to the publicly accessible folder
    $oas_url = wp_openapi_generator_save_oas_json($oas_json);

    // Redirect back to the plugin settings page with a success message and the OAS URL
    $redirect_url = add_query_arg([
        'page' => 'wp-openapi-generator',
        'oas_generated' => '1',
        'oas_url' => urlencode($oas_url),
    ], admin_url('options-general.php'));

    wp_safe_redirect($redirect_url);
    exit;
});
