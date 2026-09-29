function loadRowPreviewImage(row) {
    const url = row.dataset.attachmentPreviewUrl;

    if (!url) {
        return;
    }

    const image = row.querySelector('[data-attachment-preview-img]');

    if (image && !image.getAttribute('src')) {
        image.src = url;
        image.alt = row.dataset.attachmentModalFilename ?? 'Attachment preview';
    }
}

function bindAttachmentRowPreviews() {
    document.querySelectorAll('[data-attachment-row][data-attachment-preview-image]').forEach((row) => {
        row.addEventListener('mouseenter', () => loadRowPreviewImage(row), { once: true });
        row.addEventListener('focusin', () => loadRowPreviewImage(row), { once: true });
    });
}

function bindAttachmentImageModal() {
    const modal = document.getElementById('attachment-image-modal');
    const backdrop = document.getElementById('attachment-image-modal-backdrop');
    const closeButton = document.getElementById('attachment-image-modal-close');
    const image = document.getElementById('attachment-image-modal-img');
    const title = document.getElementById('attachment-image-modal-title');

    if (!modal || !image || !title) {
        return;
    }

    let activeTrigger = null;

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        image.removeAttribute('src');
        image.alt = '';
        title.textContent = '';
        activeTrigger = null;
    };

    const openModal = (trigger) => {
        const url = trigger.dataset.attachmentPreviewUrl;
        const filename = trigger.dataset.attachmentModalFilename ?? 'Attachment';

        if (!url) {
            return;
        }

        activeTrigger = trigger;
        title.textContent = filename;
        image.src = url;
        image.alt = filename;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        closeButton?.focus();
    };

    document.querySelectorAll('[data-attachment-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            openModal(trigger);
        });
    });

    closeButton?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
}

export function initAttachmentPreview() {
    bindAttachmentRowPreviews();
    bindAttachmentImageModal();
}
