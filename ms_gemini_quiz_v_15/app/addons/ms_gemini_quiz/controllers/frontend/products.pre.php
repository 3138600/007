<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'view') {
    $product_id = !empty($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : 0;
    
    static $quiz_data_cache = [];
    if (!isset($quiz_data_cache[$product_id])) {
        $quiz_data_cache[$product_id] = db_get_field("SELECT user_id FROM ?:gemini_quiz_products WHERE product_id = ?i", $product_id);
    }
    
    $quiz_owner_id = $quiz_data_cache[$product_id];
    
    if (!empty($quiz_owner_id)) {
        if (empty($auth['user_id']) || $auth['user_id'] != $quiz_owner_id) {
            return [CONTROLLER_STATUS_NO_PAGE];
        }
    }
}