<?php
namespace Tygh\Addons\MsGeminiQuiz;

use Tygh\Registry;

class GeminiClient
{
    private $api_keys = [];
    private $proxy_host;
    private $proxy_auth;

    public function __construct()
    {
        $raw_keys = Registry::get('addons.ms_gemini_quiz.api_keys');
        $this->api_keys = preg_split('/[\r\n,]+/', $raw_keys, -1, PREG_SPLIT_NO_EMPTY);
        $this->api_keys = array_map('trim', $this->api_keys);
        
        $this->proxy_host = Registry::get('addons.ms_gemini_quiz.proxy_host');
        $this->proxy_auth = Registry::get('addons.ms_gemini_quiz.proxy_auth');
    }

    public function sendRequest($post_data)
    {
        if (empty($this->api_keys)) {
            // Логируем отсутствие ключей
            fn_log_event('general', 'error', ['message' => 'Gemini API: Ключи не настроены в настройках модуля.']);
            return ['success' => false, 'error' => 'API Ключи не настроены'];
        }

        $json_data = json_encode($post_data, JSON_UNESCAPED_UNICODE);

        foreach ($this->api_keys as $key) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $key;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 40);

            if (!empty($this->proxy_host)) {
                curl_setopt($ch, CURLOPT_PROXY, trim($this->proxy_host));
                if (!empty($this->proxy_auth)) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, trim($this->proxy_auth));
                }
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
                    return ['success' => true, 'data' => $response_data['candidates'][0]['content']['parts'][0]['text']];
                }
            }

            // Если ошибка 429 (Лимит) или ошибка прокси - переходим к следующему ключу
            if ($http_code === 429 || $curl_error) {
                continue; 
            }
        }
        
        // Если мы вышли из цикла, значит упали ВСЕ ключи. Логируем это критическое событие!
        fn_log_event('requests', 'http', [
            'url' => 'Gemini API (Отказ всех ключей)',
            'data' => "Последний HTTP код: $http_code. Ошибка cURL: $curl_error",
            'response' => mb_substr($response, 0, 1000)
        ]);

        return ['success' => false, 'error' => 'Все API ключи исчерпали лимит или недоступны через прокси.'];
    }
}