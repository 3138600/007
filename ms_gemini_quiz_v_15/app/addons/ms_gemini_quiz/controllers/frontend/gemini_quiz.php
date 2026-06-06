<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Registry;
use Tygh\Addons\MsGeminiQuiz\GeminiClient;

if ($mode == 'start') {
    if (empty($auth['user_id'])) {
        fn_set_notification('W', __('notice'), 'Для использования премиум-подбора, пожалуйста, войдите в систему.');
        return [CONTROLLER_STATUS_REDIRECT, 'auth.login_form?return_url=' . urlencode($_REQUEST['return_url'])];
    }

    $product_id = (int)$_REQUEST['product_id'];
    
    Tygh::$app['session']['gemini_quiz'][$product_id] = [
        'active' => true,
        'messages' => []
    ];
    
    return [CONTROLLER_STATUS_REDIRECT, 'products.view?product_id=' . $product_id];
}

if ($mode == 'process_chat') {
    if (empty($auth['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') { exit; }

    $product_id = (int)$_REQUEST['product_id'];
    $is_init = !empty($_REQUEST['init']);
    $user_text = trim($_REQUEST['user_text'] ?? '');

    if (empty(Tygh::$app['session']['gemini_quiz'][$product_id]['active'])) { exit; }
    $session_data = &Tygh::$app['session']['gemini_quiz'][$product_id];

    $feature_ids = db_get_fields("SELECT DISTINCT feature_id FROM ?:product_features_values WHERE product_id = ?i", $product_id);
    
    $expected_features = [];
    if (!empty($feature_ids)) {
        $expected_features = db_get_fields("SELECT description FROM ?:product_features_descriptions WHERE feature_id IN (?n) AND lang_code = 'ru'", $feature_ids);
    }
    
    $features_list_text = !empty($expected_features) 
        ? implode(', ', $expected_features) 
        : 'ключевые параметры';

    if ($is_init && empty($session_data['messages'])) {
        $user_text = "Привет! Я хочу подобрать конфигурацию. Выступи в роли премиум-ассистента. 
        Твоя задача выяснить у меня значения для следующих параметров: [ " . $features_list_text . " ]. 
        Задавай мне уточняющие вопросы СТРОГО ПО ОДНОМУ. Как только соберешь все данные — сообщи об этом.";
    }

    if (empty($user_text)) { exit; }

    $session_data['messages'][] = ['role' => 'user', 'text' => $user_text];

    $contents = [];
    foreach ($session_data['messages'] as $msg) {
        $contents[] = [
            'role' => $msg['role'] == 'user' ? 'user' : 'model',
            'parts' => [['text' => $msg['role'] == 'user' ? $msg['text'] : json_encode(['message' => $msg['text']], JSON_UNESCAPED_UNICODE)]]
        ];
    }

    $system_instruction = db_get_field("SELECT gemini_prompt FROM ?:products WHERE product_id = ?i", $product_id);
    
    if (empty($system_instruction)) {
        $system_instruction = "Ты — премиальный ИИ-ассистент. Твоя задача: путем диалога выявить потребности клиента и заполнить конкретные характеристики.
СПИСОК ХАРАКТЕРИСТИК ДЛЯ СБОРА: " . $features_list_text . ".
Правила:
1. Задавай вопросы последовательно, чтобы выяснить данные по списку.
2. Твой ответ ВСЕГДА должен быть в валидном JSON.
3. КРИТИЧЕСКИ ВАЖНО: НИКОГДА не используй двойные кавычки (\") и переносы строк внутри текста. Заменяй их на одинарные (') или елочки («»).
4. Шаблон ответа: {\"message\": \"твой текст клиенту\", \"is_complete\": false, \"extracted_features\": {}}.
5. Когда выяснишь значения для ВСЕХ характеристик из списка, установи is_complete: true и заполни extracted_features, где КЛЮЧИ — это ТОЧНЫЕ названия характеристик из списка выше.";
    } else {
        $system_instruction .= "\n\nСПИСОК ХАРАКТЕРИСТИК ДЛЯ СБОРА: " . $features_list_text . ". \nКРИТИЧЕСКИ ВАЖНО: Ответ СТРОГО в JSON. НИКОГДА не используй двойные кавычки (\") внутри текста. Заменяй их на одинарные ('). Шаблон: {\"message\": \"текст\", \"is_complete\": false, \"extracted_features\": {}}";
    }

    $post_data = [
        'systemInstruction' => ['parts' => [['text' => $system_instruction]]],
        'contents' => $contents,
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'maxOutputTokens' => 2048 
        ]
    ];

    $class_path = Registry::get('config.dir.addons') . 'ms_gemini_quiz/Tygh/Addons/MsGeminiQuiz/GeminiClient.php';
    if (file_exists($class_path)) { require_once $class_path; }
    
    $gemini = new \Tygh\Addons\MsGeminiQuiz\GeminiClient();
    $result = $gemini->sendRequest($post_data);

    if (isset($result['success']) && $result['success']) {
        
        $raw_data = trim($result['data']);
        $raw_data = str_replace(['```json', '```JSON', '```'], '', $raw_data);
        $raw_data = trim($raw_data);

        $ai_response = json_decode($raw_data, true);

        if (is_array($ai_response) && !empty($ai_response['message'])) {
            $session_data['messages'][] = ['role' => 'model', 'text' => $ai_response['message']];
            
            if (!empty($ai_response['is_complete'])) {
                
                $base_name = db_get_field("SELECT product FROM ?:product_descriptions WHERE product_id = ?i AND lang_code = 'ru'", $product_id);
                $base_company = db_get_field("SELECT company_id FROM ?:products WHERE product_id = ?i", $product_id);
                $base_categories = db_get_fields("SELECT category_id FROM ?:products_categories WHERE product_id = ?i", $product_id);

                $desc_html = '<div style="background:#0b0b0b; color:#fff; padding:25px; border-radius:12px; font-family:sans-serif; border:1px solid #2a2415;">';
                $desc_html .= '<h3 style="color:#dfba73; margin-top:0;">Стенограмма работы с ИИ-ассистентом</h3>';
                foreach ($session_data['messages'] as $m) {
                    if (strpos($m['text'], 'Привет! Я хочу подобрать конфигурацию') !== false) continue;
                    $name = $m['role'] == 'user' ? 'Вы' : 'ИИ-Ассистент';
                    $color = $m['role'] == 'user' ? '#ffffff' : '#dfba73';
                    $desc_html .= "<div style='margin-bottom:20px;'><strong style='color:{$color}; text-transform:uppercase; font-size:12px; letter-spacing:1px;'>{$name}</strong><br><div style='margin-top:6px; line-height:1.5;'>{$m['text']}</div></div>";
                }
                $desc_html .= '</div>';

                $new_product_data = [
                    'product' => 'Персональная спецификация: ' . ($base_name ?: 'Конфигурация'),
                    'price' => 0.00,
                    'status' => 'A',
                    'company_id' => $base_company ?: 0,
                    'category_ids' => $base_categories ?: [0],
                    'main_category' => !empty($base_categories) ? reset($base_categories) : 0,
                    'full_description' => $desc_html,
                    'short_description' => 'Индивидуально подобранная спецификация на базе ваших ответов.'
                ];

                $new_product_id = fn_update_product($new_product_data, 0, 'ru');
                
                if ($new_product_id) {
                    db_query("INSERT INTO ?:gemini_quiz_products (product_id, user_id, original_product_id, timestamp) VALUES (?i, ?i, ?i, ?i)", 
                        $new_product_id, $auth['user_id'], $product_id, time()
                    );

                    if (!empty($ai_response['extracted_features'])) {
                        fn_gemini_quiz_save_features_to_product($new_product_id, $ai_response['extracted_features']);
                    }
                    
                    fn_update_product_count();

                    // ЛОГИРУЕМ УСПЕШНОЕ СОЗДАНИЕ ТОВАРА
                    fn_log_event('products', 'create', [
                        'product' => "AI-Брокер: Успешно создана персональная спецификация ID {$new_product_id} на базе шаблона ID {$product_id}. Клиент: {$auth['user_id']}."
                    ]);

                    unset(Tygh::$app['session']['gemini_quiz'][$product_id]);
                    Tygh::$app['ajax']->assign('redirect_product_id', $new_product_id);
                }
            }

            Tygh::$app['ajax']->assign('status', 'success');
            Tygh::$app['ajax']->assign('chat_response', $ai_response);
        } else {
            Tygh::$app['ajax']->assign('status', 'error');
            $debug_response = mb_substr($raw_data, 0, 300); 
            
            // ЛОГИРУЕМ ОШИБКУ РАСШИФРОВКИ
            fn_log_event('general', 'error', [
                'message' => "AI-Брокер: Ошибка формата JSON. Нейросеть сломала синтаксис.\nТочная ошибка: " . json_last_error_msg() . "\nСырой ответ от ИИ: \n" . $raw_data
            ]);

            Tygh::$app['ajax']->assign('error_text', 'Сбой JSON (' . json_last_error_msg() . '). Ответ записан в журнал событий.');
        }
    } else {
        Tygh::$app['ajax']->assign('status', 'error');
        Tygh::$app['ajax']->assign('error_text', $result['error']);
    }
    exit;
}