<?php
/**
 * admin/add-product.php
 * -----------------------------------------------------------------------
 * فرم افزودن محصول جدید به منو.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Category.php';

requireLogin();

$activePage = 'products';
$pageTitle  = 'افزودن محصول';

$errors = [];
$old    = [
    'name' => '', 'description' => '', 'price' => '', 'category_id' => '', 'badge' => '', 'status' => 'active',
    'discount_enabled' => false, 'discount_type' => 'percentage', 'discount_value' => '',
    'discount_starts_at' => '', 'discount_ends_at' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $old = [
            'name'               => trim($_POST['name'] ?? ''),
            'description'        => trim($_POST['description'] ?? ''),
            'price'              => trim($_POST['price'] ?? ''),
            'category_id'        => $_POST['category_id'] ?? '',
            'badge'              => trim($_POST['badge'] ?? ''),
            'status'             => $_POST['status'] ?? 'active',
            'discount_enabled'   => isset($_POST['discount_enabled']),
            'discount_type'      => $_POST['discount_type'] ?? 'percentage',
            'discount_value'     => trim($_POST['discount_value'] ?? ''),
            'discount_starts_at' => trim($_POST['discount_starts_at'] ?? ''),
            'discount_ends_at'   => trim($_POST['discount_ends_at'] ?? ''),
        ];

        $errors = Product::validate($old);

        // بررسی فایل آپلودی فقط اگر واقعاً چیزی انتخاب شده باشد
        $file = (!empty($_FILES['image']['name'])) ? $_FILES['image'] : null;

        if (empty($errors)) {
            try {
                Product::create($old, $file);
                $_SESSION['flash_success'] = 'محصول با موفقیت اضافه شد.';
                header('Location: products');
                exit;
            } catch (RuntimeException $e) {
                $errors['general'] = $e->getMessage();
            }
        }
    }
}

$categories = Category::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0" style="color:var(--gold-soft);">افزودن محصول جدید</h4>
  <a href="products" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">بازگشت به لیست</a>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card p-4" style="max-width:640px;">
  <form method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="mb-3">
      <label class="form-label">نام محصول</label>
      <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
             value="<?= htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8') ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label">توضیحات</label>
      <textarea name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" rows="3"><?= htmlspecialchars($old['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">قیمت (تومان)</label>
        <input type="number" name="price" min="0" step="1000" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars((string) $old['price'], ENT_QUOTES, 'UTF-8') ?>" required>
        <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">دسته‌بندی</label>
        <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
          <option value="">انتخاب کنید...</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= (string) $old['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['category_id'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">برچسب (اختیاری — مثل «پرفروش» یا «خانگی»)</label>
      <input type="text" name="badge" class="form-control" value="<?= htmlspecialchars($old['badge'], ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">تصویر محصول (JPG، PNG یا WEBP — حداکثر ۲ مگابایت)</label>
      <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="form-control">
    </div>

    <hr style="border-color:var(--line);">

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="discount_enabled" id="discountEnabled" <?= $old['discount_enabled'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="discountEnabled">این محصول تخفیف داشته باشد</label>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">نوع تخفیف</label>
        <select name="discount_type" class="form-select <?= isset($errors['discount_type']) ? 'is-invalid' : '' ?>">
          <option value="percentage" <?= $old['discount_type'] === 'percentage' ? 'selected' : '' ?>>درصدی (%)</option>
          <option value="fixed" <?= $old['discount_type'] === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت (تومان)</option>
        </select>
        <?php if (isset($errors['discount_type'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['discount_type'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">مقدار تخفیف</label>
        <input type="number" name="discount_value" min="0" step="0.01" class="form-control <?= isset($errors['discount_value']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars((string) $old['discount_value'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['discount_value'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['discount_value'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">شروع تخفیف (اختیاری)</label>
        <input type="text" data-jalali-picker data-name="discount_starts_at" data-value="<?= htmlspecialchars((string) $old['discount_starts_at'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="انتخاب تاریخ">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">پایان تخفیف (اختیاری)</label>
        <input type="text" data-jalali-picker data-name="discount_ends_at" data-value="<?= htmlspecialchars((string) $old['discount_ends_at'], ENT_QUOTES, 'UTF-8') ?>" class="form-control <?= isset($errors['discount_ends_at']) ? 'is-invalid' : '' ?>" placeholder="انتخاب تاریخ">
        <?php if (isset($errors['discount_ends_at'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['discount_ends_at'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="form-text" style="color:var(--muted); font-size:12px;">اگر خالی بماند، تخفیف تا لغو دستی ادامه دارد.</div>
      </div>
    </div>

    <hr style="border-color:var(--line);">

    <div class="mb-4">
      <label class="form-label">وضعیت نمایش در منو</label>
      <select name="status" class="form-select">
        <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
        <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
      </select>
    </div>

    <button type="submit" class="btn btn-gold">ثبت محصول</button>
  </form>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
