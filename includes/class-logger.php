<?php
if (!defined('ABSPATH')) {
    exit;
}

class Role_Manager_Logger {
    private static $instance = null;
    private $log_option = 'role_manager_logs';
    private $max_logs = 100;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function log($action, $details = array()) {
        $logs = get_option($this->log_option, array());
        
        $current_user = wp_get_current_user();
        
        $log_entry = array(
            'id' => uniqid(),
            'time' => current_time('mysql'),
            'timestamp' => time(),
            'user_id' => $current_user->ID,
            'user_name' => $current_user->user_login,
            'user_ip' => $this->get_user_ip(),
            'action' => sanitize_key($action),
            'details' => $this->sanitize_details($details)
        );

        array_unshift($logs, $log_entry);

        if (count($logs) > $this->max_logs) {
            $logs = array_slice($logs, 0, $this->max_logs);
        }

        update_option($this->log_option, $logs);
    }

    private function sanitize_details($details) {
        if (!is_array($details)) {
            return sanitize_text_field($details);
        }
        
        $sanitized = array();
        foreach ($details as $key => $value) {
            $key = sanitize_key($key);
            $sanitized[$key] = is_array($value) 
                ? $this->sanitize_details($value) 
                : sanitize_text_field($value);
        }
        return $sanitized;
    }

    private function get_user_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return $ip;
    }

    public function get_logs($limit = 50) {
        $limit = max(1, min(intval($limit), 1000));
        $logs = get_option($this->log_option, array());
        return array_slice($logs, 0, $limit);
    }

    public function clear_logs() {
        delete_option($this->log_option);
    }

    public function get_action_label($action) {
        $labels = array(
            'create_role' => __('创建角色', 'role-manager'),
            'delete_role' => __('删除角色', 'role-manager'),
            'rename_role' => __('重命名角色', 'role-manager'),
            'reset_name' => __('重置角色名称', 'role-manager'),
            'update_caps' => __('更新权限', 'role-manager'),
        );
        return isset($labels[$action]) ? $labels[$action] : $action;
    }
}
