/**
 * PFEP – photo preview and lightbox helpers
 */

// Live photo preview when a file is selected
document.querySelectorAll('input[type="file"].foto-input').forEach(function(input) {
    input.addEventListener('change', function() {
        var previewId = input.dataset.preview;
        var preview   = previewId ? document.getElementById(previewId) : null;
        if (!preview) return;

        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Vista previa">';
            };
            reader.readAsDataURL(input.files[0]);
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
