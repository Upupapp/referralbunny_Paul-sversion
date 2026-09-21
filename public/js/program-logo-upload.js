(() => {
    const form = document.querySelector('[data-program-logo-upload]');
    if (!form) return;
    const input = form.querySelector('input[type=file]');
    const preview = form.querySelector('[data-logo-preview]');
    const status = form.querySelector('[data-logo-status]');
    const save = form.querySelector('button[type=submit]');
    const editor = form.querySelector('[data-logo-editor]');
    const crop = form.querySelector('[data-logo-crop]');
    const zoom = form.querySelector('[data-logo-zoom]');
    const horizontal = form.querySelector('[data-logo-x]');
    const vertical = form.querySelector('[data-logo-y]');
    let original;
    let bitmap;
    let generation = 0;
    let previewUrl;
    const size = bytes => `${(bytes / 1024).toFixed(1)} KB`;
    const encode = (canvas, type, quality) => new Promise((resolve, reject) => {
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Could not prepare this image.')), type, quality);
    });
    const reset = () => { zoom.value = '1'; horizontal.value = vertical.value = '50'; };
    async function render() {
        const version = ++generation;
        save.disabled = true;
        for (const control of [zoom, horizontal, vertical]) control.disabled = !crop.checked;
        status.textContent = 'Preparing your logo…';
        try {
            const file = original;
            const scale = Math.min(1, 512 / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            if (crop.checked) {
                const side = Math.min(bitmap.width, bitmap.height) / Number(zoom.value);
                canvas.width = canvas.height = Math.max(1, Math.min(512, Math.round(side)));
                const context = canvas.getContext('2d');
                context.imageSmoothingEnabled = true;
                context.imageSmoothingQuality = 'high';
                context.drawImage(bitmap,
                    (bitmap.width - side) * Number(horizontal.value) / 100,
                    (bitmap.height - side) * Number(vertical.value) / 100,
                    side, side, 0, 0, canvas.width, canvas.height);
            } else {
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                const context = canvas.getContext('2d');
                context.imageSmoothingEnabled = true;
                context.imageSmoothingQuality = 'high';
                context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            }
            // Each edit starts from the original, never from an already-compressed crop.
            const candidates = await Promise.all([encode(canvas, 'image/webp', .92), encode(canvas, 'image/png')]);
            if (!crop.checked && scale === 1) candidates.push(file);
            const output = candidates.reduce((a, b) => a.size <= b.size ? a : b);
            if (output.size > 2 * 1024 * 1024) throw new Error('This logo is still too large. Please choose a simpler image.');
            if (version !== generation) return;
            const extension = output.type === 'image/webp' ? 'webp' : output.type === 'image/jpeg' ? 'jpg' : 'png';
            const transfer = new DataTransfer();
            transfer.items.add(new File([output], `program-logo.${extension}`, { type: output.type }));
            input.files = transfer.files;
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(output);
            preview.src = previewUrl;
            preview.hidden = false;
            const reduction = Math.max(0, Math.round((1 - output.size / file.size) * 100));
            status.textContent = `${size(file.size)} → ${size(output.size)}${reduction ? ` · ${reduction}% smaller` : ' · Already compact'} · ${canvas.width} × ${canvas.height} pixels. Preview ready; save to apply.`;
            save.disabled = false;
        } catch (error) {
            if (version !== generation) return;
            status.textContent = error.message || 'Could not prepare this image. Choose another file.';
            input.setCustomValidity(status.textContent);
        }
    }
    input.addEventListener('change', async () => {
        const version = ++generation;
        const file = input.files[0];
        bitmap?.close();
        bitmap = null;
        editor.hidden = preview.hidden = true;
        save.disabled = true;
        input.setCustomValidity('');
        status.textContent = '';
        if (!file) return;
        try {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) throw new Error('Choose a PNG, JPG or WebP image.');
            if (file.size > 10 * 1024 * 1024) throw new Error('Choose an image smaller than 10 MB.');
            const decoded = await createImageBitmap(file, { imageOrientation: 'from-image' });
            if (version !== generation) { decoded.close(); return; }
            if (decoded.width * decoded.height > 24000000) { decoded.close(); throw new Error('Choose an image with no more than 24 megapixels.'); }
            bitmap = decoded;
            original = file;
            crop.checked = false;
            reset();
            editor.hidden = false;
            await render();
        } catch (error) {
            if (version !== generation) return;
            input.value = '';
            status.textContent = error.message || 'Could not prepare this image.';
            input.setCustomValidity(status.textContent);
        }
    });
    for (const control of [crop, zoom, horizontal, vertical]) {
        control.addEventListener('input', () => { if (bitmap) { input.setCustomValidity(''); render(); } });
    }
    form.querySelector('[data-logo-reset]').addEventListener('click', () => {
        if (!bitmap) return;
        reset(); crop.checked = false; input.setCustomValidity(''); render();
    });
    form.addEventListener('submit', event => {
        if (save.disabled) event.preventDefault();
    });
})();
