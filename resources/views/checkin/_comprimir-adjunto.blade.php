{{-- Reduce las fotos grandes del teléfono antes de subirlas (las cámaras actuales dan 3–10 MB y el servidor
     rechazaba el archivo). Los PDF no se tocan; si pasan de 5 MB se avisa. --}}
<div id="adjunto-info" style="font-size:12px; color:var(--gris); margin-bottom:6px;"></div>
@push('scripts')
<script>
(function () {
    const input = document.querySelector('input[name="adjunto"]');
    if (!input) return;
    const info = document.getElementById('adjunto-info');
    const MAX = 5 * 1024 * 1024, mb = n => (n / 1048576).toFixed(1) + ' MB';

    input.addEventListener('change', function () {
        info.textContent = '';
        const f = input.files && input.files[0];
        if (!f) return;

        if (f.type === 'application/pdf') {
            if (f.size > MAX) { input.value = ''; info.textContent = 'El PDF pesa ' + mb(f.size) + ' (máximo 5 MB). Elige uno más liviano.'; }
            return;
        }
        if (!/^image\/(jpeg|png)$/.test(f.type) || f.size <= 1024 * 1024) return; // ya es liviana

        info.textContent = 'Optimizando la foto…';
        const url = URL.createObjectURL(f), img = new Image();
        img.onload = function () {
            const esc = Math.min(1, 1600 / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * esc); c.height = Math.round(img.height * esc);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob(function (blob) {
                if (!blob) { info.textContent = ''; return; }
                const nuevo = new File([blob], (f.name.replace(/\.[^.]+$/, '') || 'foto') + '.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer(); dt.items.add(nuevo); input.files = dt.files;
                info.textContent = 'Foto reducida de ' + mb(f.size) + ' a ' + mb(nuevo.size) + ' para subirla más rápido.';
            }, 'image/jpeg', 0.8);
        };
        img.onerror = function () { URL.revokeObjectURL(url); info.textContent = ''; if (f.size > MAX) { input.value = ''; info.textContent = 'No se pudo reducir la foto; elige una de menos de 5 MB.'; } };
        img.src = url;
    });
})();
</script>
@endpush
