<?php
if (!defined('ABSPATH')) {
    exit;
}

$roles = $this->core->get_all_roles();
$all_capabilities = $this->core->get_all_capabilities();
$custom_names = get_option('role_manager_custom_names', array());
$custom_roles = get_option('role_manager_custom_roles', array());
$restricted_caps = $this->core->get_restricted_capabilities();
$inheritance = get_option('role_manager_inheritance', array());
?>

<div class="wrap role-manager-wrap">
    <h1><?php _e('角色管理', 'role-manager'); ?></h1>

    <div class="role-manager-notices">
        <?php settings_errors('role_manager'); ?>
    </div>

    <div class="role-manager-container">
        <div class="role-manager-main">
            <h2><?php _e('现有角色', 'role-manager'); ?></h2>
            <p class="description">
                <?php _e('管理WordPress站点所有角色。WordPress默认角色只能修改显示名称，无法编辑权限或删除。', 'role-manager'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped role-manager-table">
                <thead>
                    <tr>
                        <th class="column-role-id"><?php _e('角色标识', 'role-manager'); ?></th>
                        <th class="column-role-name"><?php _e('显示名称', 'role-manager'); ?></th>
                        <th class="column-type"><?php _e('类型', 'role-manager'); ?></th>
                        <th class="column-users"><?php _e('用户数', 'role-manager'); ?></th>
                        <th class="column-actions"><?php _e('操作', 'role-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role_id => $role_data) : 
                        $is_default = $this->core->is_default_role($role_id);
                        $is_custom = $this->core->is_custom_role($role_id);
                        $display_name = $this->core->get_role_display_name($role_id);
                        $has_custom_name = isset($custom_names[$role_id]);
                        $user_count = count(get_users(array('role' => $role_id, 'fields' => 'ID')));
                        $parent_role = isset($inheritance[$role_id]) ? $inheritance[$role_id] : null;
                    ?>
                    <tr data-role="<?php echo esc_attr($role_id); ?>">
                        <td class="column-role-id">
                            <code><?php echo esc_html($role_id); ?></code>
                        </td>
                        <td class="column-role-name">
                            <span class="role-display-name"><?php echo esc_html($display_name); ?></span>
                            <?php if ($has_custom_name) : ?>
                                <span class="custom-name-badge"><?php _e('已改名', 'role-manager'); ?></span>
                            <?php endif; ?>
                            <?php if ($parent_role) : ?>
                                <span class="inheritance-badge"><?php printf(__('继承自 %s', 'role-manager'), esc_html($this->core->get_role_display_name($parent_role))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-type">
                            <?php if ($is_default) : ?>
                                <span class="badge badge-default"><?php _e('默认角色', 'role-manager'); ?></span>
                            <?php elseif ($is_custom) : ?>
                                <span class="badge badge-custom"><?php _e('自定义角色', 'role-manager'); ?></span>
                            <?php else : ?>
                                <span class="badge badge-other"><?php _e('其他角色', 'role-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-users">
                            <?php echo esc_html($user_count); ?>
                        </td>
                        <td class="column-actions">
                            <button type="button" class="button button-small action-edit-name" 
                                    data-role="<?php echo esc_attr($role_id); ?>"
                                    data-name="<?php echo esc_attr($display_name); ?>">
                                <?php _e('改名', 'role-manager'); ?>
                            </button>
                            <?php if ($has_custom_name) : ?>
                            <button type="button" class="button button-small action-reset-name"
                                    data-role="<?php echo esc_attr($role_id); ?>">
                                <?php _e('重置名称', 'role-manager'); ?>
                            </button>
                            <?php endif; ?>
                            <?php if (!$is_default) : ?>
                            <button type="button" class="button button-small action-edit-caps"
                                    data-role="<?php echo esc_attr($role_id); ?>">
                                <?php _e('编辑权限', 'role-manager'); ?>
                            </button>
                            <?php endif; ?>
                            <?php if (!$is_default) : ?>
                            <button type="button" class="button button-small button-link-delete action-delete-role"
                                    data-role="<?php echo esc_attr($role_id); ?>">
                                <?php _e('删除', 'role-manager'); ?>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="role-manager-sidebar">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('创建新角色', 'role-manager'); ?></h2>
                </div>
                <div class="inside">
                    <form id="create-role-form">
                        <p class="role-form-field">
                            <label for="new_role_id"><?php _e('角色标识', 'role-manager'); ?></label>
                            <input type="text" id="new_role_id" name="role_id" 
                                   class="regular-text" placeholder="<?php esc_attr_e('例如: custom_editor', 'role-manager'); ?>">
                            <span class="description"><?php _e('只能使用小写字母、数字和下划线', 'role-manager'); ?></span>
                        </p>
                        <p class="role-form-field">
                            <label for="new_role_name"><?php _e('显示名称', 'role-manager'); ?></label>
                            <input type="text" id="new_role_name" name="role_name" 
                                   class="regular-text" placeholder="<?php esc_attr_e('例如: 自定义编辑', 'role-manager'); ?>">
                        </p>
                        <p class="role-form-field">
                            <label for="new_role_parent"><?php _e('父角色（可选）', 'role-manager'); ?></label>
                            <select id="new_role_parent" name="parent_role" class="regular-text">
                                <option value=""><?php _e('无（不继承任何角色）', 'role-manager'); ?></option>
                                <?php foreach ($roles as $role_id => $role_data) : ?>
                                    <option value="<?php echo esc_attr($role_id); ?>">
                                        <?php echo esc_html($this->core->get_role_display_name($role_id)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="description"><?php _e('选择父角色后，新角色将自动继承父角色的所有权限', 'role-manager'); ?></span>
                        </p>
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('创建角色', 'role-manager'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('说明', 'role-manager'); ?></h2>
                </div>
                <div class="inside">
                    <div class="role-manager-help">
                        <div class="help-item">
                            <span class="badge badge-default"></span>
                            <span><?php _e('WordPress默认角色，只能修改显示名称', 'role-manager'); ?></span>
                        </div>
                        <div class="help-item">
                            <span class="badge badge-custom"></span>
                            <span><?php _e('通过本插件创建的角色', 'role-manager'); ?></span>
                        </div>
                        <div class="help-item">
                            <span class="badge badge-other"></span>
                            <span><?php _e('其他来源的角色', 'role-manager'); ?></span>
                        </div>
                        <div class="help-item">
                            <span class="badge badge-restricted"></span>
                            <span><?php _e('受限权限（无法分配）', 'role-manager'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="edit-name-modal" class="role-manager-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('修改角色名称', 'role-manager'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="edit-name-form">
                <input type="hidden" name="role_name" id="edit-role-name">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="edit-display-name"><?php _e('新名称', 'role-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="edit-display-name" name="new_name" class="regular-text">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('保存', 'role-manager'); ?></button>
                    <button type="button" class="button modal-cancel"><?php _e('取消', 'role-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<div id="delete-confirm-modal" class="role-manager-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('确认删除角色', 'role-manager'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="notice notice-warning inline">
                <p><strong><?php _e('警告：此操作不可恢复！', 'role-manager'); ?></strong></p>
                <p><?php _e('删除角色后，该角色的所有用户将被转移到默认角色。', 'role-manager'); ?></p>
            </div>
            <form id="delete-confirm-form">
                <input type="hidden" name="role_name" id="delete-role-name">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="delete-confirm-input"><?php _e('请输入角色标识确认删除', 'role-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="delete-confirm-input" class="regular-text" autocomplete="off">
                            <p class="description"><?php _e('角色标识：', 'role-manager'); ?><code id="delete-role-display"></code></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary button-link-delete"><?php _e('确认删除', 'role-manager'); ?></button>
                    <button type="button" class="button modal-cancel"><?php _e('取消', 'role-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<div id="edit-caps-modal" class="role-manager-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3><?php _e('编辑角色权限', 'role-manager'); ?> - <span id="caps-role-title"></span></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="edit-caps-form">
                <input type="hidden" name="role_name" id="caps-role-name">
                <div class="caps-notice" id="caps-parent-info" style="display: none;">
                    <p class="description">
                        <span class="badge badge-inherited"><?php _e('继承', 'role-manager'); ?></span> 
                        <span id="caps-parent-text"></span>
                    </p>
                </div>
                <div class="caps-search">
                    <input type="text" id="caps-search-input" placeholder="<?php esc_attr_e('搜索权限...', 'role-manager'); ?>">
                </div>
                <div class="caps-notice">
                    <p class="description">
                        <span class="badge badge-inherited"><?php _e('继承', 'role-manager'); ?></span> <?php _e('继承的权限来自父角色，无法编辑。如需修改，请编辑父角色的权限。', 'role-manager'); ?>
                    </p>
                    <p class="description">
                        <span class="badge badge-restricted"></span> <?php _e('标记的权限已被限制分配，无法通过本插件添加。', 'role-manager'); ?>
                    </p>
                </div>
                <div class="caps-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="column-cb check-column">
                                    <input type="checkbox" id="select-all-caps">
                                </th>
                                <th><?php _e('权限', 'role-manager'); ?></th>
                                <th><?php _e('状态', 'role-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="caps-tbody">
                            <?php foreach ($all_capabilities as $cap) : 
                                $is_restricted = in_array($cap, $restricted_caps);
                            ?>
                            <tr class="cap-row" data-cap="<?php echo esc_attr($cap); ?>">
                                <th class="check-column">
                                    <input type="checkbox" name="capabilities[<?php echo esc_attr($cap); ?>]" 
                                           value="1" class="cap-checkbox"
                                           <?php disabled($is_restricted); ?>>
                                </th>
                                <td>
                                    <code><?php echo esc_html($cap); ?></code>
                                </td>
                                <td>
                                    <span class="cap-status"></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('保存权限', 'role-manager'); ?></button>
                    <button type="button" class="button modal-cancel"><?php _e('取消', 'role-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>
