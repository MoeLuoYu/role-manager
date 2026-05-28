<?php
/**
 * Plugin Name: 角色管理
 * Plugin URI: https://github.com/MoeLuoYu/role-manager
 * Description: WordPress站点角色管理，可以管理站点所有现有的角色实例
 * Version: 1.0.0
 * Author: MoeLuoYu
 * Author URI: https://github.com/MoeLuoYu
 * License: GPL v2 or later
 * Text Domain: role-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ROLE_MANAGER_VERSION', '1.0.0');
define('ROLE_MANAGER_PATH', plugin_dir_path(__FILE__));
define('ROLE_MANAGER_URL', plugin_dir_url(__FILE__));

require_once ROLE_MANAGER_PATH . 'includes/class-logger.php';
require_once ROLE_MANAGER_PATH . 'includes/class-role-manager.php';
require_once ROLE_MANAGER_PATH . 'admin/class-admin.php';

register_activation_hook(__FILE__, 'role_manager_activate');
function role_manager_activate() {
    Role_Manager_Core::get_instance()->store_default_role_names();
}

add_action('plugins_loaded', 'role_manager_init');
function role_manager_init() {
    load_plugin_textdomain('role-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    Role_Manager_Core::get_instance();
    Role_Manager_Admin::get_instance();
}
