<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<style>
.password-visibility-field{display:flex;align-items:stretch;min-width:0;border:1px solid var(--line-2);border-radius:var(--radius);background:var(--surface);transition:border-color .15s,box-shadow .15s}
.password-visibility-field:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent)}
.editor-page .password-visibility-field .input{flex:1 1 0;width:0;min-width:0;padding:11px 14px;border:0;background:transparent;box-shadow:none;outline:0}
.editor-page .password-visibility-field .input:focus{border:0;box-shadow:none;outline:0}
.password-visibility-toggle{display:flex;align-items:center;justify-content:center;flex:0 0 64px;min-width:64px;min-height:38px;margin:4px;padding:6px 10px;border:0;border-radius:6px;background:var(--surface-2);color:var(--text);font:inherit;font-size:13px;font-weight:600;line-height:1.4;white-space:nowrap;cursor:pointer}
.password-visibility-toggle:hover{background:var(--primary-light)}
.password-visibility-toggle:focus-visible{outline:2px solid var(--primary);outline-offset:-2px}
.password-visibility-toggle[hidden]{display:none}
</style>

<div class="editor-page editor-page--standard">

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Change Password</h1>
    <p class="muted" style="margin:6px 0 0;">Update the password for <?= esc($user['full_name']) ?>.</p>
</div>

<?= view('partials/form_messages') ?>

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">Password Information</h2>
            <p class="muted" style="margin:6px 0 0;">Enter and confirm the new password for this user.</p>
        </div>

        <a class="btn secondary" href="<?= site_url('admin/users') ?>">← Back</a>
    </div>

    <form action="<?= site_url('admin/users/password-update/' . $user['id']) ?>" method="post">
        <?= csrf_field() ?>
        <p class="editor-form-note">Fields marked <span class="required-mark">*</span> are required.</p>

        <div class="row" style="gap:20px; flex-wrap:wrap;">
            <div class="field" style="flex:1; min-width:280px;">
                <label for="password" class="label">New Password <span class="required-mark" aria-hidden="true">*</span></label>
                <div class="password-visibility-field">
                    <input type="password" name="password" id="password" class="input" minlength="8" maxlength="255" autocomplete="new-password" required>
                    <button type="button" class="password-visibility-toggle" data-password-visibility="password" data-password-label="new password" aria-controls="password" aria-label="Show new password" aria-pressed="false" hidden>Show</button>
                </div>
            </div>

            <div class="field" style="flex:1; min-width:280px;">
                <label for="confirm_password" class="label">Confirm Password <span class="required-mark" aria-hidden="true">*</span></label>
                <div class="password-visibility-field">
                    <input type="password" name="confirm_password" id="confirm_password" class="input" minlength="8" maxlength="255" autocomplete="new-password" required>
                    <button type="button" class="password-visibility-toggle" data-password-visibility="confirm_password" data-password-label="confirmation password" aria-controls="confirm_password" aria-label="Show confirmation password" aria-pressed="false" hidden>Show</button>
                </div>
            </div>
        </div>
        <span class="form-help">Use at least 8 characters and enter the same password twice.</span>

        <div class="sp"></div>

        <?= view('partials/editor_actions', ['cancelUrl' => site_url('admin/users'), 'submitLabel' => 'Update Password']) ?>
    </form>
</div>

</div>

<script>
document.querySelectorAll('[data-password-visibility]').forEach(function(button){
    const input = document.getElementById(button.dataset.passwordVisibility);
    if (!input) return;
    function setVisible(visible) {
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + button.dataset.passwordLabel);
    }
    button.hidden = false;
    button.addEventListener('click', function(){ setVisible(input.type === 'password'); });
    input.form.addEventListener('reset', function(){ setVisible(false); });
    window.addEventListener('pageshow', function(){ setVisible(false); });
});
</script>

<?= $this->endSection() ?>
