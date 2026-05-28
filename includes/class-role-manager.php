<?php
if (!defined('ABSPATH')) {
    exit;
}

class Role_Manager_Core {
    private static $instance = null;
    private $wp_default_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
    private $option_name = 'role_manager_custom_names';
    private $custom_roles_option = 'role_manager_custom_roles';
    private $restricted_caps_option = 'role_manager_restricted_caps';
    private $role_inheritance_option = 'role_manager_inheritance';

    private $default_restricted_caps = array(
        'manage_options', 'manage_network', 'manage_sites', 'activate_plugins',
        'edit_plugins', 'edit_themes', 'edit_files', 'update_plugins', 'delete_plugins',
        'install_plugins', 'update_themes', 'delete_themes', 'install_themes',
        'update_core', 'export', 'import', 'unfiltered_upload', 'unfiltered_html'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('editable_roles', array($this, 'filter_role_names'));
        add_action('init', array($this, 'apply_custom_role_names'));
    }

    public function is_default_role($role_name) {
        return in_array($role_name, $this->wp_default_roles);
    }

    public function get_all_roles() {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        return $wp_roles->roles;
    }

    public function store_default_role_names() {
        $default_names = array();
        foreach ($this->wp_default_roles as $role) {
            $role_obj = get_role($role);
            if ($role_obj) {
                $default_names[$role] = translate_user_role(ucfirst($role));
            }
        }
        update_option('role_manager_default_names', $default_names);
    }

    public function get_custom_role_name($role_name) {
        $custom_names = get_option($this->option_name, array());
        return isset($custom_names[$role_name]) ? $custom_names[$role_name] : null;
    }

    public function update_role_name($role_name, $new_name) {
        if (empty($new_name)) {
            return new WP_Error('empty_name', __('角色名称不能为空', 'role-manager'));
        }

        if (!get_role($role_name)) {
            return new WP_Error('role_not_found', __('角色不存在', 'role-manager'));
        }

        $custom_names = get_option($this->option_name, array());
        $custom_names[$role_name] = sanitize_text_field($new_name);
        update_option($this->option_name, $custom_names);
        return true;
    }

    public function reset_role_name($role_name) {
        if (!get_role($role_name)) {
            return new WP_Error('role_not_found', __('角色不存在', 'role-manager'));
        }

        $custom_names = get_option($this->option_name, array());
        if (isset($custom_names[$role_name])) {
            unset($custom_names[$role_name]);
            update_option($this->option_name, $custom_names);
        }
        return true;
    }

