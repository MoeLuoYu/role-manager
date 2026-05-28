<?php
if (!defined('ABSPATH')) {
    exit;
}

class Role_Manager_Admin {
    private static $instance = null;
    private $core;
    private $logger;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core = Role_Manager_Core::get_instance();
        $this->logger = Role_Manager_Logger::get_instance();
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_role_manager_update_name', array($this, 'ajax_update_name'));
        add_action('wp_ajax_role_manager_reset_name', array($this, 'ajax_reset_name'));
        add_action('wp_ajax_role_manager_create_role', array($this, 'ajax_create_role'));
        add_action('wp_ajax_role_manager_delete_role', array($this, 'ajax_delete_role'));
        add_action('wp_ajax_role_manager_update_caps', array($this, 'ajax_update_capabilities'));
        add_action('wp_ajax_role_manager_get_role_caps', array($this, 'ajax_get_role_capabilities'));
        add_action('wp_ajax_role_manager_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_role_manager_update_restricted', array($this, 'ajax_update_restricted'));
    }

    public function add_menu() {
        add_menu_page(
            __('角色管理', 'role-manager'),
            __('角色', 'role-manager'),
            'manage_options',
            'role-manager',
            array($this, 'render_page'),
            'dashicons-admin-users',
            71
        );

        add_submenu_page(
            'role-manager',
            __('角色管理', 'role-manager'),
            __('角色管理', 'role-manager'),
            'manage_options',
            'role-manager',
            array($this, 'render_page')
        );

        add_submenu_page(
            'role-manager',
            __('操作日志', 'role-manager'),
            __('操作日志', 'role-manager'),
            'manage_options',
            'role-manager-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'role-manager',
            __('设置', 'role-manager'),
            __('设置', 'role-manager'),
            'manage_options',
            'role-manager-settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'role-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'role-manager-admin',
            ROLE_MANAGER_URL . 'assets/css/admin.css',
            array(),
            ROLE_MANAGER_VERSION
        );

        wp_enqueue_script(
            'role-manager-admin',
            ROLE_MANAGER_URL . 'assets/js/admin.js',
            array('jquery'),
            ROLE_MANAGER_VERSION,
            true
        );

        wp_localize_script('role-manager-admin', 'roleManager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('role_manager_nonce'),
            'i18n' => array(
                'confirm_delete' => __('确定要删除此角色吗？此操作不可恢复。', 'role-manager'),
                'confirm_reset' => __('确定要重置此角色的名称吗？', 'role-manager'),
                'confirm_clear_logs' => __('确定要清空所有日志吗？', 'role-manager'),
                'saving' => __('保存中...', 'role-manager'),
                'saved' => __('已保存', 'role-manager'),
                'save' => __('保存', 'role-manager'),
                'save_caps' => __('保存权限', 'role-manager'),
                'save_settings' => __('保存设置', 'role-manager'),
                'create_role' => __('创建角色', 'role-manager'),
                'confirm_delete_btn' => __('确认删除', 'role-manager'),
                'error' => __('操作失败', 'role-manager'),
                'enter_role_confirm' => __('请输入角色标识以确认删除：', 'role-manager'),
                'confirm_not_match' => __('输入的角色标识不匹配', 'role-manager'),
                'cannot_edit_default' => __('无法修改WordPress默认角色的权限', 'role-manager'),
                'fill_required' => __('请填写角色标识和显示名称', 'role-manager'),
                'inherited' => __('继承', 'role-manager'),
                'restricted' => __('受限', 'role-manager'),
                'inherits_from' => __('继承自: %s', 'role-manager'),
            )
        ));
    }

    public function render_page() {
        include ROLE_MANAGER_PATH . 'admin/views/main-page.php';
    }

    public function render_logs_page() {
        include ROLE_MANAGER_PATH . 'admin/views/logs-page.php';
    }

    public function render_settings_page() {
        include ROLE_MANAGER_PATH . 'admin/views/settings-page.php';
    }

    public function ajax_update_name() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_name = isset($_POST['role_name']) ? sanitize_key($_POST['role_name']) : '';
        $new_name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';

        if (empty($role_name)) {
            wp_send_json_error(array('message' => __('角色标识无效', 'role-manager')));
        }

        $old_name = $this->core->get_role_display_name($role_name);
        $result = $this->core->update_role_name($role_name, $new_name);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->logger->log('rename_role', array(
            'role_id' => $role_name,
            'old_name' => $old_name,
            'new_name' => $new_name
        ));

        wp_send_json_success(array('message' => __('角色名称已更新', 'role-manager')));
    }

    public function ajax_reset_name() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_name = isset($_POST['role_name']) ? sanitize_key($_POST['role_name']) : '';

        if (empty($role_name)) {
            wp_send_json_error(array('message' => __('角色标识无效', 'role-manager')));
        }

        $this->core->reset_role_name($role_name);

        $this->logger->log('reset_name', array(
            'role_id' => $role_name
        ));

        wp_send_json_success(array('message' => __('角色名称已重置', 'role-manager')));
    }

    public function ajax_create_role() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_id = isset($_POST['role_id']) ? sanitize_key($_POST['role_id']) : '';
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        $parent_role = isset($_POST['parent_role']) ? sanitize_key($_POST['parent_role']) : '';
        $capabilities = isset($_POST['capabilities']) ? $_POST['capabilities'] : array();

        if (empty($role_id) || empty($role_name)) {
            wp_send_json_error(array('message' => __('角色标识和名称不能为空', 'role-manager')));
        }

        if (is_string($capabilities)) {
            $decoded = json_decode(stripslashes($capabilities), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => __('无效的JSON数据', 'role-manager')));
            }
            $capabilities = $decoded;
        }

        if (!is_array($capabilities)) {
            $capabilities = array();
        }

        if (count($capabilities) > 500) {
            wp_send_json_error(array('message' => __('权限数量超过限制', 'role-manager')));
        }

        $result = $this->core->create_role($role_id, $role_name, $capabilities, $parent_role);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->logger->log('create_role', array(
            'role_id' => $role_id,
            'role_name' => $role_name,
            'parent_role' => $parent_role,
            'capabilities_count' => count($capabilities)
        ));

        wp_send_json_success(array('message' => __('角色创建成功', 'role-manager')));
    }

    public function ajax_delete_role() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_name = isset($_POST['role_name']) ? sanitize_key($_POST['role_name']) : '';
        $confirm = isset($_POST['confirm']) ? sanitize_key($_POST['confirm']) : '';

        if (empty($role_name)) {
            wp_send_json_error(array('message' => __('角色标识无效', 'role-manager')));
        }

        if ($confirm !== $role_name) {
            wp_send_json_error(array('message' => __('确认输入不匹配，删除操作已取消', 'role-manager')));
        }

        $role_display_name = $this->core->get_role_display_name($role_name);
        $result = $this->core->delete_role($role_name);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->logger->log('delete_role', array(
            'role_id' => $role_name,
            'role_name' => $role_display_name
        ));

        wp_send_json_success(array('message' => __('角色已删除', 'role-manager')));
    }

    public function ajax_update_capabilities() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_name = isset($_POST['role_name']) ? sanitize_key($_POST['role_name']) : '';
        $capabilities = isset($_POST['capabilities']) ? $_POST['capabilities'] : array();

        if (empty($role_name)) {
            wp_send_json_error(array('message' => __('角色标识无效', 'role-manager')));
        }

        if (is_string($capabilities)) {
            $decoded = json_decode(stripslashes($capabilities), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => __('无效的JSON数据', 'role-manager')));
            }
            $capabilities = $decoded;
        }

        if (!is_array($capabilities)) {
            $capabilities = array();
        }

        if (count($capabilities) > 500) {
            wp_send_json_error(array('message' => __('权限数量超过限制', 'role-manager')));
        }

        $result = $this->core->update_role_capabilities($role_name, $capabilities);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->logger->log('update_caps', array(
            'role_id' => $role_name,
            'capabilities_count' => count($capabilities)
        ));

        wp_send_json_success(array('message' => __('权限已更新', 'role-manager')));
    }

    public function ajax_get_role_capabilities() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $role_name = isset($_POST['role_name']) ? sanitize_key($_POST['role_name']) : '';
        $roles = $this->core->get_all_roles();

        if (!isset($roles[$role_name])) {
            wp_send_json_error(array('message' => __('角色不存在', 'role-manager')));
        }

        $all_caps_data = $this->core->get_all_role_capabilities($role_name);

        wp_send_json_success(array(
            'capabilities' => $all_caps_data['direct'],
            'inherited' => $all_caps_data['inherited'],
            'parent_role' => $this->core->get_role_parent($role_name)
        ));
    }

    public function ajax_clear_logs() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $this->logger->clear_logs();
        wp_send_json_success(array('message' => __('日志已清空', 'role-manager')));
    }

    public function ajax_update_restricted() {
        check_ajax_referer('role_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'role-manager')));
        }

        $caps = isset($_POST['restricted_caps']) ? $_POST['restricted_caps'] : array();

        if (is_string($caps)) {
            $decoded = json_decode(stripslashes($caps), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => __('无效的JSON数据', 'role-manager')));
            }
            $caps = $decoded;
        }

        if (!is_array($caps)) {
            $caps = array();
        }

        if (count($caps) > 500) {
            wp_send_json_error(array('message' => __('权限数量超过限制', 'role-manager')));
        }

        $result = $this->core->update_restricted_capabilities($caps);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $this->logger->log('update_restricted', array(
            'count' => count($caps)
        ));

        wp_send_json_success(array('message' => __('受限权限已更新', 'role-manager')));
    }
}
