<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * Выполняется при установке модуля: создает таблицу связей
 */
function fn_ms_gemini_quiz_install() {
    db_query("CREATE TABLE IF NOT EXISTS `?:gemini_quiz_products` (
        `product_id` int(11) unsigned NOT NULL,
        `user_id` int(11) unsigned NOT NULL,
        `original_product_id` int(11) unsigned NOT NULL,
        `timestamp` int(11) unsigned NOT NULL,
        PRIMARY KEY (`product_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

/**
 * Выполняется при удалении модуля: очищает БД
 */
function fn_ms_gemini_quiz_uninstall() {
    db_query("DROP TABLE IF EXISTS `?:gemini_quiz_products`;");
}

/**
 * Сохраняет характеристики от ИИ в созданный товар
 */
function fn_gemini_quiz_save_features_to_product($product_id, $features) {
    if (empty($features) || !is_array($features)) {
        return false;
    }

    foreach ($features as $feature_name => $feature_value) {
        // Ищем существующую характеристику по названию
        $feature_id = db_get_field(
            "SELECT feature_id FROM ?:product_features_descriptions WHERE description = ?s AND lang_code = ?s", 
            $feature_name, 'ru'
        );

        // Если характеристика найдена, привязываем её к товару
        if ($feature_id) {
            $product_features_data = [
                $feature_id => [
                    'feature_type' => 'T', // Текстовый тип
                    'value' => $feature_value
                ]
            ];
            // Используем стандартную функцию ядра CS-Cart для обновления характеристик
            fn_update_product_features_value($product_id, $product_features_data, 'ru');
        }
    }
    
    return true;
}