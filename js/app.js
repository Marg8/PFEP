/**
 * PFEP – photo picker, preview and lightbox helpers
 */

// Camera button → opens device camera directly
document.querySelectorAll('.btn-camera').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(btn.dataset.target);
        if (!input) return;
        input.setAttribute('capture', 'environment');
        input.click();
    });
});

// Gallery button → opens file picker / photo library
document.querySelectorAll('.btn-gallery').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(btn.dataset.target);
        if (!input) return;
        input.removeAttribute('capture');
        input.click();
    });
});

// Live photo preview when a file is selected
document.querySelectorAll('input[type="file"].foto-input').forEach(function(input) {
    input.addEventListener('change', function() {
        var previewId = input.dataset.preview;
        var preview   = previewId ? document.getElementById(previewId) : null;
        if (!preview) return;

        if (input.files && input.files[0]) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Vista previa">';
            };
            reader.readAsDataURL(file);

            // Show filename under buttons
            var picker  = input.closest('.photo-picker');
            if (picker) {
                var fn = picker.querySelector('.photo-filename');
                if (!fn) {
                    fn = document.createElement('div');
                    fn.className = 'photo-filename';
                    picker.appendChild(fn);
                }
                fn.textContent = '✅ ' + file.name;
            }
        } else {
            preview.innerHTML = '';
        }
    });
});


// Lightbox
var lightbox = document.getElementById('lightbox');
if (lightbox) {
    var lbImg = lightbox.querySelector('img');

    document.querySelectorAll('img.thumb').forEach(function(img) {
        img.addEventListener('click', function() {
            lbImg.src = img.dataset.full || img.src;
            lightbox.classList.add('active');
        });
    });

    lightbox.querySelector('.close').addEventListener('click', function() {
        lightbox.classList.remove('active');
    });
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) lightbox.classList.remove('active');
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') lightbox.classList.remove('active');
    });
}

// Confirm delete
document.querySelectorAll('a.btn-delete').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (!confirm('¿Eliminar este componente? Esta acción no se puede deshacer.')) {
            e.preventDefault();
        }
    });
});
