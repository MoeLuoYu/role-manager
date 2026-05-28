<?php
if (!defined('ABSPATH')) {
    exit;
}

$restricted_caps = $this->core->get_restricted_capabilities();
$all_caps = $this->core->get_all_capabilities();
?>

<div class="wrap role-manager-wrap">
    <h1><?php _e('设置', 'role-manager'); ?></h1>

    <div class="role-manager-settings-container">
        <div class="postbox">
            <h2 class="hndle"><?php _e('受限权限', 'role-manager'); ?></h2>
            <div class="inside">
                <p class="description">
                    <?php _e('以下权限将被限制分配给任何角色。这些权限通常只应授予管理员。', 'role-manager'); ?>
                </p>

                <form id="restricted-caps-form">
                    <div class="caps-search">
                        <input type="text" id="restricted-search-input" placeholder="<?php esc_attr_e('搜索权限...', 'role-manager'); ?>">
                    </div>
                    
                    <div class="restricted-caps-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="column-cb check-column">
                                        <input type="checkbox" id="select-all-restricted">
                                    </th>
                                    <th><?php _e('权限', 'role-manager'); ?></th>
                                    <th><?php _e('说明', 'role-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="restricted-tbody">
                                <?php foreach ($all_caps as $cap) : ?>
                                <tr class="restricted-row" data-cap="<?php echo esc_attr($cap); ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="restricted_caps[]" 
                                               value="<?php echo esc_attr($cap); ?>" 
                                               class="restricted-checkbox"
                                               <?php checked(in_array($cap, $restricted_caps)); ?>>
                                    </th>
                                    <td>
                                        <code><?php echo esc_html($cap); ?></code>
                                    </td>
                                    <td>
                                        <?php echo esc_html($this->core->get_capability_description($cap)); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('保存设置', 'role-manager'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><?php _e('关于受限权限', 'role-manager'); ?></h2>
            <div class="inside">
                <p>
                    <?php _e('受限权限功能可以防止非管理员用户通过本插件为角色分配危险权限。', 'role-manager'); ?>
                </p>
                <p>
                    <?php _e('默认受限的权限包括：', 'role-manager'); ?>
                </p>
                <ul class="restricted-default-list">
                    <li><code>manage_options</code> - <?php _e('管理站点选项', 'role-manager'); ?></li>
                    <li><code>manage_network</code> - <?php _e('管理网络', 'role-manager'); ?></li>
                    <li><code>activate_plugins</code> - <?php _e('激活插件', 'role-manager'); ?></li>
                    <li><code>edit_plugins</code> - <?php _e('编辑插件', 'role-manager'); ?></li>
                    <li><code>edit_themes</code> - <?php _e('编辑主题', 'role-manager'); ?></li>
                    <li><code>install_plugins</code> - <?php _e('安装插件', 'role-manager'); ?></li>
                    <li><code>update_core</code> - <?php _e('更新核心', 'role-manager'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
