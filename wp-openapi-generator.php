<?php
/**
 * Plugin Name: WP OpenAPI Generator
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

// Create the plugin settings page
function wp_openapi_generator_settings_page() {
    // Discover all REST API endpoints
    $endpoints = wp_openapi_generator_discover_endpoints();

    // Get the OAS URL from the saved JSON file
    $upload_dir = wp_upload_dir();
    $oas_url = $upload_dir['baseurl'] . '/openapi-spec/openapi-spec.json';

    // Create the plugin settings page
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('WP OpenAPI Generator', 'wp-openapi-generator'); ?></h1>
        <p>
            <?php esc_html_e('The current OpenAPI Specification JSON file is available at:', 'wp-openapi-generator'); ?>
            <a href="<?php echo esc_url($oas_url); ?>" target="_blank"><?php echo esc_html($oas_url); ?></a>
        </p>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_openapi_generator_settings');
            do_settings_sections('wp_openapi_generator_settings');
            ?>

            <table class="form-table">
                <?php
                foreach ($endpoints as $index => $endpoint) {
                    $route = esc_html($endpoint['route']);
                    $endpoint_url = get_rest_url() . ltrim($route, '/');
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="wp_openapi_generator_endpoints_<?php echo $index; ?>_route">
                                <a href="<?php echo esc_url($endpoint_url); ?>" target="_blank"><?php echo $route; ?></a>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox"
                                   id="wp_openapi_generator_endpoints_<?php echo $index; ?>_include"
                                   name="wp_openapi_generator_endpoints[<?php echo $index; ?>][include]"
                                   value="1"
                                   <?php checked(get_option("wp_openapi_generator_endpoints[{$index}][include]"), 1); ?>>
                            <?php esc_html_e('Include in the specification', 'wp-openapi-generator'); ?>
                            <br><br>
                            <input type="text"
                                   id="wp_openapi_generator_endpoints_<?php echo $index; ?>_summary"
                                   name="wp_openapi_generator_endpoints[<?php echo $index; ?>][summary]"
                                   value="<?php echo esc_attr(get_option("wp_openapi_generator_endpoints[{$index}][summary]")); ?>"
                                   placeholder="<?php esc_attr_e('Summary', 'wp-openapi-generator'); ?>"
                                   class="regular-text">
                            <br>
                            <textarea id="wp_openapi_generator_endpoints_<?php echo $index; ?>_description"
                                      name="wp_openapi_generator_endpoints[<?php echo $index; ?>][description]"
                                      rows="3"
                                      placeholder="<?php esc_attr_e('Description', 'wp-openapi-generator'); ?>"
                                      class="large-text"><?php echo esc_textarea(get_option("wp_openapi_generator_endpoints[{$index}][description]")); ?></textarea>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Generate the OpenAPI Specification (OAS) 3.1 JSON
function wp_openapi_generator_generate_oas() {
    // Discover all REST API endpoints
    $endpoints = wp_openapi_generator_discover_endpoints();

    // Initialize the OpenAPI Specification 3.1 JSON structure
    $oas = [
        'openapi' => '3.1.0',
        'info' => [
            'title' => get_bloginfo('name') . ' API',
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
    foreach ($endpoints as $index => $endpoint) {
        $route = $endpoint['route'];
        $methods = explode(', ', $endpoint['methods']);

        $summary = get_option("wp_openapi_generator_endpoints[{$index}][summary]");
        $description = get_option("wp_openapi_generator_endpoints[{$index}][description]");

        if (!isset($oas['paths'][$route])) {
            $oas['paths'][$route] = [];
        }

        foreach ($methods as $method) {
            $oas['paths'][$route][strtolower($method)] = [
                'summary' => $summary,
                'description' => $description,
                'parameters' => [],
                'responses' => [
                    'default' => [
                        'description' => 'A generic response',
                    ],
                ],
            ];
        }
    }

    return json_encode($oas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


// Save the generated OAS JSON in a publicly accessible folder
function wp_openapi_generator_save_oas_json($oas_json) {
    $upload_dir = wp_upload_dir();
    $oas_dir = $upload_dir['basedir'] . '/openapi-spec';
    $oas_file = $oas_dir . '/openapi-spec.json';

    // Save the OAS JSON to the file
    file_put_contents($oas_file, $oas_json);

    // Return the public URL to access the OAS JSON
    return $upload_dir['baseurl'] . '/openapi-spec/openapi-spec.json';
}


// Add the plugin settings page to the WordPress admin menu
add_action('admin_menu', function () {
    add_options_page(
        'WP OpenAPI Generator',
        'WP OpenAPI Generator',
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
