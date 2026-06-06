<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

use Tygh\Registry;
use Tygh\Enum\UsergroupTypes;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $suffix = '';

    //
    // Create/Update usergroups
    //
    if ($mode == 'update') {

        $usergroup_id = fn_update_usergroup($_REQUEST['usergroup_data'], $_REQUEST['usergroup_id'], DESCR_SL);

        if ($usergroup_id == false) {
            fn_delete_notification('changes_saved');
        }

        $suffix .= '.manage';
    }

    //
    // Delete selected usergroups
    //
    if ($mode == 'm_delete') {
        if (!empty($_REQUEST['usergroup_ids'])) {
            fn_delete_usergroups($_REQUEST['usergroup_ids']);
        }

        $suffix .= '.manage';
    }

    if ($mode == 'bulk_update_status') {
        if (!empty($_REQUEST['link_ids'])) {
            $new_status = $action == 'approve' ? 'A' : 'D';
            db_query("UPDATE ?:usergroup_links SET status = ?s WHERE link_id IN(?n)", $new_status, $_REQUEST['link_ids']);

            $force_notification = fn_get_notification_rules($_REQUEST);
            if (!empty($force_notification['C'])) {
                $usergroup_links = db_get_hash_multi_array("SELECT * FROM ?:usergroup_links WHERE link_id IN(?n)", array('user_id', 'usergroup_id'), $_REQUEST['link_ids']);
                foreach ($usergroup_links as $u_id => $val) {
                    fn_send_usergroup_status_notification($u_id, array_keys($val), $new_status);
                }
            }
        }

        $suffix = ".requests";
    }

    if ($mode == 'delete') {
        if (!empty($_REQUEST['usergroup_id'])) {
            fn_delete_usergroups((array) $_REQUEST['usergroup_id']);
        }

        return array(CONTROLLER_STATUS_REDIRECT, 'usergroups.manage');

    }

    if ($mode == 'update_status') {
        $user_data = fn_get_user_info($_REQUEST['user_id']);
        if (empty($user_data) || (Registry::get('runtime.company_id') && $user_data['is_root'] == 'Y') || (defined('RESTRICTED_ADMIN') && ($auth['user_id'] == $_REQUEST['user_id'] || fn_is_restricted_admin(array('user_id' => $_REQUEST['user_id']))))) {
            fn_set_notification('E', __('error'), __('access_denied'));
            exit;
        }

        $group_type = db_get_field("SELECT type FROM ?:usergroups WHERE usergroup_id = ?i", $_REQUEST['id']);

        if (empty($group_type) || ($group_type == 'A' && !in_array($user_data['user_type'], array('A','V')))) {
            fn_set_notification('E', __('error'), __('access_denied'));
            exit;
        }

        $old_status = db_get_field("SELECT status FROM ?:usergroup_links WHERE user_id = ?i AND usergroup_id = ?i", $_REQUEST['user_id'], $_REQUEST['id']);

        $result = fn_change_usergroup_status($_REQUEST['status'], $_REQUEST['user_id'], $_REQUEST['id'], fn_get_notification_rules($_REQUEST));
        if ($result) {
            fn_set_notification('N', __('notice'), __('status_changed'));
        } else {
            fn_set_notification('E', __('error'), __('error_status_not_changed'));
            Tygh::$app['ajax']->assign('return_status', empty($old_status) ? 'F' : $old_status);
        }

        exit;
    }

    return array(CONTROLLER_STATUS_OK, 'usergroups' . $suffix);
}


if ($mode == 'manage') {

    $exclude_types = defined('RESTRICTED_ADMIN') ? array('A') : array();

    if (fn_allowed_for('ULTIMATE')) {
        $customer_usergroups = fn_get_usergroups(array('exclude_types' => $exclude_types, 'type' => 'C'), DESCR_SL);
        $exclude_types[] = 'C';
    }

    $usergroups = fn_get_usergroups(array('exclude_types' => $exclude_types), DESCR_SL);

    if (fn_allowed_for('ULTIMATE')) {
        $usergroups = array_merge($usergroups, $customer_usergroups);
    }

    Tygh::$app['view']->assign(array(
        'usergroups'      => $usergroups,
        'usergroup_types' => UsergroupTypes::getList(),
    ));

    Registry::set('navigation.tabs', array (
        'general_0' => array (
            'title' => __('general'),
            'js' => true
        ),
    ));

} elseif ($mode == 'update') {

    $usergroup_id = isset($_REQUEST['usergroup_id']) ? $_REQUEST['usergroup_id'] : null;
    $usergroups = fn_get_usergroups(array('usergroup_id' => $usergroup_id), DESCR_SL);
    $usergroup = $usergroups[$usergroup_id];

    Tygh::$app['view']->assign('usergroup', $usergroup);

    $tabs = array(
        'general_' . $usergroup_id => array(
            'title' => __('general'),
            'js' => true
        ),
    );

    $show_privileges_tab = fn_check_can_usergroup_have_privileges($usergroup);
    if ($show_privileges_tab) {
        $tabs['privilege_' . $usergroup_id] = array(
            'title' => __('privileges'),
            'js' => true
        );
    }

    /* Privilege section */
    if (defined('RESTRICTED_ADMIN')) {
        $requested_mtype = db_get_field('SELECT type FROM ?:usergroups WHERE usergroup_id = ?i', $usergroup_id);
        if ($requested_mtype == 'A') {
            unset($tabs['privilege_' . $usergroup_id]);
        }
    }

    $usergroup_name = db_get_field('SELECT usergroup FROM ?:usergroup_descriptions WHERE usergroup_id = ?i AND lang_code = ?s', $usergroup_id, DESCR_SL);
    $usergroup_privileges = db_get_hash_single_array('SELECT privilege FROM ?:usergroup_privileges WHERE usergroup_id = ?i', array('privilege', 'privilege'), $usergroup_id);
    $privileges_data = fn_get_usergroup_privileges($usergroup);

    $_preload = array();

    foreach ($privileges_data as $key => $privilege) {
        $section = 'privilege_sections.' . $privilege['section_id'];
        if (!in_array($section, $_preload)) {
            $_preload[] = $section;
        }
        $_preload[] = 'privileges.' . $privilege['privilege'];
    }

    fn_preload_lang_vars($_preload);

    $_sections = array();
    foreach ($privileges_data as $key => $privilege) {
        $_sections[$privilege['section_id']] = __('privilege_sections.' . $privilege['section_id']);
        $privileges_data[$key]['description'] = __('privileges.' . $privilege['privilege']);
    }

    $privileges_data = fn_sort_array_by_key($privileges_data, 'description');
    asort($_sections);
    $privileges = array_fill_keys(array_keys($_sections), array());

    foreach ($privileges_data as $privilege) {
        $privilege['section'] = $_sections[$privilege['section_id']];
        $privileges[$privilege['section_id']][] = $privilege;
    }
    Tygh::$app['view']->assign(array(
        'usergroup_privileges' => $usergroup_privileges,
        'usergroup_name'       => $usergroup_name,
        'privileges'           => $privileges,
        'usergroup_types'      => UsergroupTypes::getList(),
        'show_privileges_tab'  => $show_privileges_tab,
    ));

    Registry::set('navigation.tabs', $tabs);

} elseif ($mode == 'requests') {

    list($requests, $search) = fn_get_usergroup_requests($_REQUEST, Registry::get('settings.Appearance.admin_elements_per_page'));

    Tygh::$app['view']->assign('usergroup_requests', $requests);
    Tygh::$app['view']->assign('search', $search);
}

