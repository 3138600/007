{if !$is_quiz_product}
    <div class="gemini-quiz-trigger-zone" style="margin: 20px 0;">
        <form action="{""|fn_url}" method="post">
            <input type="hidden" name="product_id" value="{$product.product_id}">
            <input type="hidden" name="dispatch" value="gemini_quiz.start">
            <input type="hidden" name="return_url" value="{$config.current_url}">
            
            <button type="submit" class="premium-quiz-btn">
                <span>Индивидуальный премиум подбор</span>
            </button>
        </form>
    </div>
{/if}

<style>
.premium-quiz-btn { background: linear-gradient(135deg, #111111 0%, #2c2c2c 100%); color: #dfba73; border: 1px solid #dfba73; padding: 14px 28px; font-size: 15px; text-transform: uppercase; font-weight: 600; border-radius: 4px; cursor: pointer; display: inline-flex; transition: all 0.4s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.premium-quiz-btn:hover { background: linear-gradient(135deg, #dfba73 0%, #c59b4e 100%); color: #111; transform: translateY(-2px); }
</style>