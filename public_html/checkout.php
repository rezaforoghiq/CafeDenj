<?php declare(strict_types=1);require_once __DIR__.'/../includes/customer-auth.php';require_once __DIR__.'/../classes/Order.php';require_once __DIR__.'/../classes/Coupon.php';requireCustomerLogin();$c=(int)$_SESSION['customer_id'];$items=Order::cart($c);if(!$items){header('Location: cart');exit;}$err=null;$appliedCoupon = null;$couponMessage = null;if($_SERVER['REQUEST_METHOD']==='POST'){if(!verifyCsrfToken($_POST['csrf_token']??null))$err='نشست منقضی شده است.';else try{ $couponCode = isset($_POST['coupon_code']) ? trim((string)$_POST['coupon_code']) : null; $id=Order::place($c,(string)($_POST['note']??''), $couponCode); header('Location: order?id='.$id); exit;}catch(Throwable $e){$err=$e->getMessage();}}$total=array_sum(array_map(fn($i)=>(int)$i['price']*(int)$i['quantity'],$items));?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/css/customer-auth.css"><title>تکمیل سفارش</title></head><body class="customer-auth"><main class="auth-card"><div class="auth-brand"><h1>تکمیل سفارش</h1><p>پرداخت آنلاین ندارد؛ سفارش برای تأیید ارسال می‌شود.</p></div><?php if($err):?><div class="auth-alert"><?=htmlspecialchars($err)?></div><?php endif;?><p>
  <div id="original-block" style="display:none;font-size:13px;color:var(--muted);">
    مبلغ قبل از تخفیف: <span id="original-total" style="text-decoration:line-through; font-size:13px;"><?=number_format($total)?> تومان</span>
  </div>
  <div style="margin-top:6px;">مبلغ کل: <b id="order-total" style="font-size:18px"><?=number_format($total)?> تومان</b></div>
</p>
<form method="post" data-auth-form>
<input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
<div class="auth-field">
  <label>کد کوپن (اختیاری)</label>
  <div style="display:flex;gap:8px;align-items:center;">
    <input type="text" name="coupon_code" id="coupon_code" class="auth-input" placeholder="کد کوپن را وارد کنید" style="flex:1;">
    <button type="button" id="applyCoupon" class="auth-submit" style="width: 80px; padding:11px 13px; border-radius:8px; white-space:nowrap;">اعمال</button>
  </div>
  <div id="coupon-msg" style="color:var(--muted); font-size:13px; margin-top:6px;"></div>
</div>
<div class="auth-field"><label>توضیحات سفارش</label><textarea class="auth-input" name="note" maxlength="500" rows="4"></textarea></div>
<div class="auth-field"><label>تخفیف: </label><div id="coupon-discount">—</div></div>
<div class="auth-field"><label>مبلغ قابل پرداخت: </label><div id="final-total" style="font-size:18px; font-weight:700"><?=number_format($total)?> تومان</div></div>
<button class="auth-submit">ثبت نهایی سفارش</button>
</form>
</main>
<script>
(function(){
  const total = <?= (int)$total ?>;
  const input = document.getElementById('coupon_code');
  const applyBtn = document.getElementById('applyCoupon');
  const msg = document.getElementById('coupon-msg');
  const discountEl = document.getElementById('coupon-discount');
  const finalEl = document.getElementById('final-total');
  const totalEl = document.getElementById('order-total');

  function format(n){return new Intl.NumberFormat('fa-IR').format(n)+' تومان';}

  function clearCouponUI(){
    msg.textContent = '';
    discountEl.textContent = '—';
    finalEl.textContent = format(total);
    input.dataset.applied = '';
  }

  function showSuccess(code, percent){
    msg.style.color = 'var(--green)';
    msg.textContent = 'کوپن ' + code + ' با موفقیت اعمال شد — ' + percent + '%';
    const discount = Math.round(total * percent / 100);
    discountEl.textContent = format(discount) + ' (' + percent + '%)';
    finalEl.textContent = format(total - discount);
    // show original struck-through price
    document.getElementById('original-block').style.display = 'block';
    document.getElementById('original-total').textContent = format(total);
    document.getElementById('order-total').textContent = format(total - discount);
    input.dataset.applied = code;
  }

  function showError(text){
    msg.style.color = 'var(--danger)';
    msg.textContent = text;
    discountEl.textContent = '—';
    finalEl.textContent = format(total);
    document.getElementById('original-block').style.display = 'none';
    document.getElementById('order-total').textContent = format(total);
    input.dataset.applied = '';
  }

  applyBtn.addEventListener('click', function(){
    const code = input.value.trim();
    if(code === ''){ clearCouponUI(); return; }
    applyBtn.disabled = true;
    fetch('coupon-validate.php?code='+encodeURIComponent(code))
      .then(r=>r.json())
      .then(data=>{
        if(data.valid){
          showSuccess(code, data.percent);
        } else {
          showError(data.message || 'کوپن نامعتبر.');
        }
      }).catch(()=>{
        showError('خطا در بررسی کوپن.');
      }).finally(()=>{ applyBtn.disabled = false; });
  });

  // live-clear when input emptied
  input.addEventListener('input', function(){ if(!input.value.trim()) clearCouponUI(); });

})();
</script>
</body></html>
