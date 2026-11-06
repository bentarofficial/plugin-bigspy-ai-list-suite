<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function bigspy_generator_integration_status() {
    return array('plugin_version'=>defined('BIGSPY_AI_VERSION') ? BIGSPY_AI_VERSION : '','theme_version'=>function_exists('bigspy_theme_integration_version') ? bigspy_theme_integration_version() : '','woocommerce'=>class_exists('WooCommerce'),'acf'=>function_exists('update_field'),'rank_math'=>defined('RANK_MATH_VERSION') || class_exists('RankMath\\Admin\\Admin'));
}
