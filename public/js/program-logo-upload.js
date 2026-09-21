(() => {
    const form = document.querySelector('[data-program-logo-upload]');
    if (!form) return;
    const input = form.querySelector('input[type=file]');
    const preview = form.querySelector('[data-logo-preview]');
    const status = form.querySelector('[data-logo-status]');
    const save = form.querySelector('button[type=submit]');
    let generation = 0;
    let previewUrl;
    const size = bytes => `${(bytes / 1024).toFixed(1)} KB`;
    const encode = (canvas, type, quality) => new Promise((resolve, reject) => {
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Could not prepare this image.')), type, quality);
    });
    input.addEventListener('change', async () => {
        const version = ++generation;
        const file = input.files[0];
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.hidden = true;
        save.disabled = true;
        input.setCustomValidity('');
        status.textContent = '';
        if (!file) return;
        status.textContent = 'Preparing your logo…';
        let bitmap;
        try {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) throw new Error('Choose a PNG, JPG or WebP image.');
            if (file.size > 10 * 1024 * 1024) throw new Error('Choose an image smaller than 10 MB.');
            bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            if (version !== generation) return;
            if (bitmap.width * bitmap.height > 24000000) throw new Error('Choose an image with no more than 24 megapixels.');
            const scale = Math.min(1, 512 / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            const context = canvas.getContext('2d');
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            // Keep transparency and choose the smaller of high-quality WebP and lossless PNG.
            const candidates = await Promise.all([encode(canvas, 'image/webp', .92), encode(canvas, 'image/png')]);
            if (scale === 1) candidates.push(file);
            const output = candidates.reduce((a, b) => a.size <= b.size ? a : b);
            if (output.size > 2 * 1024 * 1024) throw new Error('This logo is still too large. Please choose a simpler image.');
            if (version !== generation) return;
            const extension = output.type === 'image/webp' ? 'webp' : output.type === 'image/jpeg' ? 'jpg' : 'png';
            const transfer = new DataTransfer();
            transfer.items.add(new File([output], `program-logo.${extension}`, { type: output.type }));
            input.files = transfer.files;
            previewUrl = URL.createObjectURL(output);
            preview.src = previewUrl;
            preview.hidden = false;
            const reduction = Math.max(0, Math.round((1 - output.size / file.size) * 100));
            status.textContent = `${size(file.size)} → ${size(output.size)}${reduction ? ` · ${reduction}% smaller` : ' · Already compact'} · ${canvas.width} × ${canvas.height} pixels. Preview ready; save to apply.`;
            save.disabled = false;
        } catch (error) {
            if (version !== generation) return;
            input.value = '';
            status.textContent = error.message || 'Could not prepare this image. Choose another file.';
            input.setCustomValidity(status.textContent);
        } finally {
            bitmap?.close();
        }
    });
    form.addEventListener('submit', event => {
        if (save.disabled) event.preventDefault();
    });
})();
