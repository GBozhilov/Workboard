const MODAL_ID = 'workboard-confirm-modal';
const BYPASS_FLAG = 'confirmBypass';

let pendingForm = null;

function formRequiresConfirm(form) {
    return form instanceof HTMLFormElement && form.hasAttribute('data-confirm');
}

function formIsDestructive(form) {
    return form.hasAttribute('data-confirm-destructive');
}

function getModal() {
    return document.getElementById(MODAL_ID);
}

function closeModal() {
    const modal = getModal();

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingForm = null;

    const confirmButton = document.getElementById('workboard-confirm-confirm');
    if (confirmButton) {
        confirmButton.disabled = false;
        confirmButton.classList.remove('opacity-60', 'cursor-not-allowed');
    }
}

function openModal(form) {
    const modal = getModal();

    if (!modal) {
        form.submit();

        return;
    }

    pendingForm = form;

    const title = form.dataset.confirmTitle ?? 'Are you sure?';
    const message = form.dataset.confirmMessage ?? 'This action cannot be undone.';
    const confirmLabel = form.dataset.confirmLabel ?? 'Confirm';
    const destructive = formIsDestructive(form);

    document.getElementById('workboard-confirm-title').textContent = title;
    document.getElementById('workboard-confirm-message').textContent = message;

    const confirmButton = document.getElementById('workboard-confirm-confirm');
    confirmButton.textContent = confirmLabel;
    confirmButton.className =
        'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition ' +
        (destructive
            ? 'bg-red-600 hover:bg-red-500'
            : 'bg-indigo-600 hover:bg-indigo-500');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    confirmButton.focus();
}

function confirmPendingForm() {
    if (!pendingForm) {
        return;
    }

    const form = pendingForm;
    const confirmButton = document.getElementById('workboard-confirm-confirm');

    confirmButton.disabled = true;
    confirmButton.classList.add('opacity-60', 'cursor-not-allowed');

    form.dataset[BYPASS_FLAG] = '1';
    closeModal();
    form.requestSubmit();
}

function bindConfirmModal() {
    const modal = getModal();

    if (!modal) {
        return;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!formRequiresConfirm(form)) {
            return;
        }

        if (form.dataset[BYPASS_FLAG] === '1') {
            delete form.dataset[BYPASS_FLAG];

            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openModal(form);
    });

    document.getElementById('workboard-confirm-cancel')?.addEventListener('click', closeModal);
    document.getElementById('workboard-confirm-backdrop')?.addEventListener('click', closeModal);
    document.getElementById('workboard-confirm-confirm')?.addEventListener('click', confirmPendingForm);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
}

export function initConfirmModal() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindConfirmModal);
    } else {
        bindConfirmModal();
    }
}
