{$quiz_owner = ""|db_get_field:"SELECT user_id FROM ?:gemini_quiz_products WHERE product_id = ?i":$product.product_id}

{if $quiz_owner && $quiz_owner == $auth.user_id}
<div class="premium-chat-overlay" id="gemini_quiz_chat_container">
    <div class="premium-chat-window">
        <div class="premium-chat-header">
            <div class="ai-avatar-glow"></div>
            <div>
                <h3>ИИ-Брокер | Commercial Real Estate</h3>
                <span class="online-status">На связи. Анализирую параметры...</span>
            </div>
        </div>
        
        <div class="premium-chat-messages" id="quiz_chat_history"></div>

        <div class="premium-chat-footer" id="quiz_input_zone">
            <input type="text" id="quiz_user_text" placeholder="Введите ваш ответ...">
            <button id="quiz_send_btn" class="send-gold-btn">Ответить</button>
        </div>
    </div>
</div>

<script>
(function(_, $) {
    // Надежно получаем ID товара до инициализации JS-блока
    let productId = '{$product.product_id|intval}';
    if (!productId || productId == '0') {
        productId = $('form[name^="product_form_"] input[name="product_id"]').first().val();
    }
    
    {literal}
    let isFeatureSaved = false;

    function scrollToBottom() {
        let history = $('#quiz_chat_history');
        history.animate({ scrollTop: history.prop("scrollHeight") }, 400);
    }

    function showTypingIndicator() {
        let indicator = `<div class="msg-row ai-row" id="typing_indicator">
            <div class="msg-bubble ai-bubble typing"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
        </div>`;
        $('#quiz_chat_history').append(indicator);
        scrollToBottom();
    }

    function sendChatRequest(text, isInit = false) {
        if (!isInit) {
            $('#quiz_chat_history').append(`<div class="msg-row user-row animation-fade-up"><div class="msg-bubble user-bubble">${text}</div></div>`);
            $('#quiz_user_text').val('');
        }
        
        showTypingIndicator();
        $('#quiz_user_text').prop('disabled', true);
        $('#quiz_send_btn').prop('disabled', true);

        $.ceAjax('request', fn_url('gemini_quiz.process_chat'), {
            method: 'post',
            data: { product_id: productId, user_text: text, init: isInit ? 1 : 0 },
            callback: function(response) {
                $('#typing_indicator').remove();
                $('#quiz_user_text').prop('disabled', false).focus();
                $('#quiz_send_btn').prop('disabled', false);

                if (response.status === 'success') {
                    let aiMsg = response.chat_response.message;
                    $('#quiz_chat_history').append(`<div class="msg-row ai-row animation-fade-up"><div class="msg-bubble ai-bubble">${aiMsg}</div></div>`);
                    
                    // ЕСЛИ СЕРВЕР СОЗДАЛ НОВЫЙ ТОВАР - ДЕЛАЕМ РЕДИРЕКТ НА НЕГО
                    if (response.redirect_product_id && !isFeatureSaved) {
                        isFeatureSaved = true;
                        $('#quiz_input_zone').hide(); // Прячем поле ввода
                        $('#quiz_chat_history').append(`<div class="msg-row ai-row animation-fade-up"><div class="msg-bubble ai-bubble final-gold-bubble">✓ Спецификация сформирована. Перенаправляем на вашу персональную страницу...</div></div>`);
                        
                        // Жестко извлекаем числовое значение (спасает от ошибки [object Object])
                        let finalId = response.redirect_product_id;
                        if (typeof finalId === 'object' && finalId !== null) {
                            finalId = finalId.product_id || finalId[0] || Object.values(finalId)[0];
                        }
                        
                        // Перенаправляем
                        setTimeout(function(){ 
                            window.location.href = fn_url('products.view?product_id=' + parseInt(finalId)); 
                        }, 2500);
                    }
                } else {
                    alert('Ошибка связи с ИИ: ' + (response.error_text || 'Неизвестная ошибка'));
                }
                scrollToBottom();
            }
        });
    }

    $(document).ready(function() {
        sendChatRequest('', true);

        $('#quiz_send_btn').on('click', function() {
            let text = $('#quiz_user_text').val().trim();
            if(text) sendChatRequest(text);
        });

        $('#quiz_user_text').on('keypress', function(e) {
            if(e.which === 13) $('#quiz_send_btn').click();
        });
    });
    {/literal}
})(Tygh, Tygh.$);
</script>

<style>
.premium-chat-overlay { background: #0b0b0b; border: 1px solid #2a2415; border-radius: 12px; margin: 40px 0; overflow: hidden; }
.premium-chat-window { display: flex; flex-direction: column; height: 550px; }
.premium-chat-header { padding: 20px; border-bottom: 1px solid #2a2415; display: flex; align-items: center; gap: 15px; }
.ai-avatar-glow { width: 12px; height: 12px; background: #dfba73; border-radius: 50%; box-shadow: 0 0 12px #dfba73; }
.premium-chat-header h3 { margin: 0; color: #fff; font-size: 18px; font-weight: 500; }
.online-status { color: #888; font-size: 12px; }
.premium-chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
.msg-row { display: flex; width: 100%; }
.ai-row { justify-content: flex-start; }
.user-row { justify-content: flex-end; }
.msg-bubble { padding: 15px 22px; border-radius: 18px; max-width: 70%; font-size: 15px; line-height: 1.6; }
.ai-bubble { background: #1c1c1c; color: #e0e0e0; border: 1px solid #2b2b2b; border-bottom-left-radius: 4px; }
.user-bubble { background: linear-gradient(135deg, #dfba73 0%, #b88e3c 100%); color: #000; font-weight: 500; border-bottom-right-radius: 4px; }
.final-gold-bubble { background: transparent; border: 2px dashed #dfba73; color: #dfba73; text-align: center; max-width: 100%; }
.premium-chat-footer { padding: 20px; background: #111; border-top: 1px solid #2a2415; display: flex; gap: 15px; }
#quiz_user_text { flex: 1; background: #000; border: 1px solid #333; color: #fff; padding: 12px 20px; border-radius: 6px; font-size: 15px; }
#quiz_user_text:focus { border-color: #dfba73; outline: none; }
.send-gold-btn { background: #dfba73; color: #000; font-weight: 600; border: none; padding: 0 30px; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 13px; }
.animation-fade-up { animation: fadeUp 0.5s forwards; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.typing .dot { display: inline-block; width: 6px; height: 6px; background: #dfba73; border-radius: 50%; margin-right: 4px; animation: wave 1.3s infinite; }
.typing .dot:nth-child(2) { animation-delay: 0.15s; }
.typing .dot:nth-child(3) { animation-delay: 0.3s; }
@keyframes wave { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
</style>
{/if}