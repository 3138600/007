{include file="common/subheader.tpl" title="Настройки ИИ-Брокера (Gemini Quiz)" target="#acc_gemini_quiz"}
<div id="acc_gemini_quiz" class="collapse in">
    <div class="control-group">
        <label class="control-label" for="elm_gemini_prompt">Индивидуальный промт для ИИ:</label>
        <div class="controls">
            <textarea id="elm_gemini_prompt" name="product_data[gemini_prompt]" cols="55" rows="8" class="input-large">{$product_data.gemini_prompt}</textarea>
            <p class="muted description">Задайте системные инструкции для ИИ-брокера при подборе на основе этого объекта. Если оставить пустым, применится базовый системный промт.</p>
        </div>
    </div>
</div>