    public function create_role($role_name, $display_name, $capabilities = array(), $parent_role = '') {
        $role_name = sanitize_key($role_name);
        $display_name = sanitize_text_field($display_name);
        $parent_role = sanitize_key($parent_role);
        
        if (empty($role_name) || empty($display_name)) {
            return new WP_Error('empty_fields', __('角色标识和名称不能为空', 'role-manager'));
        }

        if (preg_match('/[^a-z0-9_]/', $role_name)) {
            return new WP_Error('invalid_role_id', __('角色标识只能使用小写字母、数字和下划线', 'role-manager'));
        }

        if (get_role($role_name)) {
            return new WP_Error('role_exists', __('该角色标识已存在', 'role-manager'));
        }

        if (!empty($parent_role)) {
            $validation = $this->validate_inheritance($role_name, $parent_role);
            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        $capabilities = $this->sanitize_capabilities($capabilities);

        if (!empty($parent_role)) {
            $parent_role_obj = get_role($parent_role);
            if ($parent_role_obj) {
                foreach ($parent_role_obj->capabilities as $cap => $grant) {
                    if ($grant) {
                        $capabilities[$cap] = true;
                    }
                }
            }
        }

        $result = add_role($role_name, $display_name, $capabilities);
        if ($result) {
            $custom_roles = get_option($this->custom_roles_option, array());
            $custom_roles[] = $role_name;
            update_option($this->custom_roles_option, $custom_roles);

            if (!empty($parent_role)) {
                $this->set_role_parent($role_name, $parent_role);
            }

            return true;
        }

        return new WP_Error('create_failed', __('创建角色失败', 'role-manager'));
    }

    public function delete_role($role_name) {
        if ($this->is_default_role($role_name)) {
            return new WP_Error('cannot_delete_default', __('无法删除WordPress默认角色', 'role-manager'));
        }

        $users = get_users(array('role' => $role_name));
        if (!empty($users)) {
            $default_role = get_option('default_role', 'subscriber');
            foreach ($users as $user) {
                $user->remove_role($role_name);
                $user->add_role($default_role);
            }
        }

        $this->cleanup_inheritance($role_name);
        $this->update_child_roles($role_name);

        remove_role($role_name);

        $custom_names = get_option($this->option_name, array());
        if (isset($custom_names[$role_name])) {
            unset($custom_names[$role_name]);
            update_option($this->option_name, $custom_names);
        }

        $custom_roles = get_option($this->custom_roles_option, array());
        if (($key = array_search($role_name, $custom_roles)) !== false) {
            unset($custom_roles[$key]);
            update_option($this->custom_roles_option, $custom_roles);
        }

        return true;
    }

    private function cleanup_inheritance($deleted_role) {
        $inheritance = get_option($this->role_inheritance_option, array());
        $modified = false;
        
        foreach ($inheritance as $child => $parent) {
            if ($parent === $deleted_role) {
                unset($inheritance[$child]);
                $modified = true;
            }
        }
        
        if ($modified) {
            update_option($this->role_inheritance_option, $inheritance);
        }
    }

    public function update_role_capabilities($role_name, $capabilities) {
        if ($this->is_default_role($role_name)) {
            return new WP_Error('cannot_edit_default_caps', __('无法修改WordPress默认角色的权限', 'role-manager'));
        }

        $role = get_role($role_name);
        if (!$role) {
            return new WP_Error('role_not_found', __('角色不存在', 'role-manager'));
        }

        $all_caps_data = $this->get_all_role_capabilities($role_name);
        $inherited_caps = $all_caps_data['inherited'];

        foreach ($inherited_caps as $cap) {
            $role->add_cap($cap);
        }

        $capabilities = $this->sanitize_capabilities($capabilities);

        foreach ($capabilities as $cap => $grant) {
            $cap = sanitize_key($cap);
            if (empty($cap)) {
                continue;
            }
            if ($grant) {
                $role->add_cap($cap);
            } else {
                if (!isset($inherited_caps[$cap])) {
                    $role->remove_cap($cap);
                }
            }
        }

        $this->update_child_roles($role_name);

        return true;
    }

    private function sanitize_capabilities($capabilities) {
        if (!is_array($capabilities)) {
            return array();
        }

        $sanitized = array();
        $all_caps = $this->get_all_capabilities();
        $restricted = $this->get_restricted_capabilities();

        foreach ($capabilities as $cap => $grant) {
            $cap = sanitize_key($cap);
            if (empty($cap)) {
                continue;
            }
            if (!in_array($cap, $all_caps) && !$this->is_valid_cap_format($cap)) {
                continue;
            }
            if (in_array($cap, $restricted)) {
                continue;
            }
            $sanitized[$cap] = (bool) $grant;
        }

        return $sanitized;
    }

    private function is_valid_cap_format($cap) {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $cap);
    }

    public function get_restricted_capabilities() {
        $restricted = get_option($this->restricted_caps_option, $this->default_restricted_caps);
        return is_array($restricted) ? $restricted : $this->default_restricted_caps;
    }

    public function update_restricted_capabilities($caps) {
        if (!is_array($caps)) {
            return new WP_Error('invalid_input', __('无效的输入', 'role-manager'));
        }
        $sanitized = array();
        foreach ($caps as $cap) {
            $cap = sanitize_key($cap);
            if (!empty($cap)) {
                $sanitized[] = $cap;
            }
        }
        update_option($this->restricted_caps_option, $sanitized);
        return true;
    }

    public function is_restricted_cap($cap) {
        return in_array($cap, $this->get_restricted_capabilities());
    }

    public function get_all_capabilities() {
        $capabilities = array();
        $roles = $this->get_all_roles();
        
        foreach ($roles as $role) {
            if (isset($role['capabilities'])) {
                foreach ($role['capabilities'] as $cap => $grant) {
                    $capabilities[$cap] = $cap;
                }
            }
        }

        ksort($capabilities);
        return array_keys($capabilities);
    }

    public function is_custom_role($role_name) {
        $custom_roles = get_option($this->custom_roles_option, array());
        return in_array($role_name, $custom_roles);
    }

