(function () {
    const modal = document.getElementById('modal-user');
    const form = document.getElementById('form-user');
    const titleEl = document.getElementById('user-modal-title');
    const passwordField = document.getElementById('user-password-field');
    const passwordInput = document.getElementById('user-password');

    function openModal() { modal.classList.add('open'); }
    function closeModal() { modal.classList.remove('open'); form.reset(); }

    document.getElementById('btn-new-user').addEventListener('click', () => {
        titleEl.textContent = 'Tambah User';
        document.getElementById('user-id').value = '';
        passwordField.style.display = '';
        passwordInput.required = true;
        openModal();
    });

    modal.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            closeModal();
        }
    });

    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', () => {
            titleEl.textContent = 'Edit User';
            document.getElementById('user-id').value = btn.dataset.id;
            document.getElementById('user-name').value = btn.dataset.name;
            document.getElementById('user-email').value = btn.dataset.email;
            passwordField.style.display = 'none';
            passwordInput.required = false;
            openModal();
        });
    });

    document.getElementById('btn-save-user').addEventListener('click', async () => {
        if (!form.reportValidity()) return;
        const id = document.getElementById('user-id').value;
        const fd = new FormData(form);
        fd.append('_csrf', CSRF_TOKEN);
        const url = id ? 'user_edit.php' : 'user_create.php';
        const res = await fetch(url, { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) { alert(json.message); return; }
        location.reload();
    });

    document.querySelectorAll('.btn-toggle-user').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.active === '1' ? 'menonaktifkan' : 'mengaktifkan';
            if (!confirm(`Yakin ingin ${action} user ini?`)) return;
            const fd = new FormData();
            fd.append('id', btn.dataset.id);
            fd.append('_csrf', CSRF_TOKEN);
            const res = await fetch('user_toggle_status.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) { alert(json.message); return; }
            location.reload();
        });
    });

    document.querySelectorAll('.btn-reset-user').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Reset password user ini?')) return;
            const fd = new FormData();
            fd.append('id', btn.dataset.id);
            fd.append('_csrf', CSRF_TOKEN);
            const res = await fetch('user_reset_password.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) { alert(json.message); return; }
            alert(`Password baru: ${json.data.new_password}`);
        });
    });
})();
