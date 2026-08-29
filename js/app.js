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

// Dynamic part-number search / filter (dashboard)
document.querySelectorAll('input[data-target]').forEach(function(input) {
    var table = document.getElementById(input.dataset.target);
    if (!table) return;
    var counter = document.getElementById('search-count');

    function applyFilter() {
        var term = input.value.trim().toLowerCase();
        var rows = table.querySelectorAll('tbody tr[data-part]');
        var shown = 0;
        rows.forEach(function(row) {
            var match = row.dataset.part.indexOf(term) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        if (counter) {
            counter.textContent = term === ''
                ? rows.length + ' partes'
                : shown + ' de ' + rows.length + ' partes';
        }
    }

    input.addEventListener('input', applyFilter);
    applyFilter();
});

// Inline demand editing (demanda.php)
document.querySelectorAll('.btn-save-demanda').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var row    = btn.closest('tr.demanda-row');
        if (!row) return;
        var status = row.querySelector('.save-status');
        var part   = row.dataset.partNumber;

        var payload = new URLSearchParams();
        payload.append('part_number', part);
        row.querySelectorAll('.cell-input').forEach(function(inp) {
            payload.append(inp.dataset.field, inp.value === '' ? '0' : inp.value);
        });

        btn.disabled = true;
        if (status) { status.textContent = '⏳'; status.className = 'save-status'; }

        fetch('demanda_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
        .then(function(res) { return res.json().then(function(j) { return { ok: res.ok, body: j }; }); })
        .then(function(r) {
            btn.disabled = false;
            if (r.ok && r.body.ok) {
                if (status) { status.textContent = '✅ Guardado'; status.className = 'save-status ok'; }
                // Highlight zero-demand rows dynamically
                var demand = parseInt(r.body.daily_demand, 10) || 0;
                row.classList.toggle('row-pending', demand === 0);
                var updated = row.querySelector('.cell-updated');
                if (updated) updated.textContent = new Date().toLocaleString();
            } else {
                if (status) { status.textContent = '❌ ' + (r.body.error || 'Error'); status.className = 'save-status err'; }
            }
        })
        .catch(function() {
            btn.disabled = false;
            if (status) { status.textContent = '❌ Error de red'; status.className = 'save-status err'; }
        });
    });
});
