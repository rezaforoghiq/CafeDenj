<?php
/**
 * admin/coupons.php
 * مدیریت کوپن‌های تخفیف
 */

declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Coupon.php';

requireLogin();
$activePage = 'coupons';
$pageTitle = 'کوپن‌ها';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = null;
unset($_SESSION['flash_success']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست منقضی شده است.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create' || $action === 'update') {
            $data = [
                'code' => trim($_POST['code'] ?? ''),
                'percent' => trim($_POST['percent'] ?? ''),
                'expires_at' => trim($_POST['expires_at'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $errors = Coupon::validate($data);
            if (empty($errors)) {
                try {
                    if ($action === 'create') {
                        Coupon::create($data);
                        $_SESSION['flash_success'] = 'کوپن ایجاد شد.';
                    } else {
                        $id = (int) ($_POST['id'] ?? 0);
                        Coupon::update($id, $data);
                        $_SESSION['flash_success'] = 'کوپن به‌روزرسانی شد.';
                    }
                    header('Location: coupons');
                    exit;
                } catch (Throwable $e) {
                    $flashError = $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            Coupon::delete($id);
            $_SESSION['flash_success'] = 'کوپن حذف شد.';
            header('Location: coupons');
            exit;
        }
    }
}

$coupons = Coupon::all();
$editCoupon = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) $editCoupon = Coupon::find($editId);

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0" style="color:var(--gold-soft);">مدیریت کوپن‌ها</h4>
  <span style="color:var(--muted); font-size:13px;">تعداد: <?= count($coupons) ?></span>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="row">
  <div class="col-12 col-md-5">
    <div class="card p-3 mb-4">
      <h6 class="mb-3"><?= $editCoupon ? 'ویرایش کوپن' : 'ایجاد کوپن جدید' ?></h6>
      <?php if (!empty($errors)): ?><div class="alert alert-danger py-2 px-3" style="font-size:13px;">لطفاً خطاهای فرم را بررسی کنید.</div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="<?= $editCoupon ? 'update' : 'create' ?>">
        <?php if ($editCoupon): ?><input type="hidden" name="id" value="<?= (int)$editCoupon['id'] ?>"><?php endif; ?>

        <div class="mb-3">
          <label class="form-label">کد کوپن</label>
          <input name="code" class="form-control form-control-sm <?= isset($errors['code']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($editCoupon['code'] ?? '') ?>">
          <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['code'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">درصد تخفیف</label>
          <input name="percent" type="number" min="1" max="100" class="form-control form-control-sm <?= isset($errors['percent']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string)($editCoupon['percent'] ?? '')) ?>">
          <?php if (isset($errors['percent'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['percent'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">تاریخ انقضا (اختیاری)</label>
          <input type="text" data-jalali-picker data-name="expires_at" data-value="<?= htmlspecialchars($editCoupon['expires_at'] ?? '') ?>" class="form-control form-control-sm">
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="couponActive" <?= empty($editCoupon) || (int)($editCoupon['is_active'] ?? 1) === 1 ? 'checked' : '' ?> >
          <label class="form-check-label" for="couponActive" style="color:var(--muted);">فعال باشد</label>
        </div>

        <button class="btn btn-gold btn-sm"><?= $editCoupon ? 'به‌روزرسانی' : 'ایجاد' ?></button>
        <?php if ($editCoupon): ?><a href="coupons" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">انصراف</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-12 col-md-7">
    <div class="card p-3 mb-4">
      <h6 class="mb-3">لیست کوپن‌ها</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>کد</th><th>درصد</th><th>انقضا</th><th>فعال</th><th>عملیات</th></tr></thead>
          <tbody>
            <?php if (empty($coupons)): ?><tr><td colspan="5" class="text-center py-3" style="color:var(--muted);">کوپنی وجود ندارد.</td></tr><?php endif; ?>
            <?php foreach ($coupons as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$c['percent'] ?>%</td>
              <td><?= htmlspecialchars($c['expires_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$c['is_active'] ? 'بله' : 'خیر' ?></td>
              <td>
                <a href="coupons?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">ویرایش</a>
                <form method="post" class="d-inline" onsubmit="return confirm('کوپن حذف شود؟');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php';
