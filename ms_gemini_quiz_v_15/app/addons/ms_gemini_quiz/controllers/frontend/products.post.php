<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'view' || $mode == 'quick_view') {
    $product = Tygh::$app['view']->getTemplateVars('product');
    $product_id = !empty($product['product_id']) ? $product['product_id'] : 0;
    
    // Проверяем, запущен ли квиз прямо сейчас для этого товара в текущей сессии
    $show_quiz = !empty(Tygh::$app['session']['gemini_quiz'][$product_id]['active']);
    
    Tygh::$app['view']->assign('show_quiz', $show_quiz);
    
    // Также проверим, является ли текущий просматриваемый товар уже результатом какого-то подбора
    $quiz_owner_id = db_get_field("SELECT user_id FROM ?:gemini_quiz_products WHERE product_id = ?i", $product_id);
    Tygh::$app['view']->assign('quiz_owner_id', $quiz_owner_id);
}