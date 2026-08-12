<?php
/**
 * admin/events.php
 * -----------------------------------------------------------------------
 * مدیریت ایونت/اطلاعیه‌ای که به‌صورت پاپ‌آپ در صفحهٔ منوی عمومی نمایش
 * داده می‌شود. در هر لحظه فقط یکی می‌تواند «فعال» باشد.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Event.php';

requireLogin();
requireAdmin();

$activePage = 'events';
$pageTitle  = 'ایونت‌ها';

$errors       = [];
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

$editId  = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? Event::find($editId) : null;

$old = $editing
    ? ['title' => $editing['title'], 'content' => $editing['content'], 'collect_phone' => $editing['collect_phone'], 'is_active' => $editing['is_active']]
    : ['title' => '', 'content' => '', 'collect_phone' => 1, 'is_active' => 0];

// ---------------------------------------------------------------
// ثبت / ویرایش
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $saveId = (int) ($_POST['id'] ?? 0);

        $old = [
            'title'         => trim($_POST['title'] ?? ''),
            'content'       => trim($_POST['content'] ?? ''),
            'collect_phone' => isset($_POST['collect_phone']) ? 1 : 0,
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = Event::validate($old);

        if (empty($errors)) {
            if ($saveId > 0) {
                Event::update($saveId, $old);
                $_SESSION['flash_success'] = 'ایونت با موفقیت ویرایش شد.';
            } else {
                Event::create($old);
                $_SESSION['flash_success'] = 'ایونت جدید ثبت شد.';
            }

            header('Location: events');
            exit;
        }

        $editId  = $saveId;
        $editing = $saveId > 0 ? ['id' => $saveId] : null;
    }
}

// ---------------------------------------------------------------
// حذف
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        Event::delete((int) $_POST['id']);
        $flashSuccess = 'ایونت حذف شد.';
    } else {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    }
}

// ---------------------------------------------------------------
// فعال/غیرفعال کردن سریع از داخل لیست
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $ev = Event::find((int) $_POST['id']);
        if ($ev) {
            Event::update((int) $ev['id'], [
                'title'         => $ev['title'],
                'content'       => $ev['content'],
                'collect_phone' => $ev['collect_phone'],
                'is_active'     => $ev['is_active'] ? 0 : 1,
            ]);
            $flashSuccess = 'وضعیت ایونت تغییر کرد.';
        }
    } else {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    }
}

$events = Event::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<h4 class="mb-4" style="color:var(--gold-soft);">مدیریت ایونت‌ها / پاپ‌آپ سایت</h4>
<p style="color:var(--muted); font-size:13px; margin-top:-16px; margin-bottom:24px;">
  در هر لحظه فقط یک ایونت می‌تواند «فعال» باشد؛ همان یکی به‌صورت پاپ‌آپ به بازدیدکنندگان منو نمایش داده می‌شود.
</p>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- فرم افزودن/ویرایش -->
  <div class="col-12 col-lg-5">
    <div class="card p-3">
      <h6 class="mb-3" style="color:var(--ivory);"><?= $editing ? 'ویرایش ایونت' : 'افزودن ایونت جدید' ?></h6>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($editing): ?>
          <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px;">عنوان</label>
          <input type="text" name="title" class="form-control form-control-sm <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                 value="<?= htmlspecialchars((string) $old['title'], ENT_QUOTES, 'UTF-8') ?>" required>
          <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px;">متن ایونت</label>
          <textarea name="content" rows="4" class="form-control form-control-sm <?= isset($errors['content']) ? 'is-invalid' : '' ?>" required><?= htmlspecialchars((string) $old['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
          <?php if (isset($errors['content'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['content'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="collect_phone" id="collectPhone" <?= $old['collect_phone'] ? 'checked' : '' ?>>
          <label class="form-check-label" style="font-size:13px; color:var(--muted);" for="collectPhone">
            نمایش فرم دریافت شمارهٔ تماس داخل پاپ‌آپ
          </label>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $old['is_active'] ? 'checked' : '' ?>>
          <label class="form-check-label" style="font-size:13px; color:var(--muted);" for="isActive">
            فعال باشد (به بازدیدکنندگان نمایش داده شود)
          </label>
        </div>

        <button type="submit" class="btn btn-gold btn-sm w-100"><?= $editing ? 'ذخیرهٔ تغییرات' : 'ثبت ایونت' ?></button>
        <?php if ($editing): ?>
          <a href="events" class="btn btn-sm btn-outline-light w-100 mt-2" style="border-color:var(--line); color:var(--ivory);">انصراف از ویرایش</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- لیست ایونت‌ها -->
  <div class="col-12 col-lg-7">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>عنوان</th>
            <th>وضعیت</th>
            <th>تاریخ ساخت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($events)): ?>
            <tr><td colspan="4" class="text-center py-4" style="color:var(--muted);">هنوز ایونتی ثبت نشده.</td></tr>
          <?php endif; ?>

          <?php foreach ($events as $ev): ?>
            <tr>
              <td><?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $ev['is_active'] ? 'btn-success' : 'btn-secondary' ?>">
                    <?= $ev['is_active'] ? 'فعال' : 'غیرفعال' ?>
                  </button>
                </form>
              </td>
              <td style="color:var(--muted); font-size:13px;"><?= htmlspecialchars($ev['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <a href="events?edit=<?= $ev['id'] ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">ویرایش</a>
                <form method="POST" class="d-inline" onsubmit="return confirm('این ایونت حذف شود؟');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