    public function filter_role_names($roles) {
        $custom_names = get_option($this->option_name, array());
        
        foreach ($roles as $role_name => $role_data) {
            if (isset($custom_names[$role_name])) {
                $roles[$role_name]['name'] = $custom_names[$role_name];
            }
        }
        
        return $roles;
    }

    public function apply_custom_role_names() {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            return;
        }

        $custom_names = get_option($this->option_name, array());
        
        foreach ($custom_names as $role_name => $custom_name) {
            if (isset($wp_roles->roles[$role_name])) {
                $wp_roles->roles[$role_name]['name'] = $custom_name;
            }
        }
    }

    public function get_role_display_name($role_name) {
        $custom_name = $this->get_custom_role_name($role_name);
        if ($custom_name) {
            return $custom_name;
        }

        $roles = $this->get_all_roles();
        if (isset($roles[$role_name])) {
            return translate_user_role($roles[$role_name]['name']);
        }

        return $role_name;
    }

    public function get_capability_description($cap) {
        $descriptions = array(
            'manage_options' => __('管理站点选项', 'role-manager'),
            'manage_network' => __('管理网络', 'role-manager'),
            'manage_sites' => __('管理站点', 'role-manager'),
            'activate_plugins' => __('激活插件', 'role-manager'),
            'edit_plugins' => __('编辑插件', 'role-manager'),
            'edit_themes' => __('编辑主题', 'role-manager'),
            'edit_files' => __('编辑文件', 'role-manager'),
            'update_plugins' => __('更新插件', 'role-manager'),
            'delete_plugins' => __('删除插件', 'role-manager'),
            'install_plugins' => __('安装插件', 'role-manager'),
            'update_themes' => __('更新主题', 'role-manager'),
            'delete_themes' => __('删除主题', 'role-manager'),
            'install_themes' => __('安装主题', 'role-manager'),
            'update_core' => __('更新核心', 'role-manager'),
            'export' => __('导出内容', 'role-manager'),
            'import' => __('导入内容', 'role-manager'),
            'create_users' => __('创建用户', 'role-manager'),
            'delete_users' => __('删除用户', 'role-manager'),
            'promote_users' => __('提升用户', 'role-manager'),
            'remove_users' => __('移除用户', 'role-manager'),
            'unfiltered_upload' => __('未过滤上传', 'role-manager'),
            'unfiltered_html' => __('未过滤HTML', 'role-manager'),
            'read' => __('阅读', 'role-manager'),
            'edit_posts' => __('编辑文章', 'role-manager'),
            'edit_others_posts' => __('编辑他人文章', 'role-manager'),
            'edit_published_posts' => __('编辑已发布文章', 'role-manager'),
            'edit_private_posts' => __('编辑私密文章', 'role-manager'),
            'publish_posts' => __('发布文章', 'role-manager'),
            'delete_posts' => __('删除文章', 'role-manager'),
            'delete_others_posts' => __('删除他人文章', 'role-manager'),
            'delete_published_posts' => __('删除已发布文章', 'role-manager'),
            'delete_private_posts' => __('删除私密文章', 'role-manager'),
            'read_private_posts' => __('阅读私密文章', 'role-manager'),
            'edit_pages' => __('编辑页面', 'role-manager'),
            'edit_others_pages' => __('编辑他人页面', 'role-manager'),
            'edit_published_pages' => __('编辑已发布页面', 'role-manager'),
            'edit_private_pages' => __('编辑私密页面', 'role-manager'),
            'publish_pages' => __('发布页面', 'role-manager'),
            'delete_pages' => __('删除页面', 'role-manager'),
            'delete_others_pages' => __('删除他人页面', 'role-manager'),
            'delete_published_pages' => __('删除已发布页面', 'role-manager'),
            'delete_private_pages' => __('删除私密页面', 'role-manager'),
            'read_private_pages' => __('阅读私密页面', 'role-manager'),
            'upload_files' => __('上传文件', 'role-manager'),
            'moderate_comments' => __('审核评论', 'role-manager'),
            'manage_categories' => __('管理分类', 'role-manager'),
            'manage_links' => __('管理链接', 'role-manager'),
        );
        
        return isset($descriptions[$cap]) ? $descriptions[$cap] : '';
    }

    public function get_role_parent($role_name) {
        $inheritance = get_option($this->role_inheritance_option, array());
        return isset($inheritance[$role_name]) ? $inheritance[$role_name] : null;
    }

    public function set_role_parent($role_name, $parent_role) {
        $inheritance = get_option($this->role_inheritance_option, array());
        
        if (empty($parent_role)) {
            unset($inheritance[$role_name]);
        } else {
            $inheritance[$role_name] = sanitize_key($parent_role);
        }
        
        update_option($this->role_inheritance_option, $inheritance);
        return true;
    }

    public function get_inherited_capabilities($role_name) {
        $capabilities = array();
        $parent_role = $this->get_role_parent($role_name);
        
        while ($parent_role) {
            $parent_role_obj = get_role($parent_role);
            if ($parent_role_obj) {
                foreach ($parent_role_obj->capabilities as $cap => $grant) {
                    if ($grant) {
                        $capabilities[$cap] = $cap;
                    }
                }
            }
            $parent_role = $this->get_role_parent($parent_role);
        }
        
        return $capabilities;
    }

    public function get_all_role_capabilities($role_name) {
        $role = get_role($role_name);
        if (!$role) {
            return array();
        }

        $direct_caps = array();
        foreach ($role->capabilities as $cap => $grant) {
            if ($grant) {
                $direct_caps[$cap] = $cap;
            }
        }

        $inherited_caps = $this->get_inherited_capabilities($role_name);
        
        $all_caps = array_merge($direct_caps, $inherited_caps);
        ksort($all_caps);
        
        return array(
            'direct' => $direct_caps,
            'inherited' => $inherited_caps,
            'all' => $all_caps
        );
    }

    public function update_child_roles($role_name, $depth = 0, $max_depth = 50) {
        if ($depth > $max_depth) {
            error_log('Role inheritance depth exceeded for role: ' . $role_name);
            return;
        }

        $inheritance = get_option($this->role_inheritance_option, array());
        $children = array();
        
        foreach ($inheritance as $child => $parent) {
            if ($parent === $role_name) {
                $children[] = $child;
            }
        }
        
        foreach ($children as $child_role) {
            if (!get_role($child_role)) {
                unset($inheritance[$child_role]);
                update_option($this->role_inheritance_option, $inheritance);
                continue;
            }
            
            $this->apply_inherited_capabilities($child_role);
            $this->update_child_roles($child_role, $depth + 1, $max_depth);
        }
    }

    public function apply_inherited_capabilities($role_name) {
        $role = get_role($role_name);
        if (!$role) {
            return false;
        }

        $parent_role = $this->get_role_parent($role_name);
        if (!$parent_role) {
            return false;
        }

        $all_caps_data = $this->get_all_role_capabilities($role_name);
        $direct_caps = $all_caps_data['direct'];
        
        foreach ($role->capabilities as $cap => $grant) {
            if (!isset($direct_caps[$cap]) && $grant) {
                $role->remove_cap($cap);
            }
        }
        
        return true;
    }

    public function validate_inheritance($role_name, $parent_role) {
        if (empty($parent_role)) {
            return true;
        }
        
        if ($role_name === $parent_role) {
            return new WP_Error('self_inheritance', __('角色不能继承自身', 'role-manager'));
        }
        
        if (!get_role($parent_role)) {
            return new WP_Error('parent_not_exist', __('父角色不存在', 'role-manager'));
        }

        if (!$this->get_role($role_name)) {
            return new WP_Error('role_not_exist', __('角色不存在', 'role-manager'));
        }
        
        $current = $this->get_role_parent($parent_role);
        $visited = array($role_name, $parent_role);
        
        while ($current) {
            if (in_array($current, $visited)) {
                return new WP_Error('circular_inheritance', __('检测到循环继承', 'role-manager'));
            }
            $visited[] = $current;
            $current = $this->get_role_parent($current);
        }

        $inheritance = get_option($this->role_inheritance_option, array());
        if (isset($inheritance[$role_name])) {
            $current_parent = $inheritance[$role_name];
            if ($this->is_ancestor($current_parent, $parent_role)) {
                return new WP_Error('reverse_inheritance', __('不能将父角色设为子角色', 'role-manager'));
            }
        }
        
        return true;
    }

    private function is_ancestor($potential_ancestor, $role) {
        $current = $this->get_role_parent($role);
        $visited = array($role);
        
        while ($current) {
            if ($current === $potential_ancestor) {
                return true;
            }
            if (in_array($current, $visited)) {
                return false;
            }
            $visited[] = $current;
            $current = $this->get_role_parent($current);
        }
        
        return false;
    }
}
