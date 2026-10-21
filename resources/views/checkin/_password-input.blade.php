{{-- Campo de contraseña con ojo para mostrar/ocultar lo escrito.
     Uso: @include('checkin._password-input', ['id' => 'password', 'name' => 'password', 'placeholder' => '••••••', 'extra' => 'minlength="6"']) --}}
<div style="position:relative;">
    <input type="password" id="{{ $id }}" name="{{ $name }}" class="form-input"
           placeholder="{{ $placeholder ?? '' }}" style="padding-right:48px;" {!! $extra ?? '' !!} required>
    <button type="button" class="ojo-password" data-target="{{ $id }}" aria-label="Mostrar contraseña"
            style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:0; padding:8px; cursor:pointer; color:#6B7280; line-height:0;">
        <svg class="ojo-abierto" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <svg class="ojo-cerrado" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
    </button>
</div>
@once
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.ojo-password');
    if (!b) return;
    var input = document.getElementById(b.dataset.target);
    var ver = input.type === 'password';
    input.type = ver ? 'text' : 'password';
    b.querySelector('.ojo-abierto').style.display = ver ? 'none' : '';
    b.querySelector('.ojo-cerrado').style.display = ver ? '' : 'none';
    b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
});
</script>
@endonce
