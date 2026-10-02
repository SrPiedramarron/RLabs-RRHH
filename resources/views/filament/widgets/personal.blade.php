<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Personal</x-slot>

        <div style="display:flex;gap:32px;flex-wrap:wrap;margin-bottom:16px;">
            <a href="{{ $urlPersonal }}" style="text-decoration:none;color:inherit;">
                <div style="font-size:12px;opacity:.7;">Trabajadores activos</div>
                <div style="font-size:26px;font-weight:650;">{{ $activos }}</div>
            </a>
            <div>
                <div style="font-size:12px;opacity:.7;">Altas del mes</div>
                <div style="font-size:26px;font-weight:650;">{{ $altas }}</div>
            </div>
            <div>
                <div style="font-size:12px;opacity:.7;">Bajas del mes</div>
                <div style="font-size:26px;font-weight:650;">{{ $bajas }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
            <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:12px 14px;">
                <strong style="font-size:14px;">Cumpleaños (próximos 7 días)</strong>
                @forelse ($cumples as $c)
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:13px;margin-top:6px;">
                        <span>{{ $c['nombre'] }}</span>
                        <span style="opacity:.7;">{{ $c['fecha']->isToday() ? 'Hoy' : $c['fecha']->format('d/m') }}</span>
                    </div>
                @empty
                    <div style="font-size:13px;opacity:.6;margin-top:6px;">Ninguno esta semana.</div>
                @endforelse
            </div>

            <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;padding:12px 14px;">
                <strong style="font-size:14px;">De vacaciones (próximos 7 días)</strong>
                @forelse ($vacaciones as $v)
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:13px;margin-top:6px;">
                        <span>{{ $v['nombre'] }}</span>
                        <span style="opacity:.7;">{{ $v['ahora'] ? 'Hasta el ' . $v['hasta'] : $v['desde'] . ' al ' . $v['hasta'] }}</span>
                    </div>
                @empty
                    <div style="font-size:13px;opacity:.6;margin-top:6px;">Nadie esta semana.</div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
