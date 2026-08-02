<?php
/**
 * admin/edit-product.php
 * -----------------------------------------------------------------------
 * فرم ویرایش یک محصول موجود (شناسه از طریق ?id=... می‌آید).
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Category.php';

requireLogin();

$activePage = 'products';
$pageTitle  = 'ویرایش محصول';

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? Product::find($id) : null;

if ($product === null) {
    header('Location: products');
    exit;
}

$errors = [];
$old = [
    'name'        => $product['name'],
    'description' => $product['description'] ?? '',
    'price'       => $product['price'],
    'category_id' => $product['category_id'],
    'badge'       => $product['badge'] ?? '',
    'status'      => $product['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $old = [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price'       => trim($_POST['price'] ?? ''),
            'category_id' => $_POST['category_id'] ?? '',
            'badge'       => trim($_POST['badge'] ?? ''),
            'status'      => $_POST['status'] ?? 'active',
        ];

        $errors = Product::validate($old);

        $file        = (!empty($_FILES['image']['name'])) ? $_FILES['image'] : null;
        $removeImage = isset($_POST['remove_image']);

        if (empty($errors)) {
            try {
                Product::update($id, $old, $file, $removeImage);
                $_SESSION['flash_success'] = 'محصول با موفقیت ویرایش شد.';
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
  <h4 class="mb-0" style="color:var(--gold-soft);">ویرایش محصول</h4>
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
             value="<?= htmlspecialchars((string) $old['name'], ENT_QUOTES, 'UTF-8') ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div class="mb-3">
      <label class="form-label">توضیحات</label>
      <textarea name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" rows="3"><?= htmlspecialchars((string) $old['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
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
      <label class="form-label">برچسب (اختیاری)</label>
      <input type="text" name="badge" class="form-control" value="<?= htmlspecialchars((string) $old['badge'], ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <?php if ($product['image']): ?>
      <div class="mb-3">
        <label class="form-label d-block">تصویر فعلی</label>
        <img src="<?= UPLOAD_DIR_URL . '/' . htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
             alt="" width="90" height="90" style="object-fit:cover; border-radius:10px;" class="mb-2">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage">
          <label class="form-check-label" style="font-size:13px; color:var(--muted);" for="removeImage">حذف تصویر فعلی (بدون جایگزین)</label>
        </div>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label"><?= $product['image'] ? 'جایگزینی تصویر (اختیاری)' : 'تصویر محصول (اختیاری)' ?> — JPG، PNG یا WEBP، حداکثر ۲ مگابایت</label>
      <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="form-control">
    </div>

    <div class="mb-4">
      <label class="form-label">وضعیت نمایش در منو</label>
      <select name="status" class="form-select">
        <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
        <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
      </select>
    </div>

    <button type="submit" class="btn btn-gold">ذخیرهٔ تغییرات</button>
  </form>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
