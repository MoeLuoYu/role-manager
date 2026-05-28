<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('权限不足', 'role-manager'));
}

$logs = Role_Manager_Logger::get_instance()->get_logs(100);
?>

<div class="wrap role-manager-wrap">
    <h1><?php _e('操作日志', 'role-manager'); ?></h1>

    <p class="description">
        <?php _e('查看角色管理操作的历史记录。', 'role-manager'); ?>
    </p>

    <div class="role-manager-logs-actions">
        <button type="button" id="clear-logs-btn" class="button">
            <?php _e('清空日志', 'role-manager'); ?>
        </button>
    </div>

    <?php if (empty($logs)) : ?>
        <div class="notice notice-info inline">
            <p><?php _e('暂无操作日志。', 'role-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped role-manager-logs-table">
            <thead>
                <tr>
                    <th class="column-time"><?php _e('时间', 'role-manager'); ?></th>
                    <th class="column-user"><?php _e('操作者', 'role-manager'); ?></th>
                    <th class="column-action"><?php _e('操作', 'role-manager'); ?></th>
                    <th class="column-details"><?php _e('详情', 'role-manager'); ?></th>
                    <th class="column-ip"><?php _e('IP地址', 'role-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                <tr>
                    <td class="column-time">
                        <?php echo esc_html(mysql2date('Y-m-d H:i:s', $log['time'], true)); ?>
                    </td>
                    <td class="column-user">
                        <?php if ($log['user_id']) : ?>
                            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $log['user_id'])); ?>">
                                <?php echo esc_html($log['user_name']); ?>
                            </a>
                        <?php else : ?>
                            <?php _e('未知', 'role-manager'); ?>
                        <?php endif; ?>
                    </td>
                    <td class="column-action">
                        <span class="log-action log-action-<?php echo esc_attr($log['action']); ?>">
                            <?php echo esc_html(Role_Manager_Logger::get_instance()->get_action_label($log['action'])); ?>
                        </span>
                    </td>
                    <td class="column-details">
                        <?php if (!empty($log['details'])) : ?>
                            <ul class="log-details-list">
                                <?php foreach ($log['details'] as $key => $value) : ?>
                                    <li>
                                        <strong><?php echo esc_html($key); ?>:</strong> 
                                        <?php if (is_array($value)) : ?>
                                            <?php echo esc_html(json_encode($value)); ?>
                                        <?php else : ?>
                                            <?php echo esc_html($value); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td class="column-ip">
                        <?php echo esc_html($log['user_ip']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
