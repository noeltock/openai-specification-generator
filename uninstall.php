<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin settings
delete_option('wp_openapi_generator_endpoints');

// Delete the generated OpenAPI Specification JSON file
$upload_dir = wp_upload_dir();
$oas_dir = $upload_dir['basedir'] . '/openapi-spec';
$oas_file = $oas_dir . '/openapi-spec.json';

if (file_exists($oas_file)) {
    unlink($oas_file);
}
