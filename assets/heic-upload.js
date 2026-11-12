/* Konversi HEIC/HEIF → JPEG di browser sebelum upload.
   Server (PHP GD) tidak bisa membaca HEIC, jadi file dikonversi dulu di sini,
   lalu JPEG hasil konversi dikompres oleh imgCompress() di server seperti biasa. */
(function () {
    'use strict';

    function isHeic(file) {
        if (!file || !file.name) return false;
        var ext = file.name.toLowerCase().split('.').pop();
        if (ext === 'heic' || ext === 'heif') return true;
        var t = (file.type || '').toLowerCase();
        return t === 'image/heic' || t === 'image/heif'
            || t === 'image/heic-sequence' || t === 'image/heif-sequence';
    }

    // HEIC → Blob JPEG (kualitas 0.92), EXIF orientation dipakai ulang agar tidak terbalik
    function heicToJpeg(file) {
        return heic2any({ blob: file, toType: 'image/jpeg', quality: 0.92 })
            .then(function (result) {
                // heic2any bisa mengembalikan array (multi-frame) — pakai frame pertama
                return (Array.isArray(result) ? result[0] : result);
            })
            .then(function (blob) {
                if (!window.createImageBitmap || !window.HTMLCanvasElement) return blob;
                return createImageBitmap(blob, { imageOrientation: 'from-image' })
                    .then(function (bmp) {
                        var canvas = document.createElement('canvas');
                        canvas.width = bmp.width;
                        canvas.height = bmp.height;
                        var ctx = canvas.getContext('2d');
                        ctx.drawImage(bmp, 0, 0);
                        bmp.close && bmp.close();
                        return new Promise(function (resolve) {
                            canvas.toBlob(function (out) {
                                resolve(out || blob);
                            }, 'image/jpeg', 0.92);
                        });
                    })
                    .catch(function () { return blob; }); // gagal rotate → pakai blob apa adanya
            });
    }

    // Ganti file HEIC pada satu input[type=file] dengan hasil konversi JPEG
    function convertInput(input) {
        var file = input.files && input.files[0];
        if (!isHeic(file)) return Promise.resolve(false);
        if (typeof heic2any !== 'function') {
            return Promise.reject(new Error('Library heic2any tidak termuat.'));
        }
        return heicToJpeg(file).then(function (blob) {
            if (!blob) throw new Error('Konversi HEIC gagal.');
            var base = file.name.replace(/\.(heic|heif)$/i, '');
            var renamed = new File([blob], base + '.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
            var dt = new DataTransfer();
            dt.items.add(renamed);
            input.files = dt.files;
            return true;
        });
    }

    // Konversi semua input HEIC di dalam form → true bila ada yang dikonversi
    function convertHeicInForm(form) {
        var inputs = form.querySelectorAll('input[type="file"]');
        var jobs = [];
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].files && inputs[i].files.length) {
                jobs.push(convertInput(inputs[i]));
            }
        }
        return Promise.all(jobs).then(function (results) {
            return results.some(function (r) { return r; });
        });
    }

    // Baca file (setelah konversi bila HEIC) sebagai data URL untuk preview
    function readAsDataURL(file) {
        var p = isHeic(file)
            ? heicToJpeg(file).then(function (blob) { return blob || file; })
            : Promise.resolve(file);
        return p.then(function (f) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onload = function () { resolve(reader.result); };
                reader.onerror = function () { reject(reader.error); };
                reader.readAsDataURL(f);
            });
        });
    }

    // Pasang auto-konversi pada submit form (preventDefault → convert → submit ulang)
    function bindFormSubmit(form, onDone) {
        form.addEventListener('submit', function (ev) {
            var hasHeic = false;
            var inputs = form.querySelectorAll('input[type="file"]');
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].files && inputs[i].files.length && isHeic(inputs[i].files[0])) {
                    hasHeic = true;
                    break;
                }
            }
            if (!hasHeic) return; // tidak ada HEIC → submit normal
            ev.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            var oldLabel = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Memproses foto…'; }
            convertHeicInForm(form).then(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = oldLabel; }
                if (typeof onDone === 'function') onDone();
                form.submit(); // submit ulang tanpa memicu listener (bukan dispatchEvent)
            }).catch(function (err) {
                if (btn) { btn.disabled = false; btn.innerHTML = oldLabel; }
                alert((err && err.message ? err.message : 'Gagal memproses file HEIC.')
                    + '\nCoba simpan ulang, atau ubah foto ke format JPG dulu.');
            });
        });
    }

    window.HeicUpload = {
        isHeic: isHeic,
        convertInput: convertInput,
        convertHeicInForm: convertHeicInForm,
        readAsDataURL: readAsDataURL,
        bindFormSubmit: bindFormSubmit
    };
})();
