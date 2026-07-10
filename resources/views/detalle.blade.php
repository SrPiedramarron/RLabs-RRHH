<x-filament-panels::page>

<div class="max-w-3xl mx-auto space-y-4">

    {{-- Encabezado empleado --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $this->record->apellidos }}, {{ $this->record->nombres }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">DNI: {{ $this->record->dni }} &mdash; {{ $this->record->cargo ?? 'Sin cargo' }}</p>
            </div>
            <span class="text-sm font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full">
                {{ $this->record->mes_nombre }}
            </span>
        </div>
        <div class="mt-3 flex gap-3 text-xs">
            <span class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded">
                {{ $this->record->sistema_pensiones_label }}
            </span>
            @if($this->record->aplica_5ta_categoria)
            <span class="bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded">
                5ta categoria
            </span>
            @endif
        </div>
    </div>

    {{-- Asistencia --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 uppercase tracking-wide">Asistencia</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $this->record->dias_laborables }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Dias laborables</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->record->dias_trabajados }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Dias trabajados</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $this->record->dias_falta > 0 ? 'text-red-500' : 'text-gray-400' }}">
                    {{ $this->record->dias_falta }}
                </div>
                <div class="text-xs text-gray-400 mt-0.5">Faltas</div>
            </div>
            <div>
                <div class="text-2xl font-bold {{ $this->record->total_minutos_tarde > 0 ? 'text-amber-500' : 'text-gray-400' }}">
                    {{ $this->record->total_minutos_tarde }}
                </div>
                <div class="text-xs text-gray-400 mt-0.5">Min. tardanza</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($this->record->horas_extra_diurnas, 1) }}</div>
                <div class="text-xs text-gray-400 mt-0.5">H.E. diurnas (25%)</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($this->record->horas_extra_nocturnas, 1) }}</div>
                <div class="text-xs text-gray-400 mt-0.5">H.E. nocturnas (35%)</div>
            </div>
        </div>
    </div>

    {{-- Calculo --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 uppercase tracking-wide">Calculo</h3>

        {{-- Ingresos --}}
        <div class="space-y-2 mb-4">
            <p class="text-xs font-semibold text-gray-400 uppercase">Ingresos</p>

            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Sueldo base</span>
                <span>S/ {{ number_format($this->record->sueldo_base, 2) }}</span>
            </div>

            @if($this->record->dias_falta > 0)
            <div class="flex justify-between text-sm text-red-500">
                <span>Descuento faltas ({{ $this->record->dias_falta }} dia/s)</span>
                <span>- S/ {{ number_format($this->record->descuento_faltas, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm border-t border-dashed border-gray-200 dark:border-gray-700 pt-2">
                <span class="text-gray-600 dark:text-gray-400">Sueldo proporcional</span>
                <span>S/ {{ number_format($this->record->sueldo_proporcional, 2) }}</span>
            </div>
            @endif

            @if($this->record->importe_horas_extra_diurnas > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">H.E. diurnas ({{ number_format($this->record->horas_extra_diurnas, 1) }}h x 25%)</span>
                <span class="text-blue-600">+ S/ {{ number_format($this->record->importe_horas_extra_diurnas, 2) }}</span>
            </div>
            @endif

            @if($this->record->importe_horas_extra_nocturnas > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">H.E. nocturnas ({{ number_format($this->record->horas_extra_nocturnas, 1) }}h x 35%)</span>
                <span class="text-purple-600">+ S/ {{ number_format($this->record->importe_horas_extra_nocturnas, 2) }}</span>
            </div>
            @endif

            @if($this->record->comisiones > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Comisiones</span>
                <span class="text-green-600">+ S/ {{ number_format($this->record->comisiones, 2) }}</span>
            </div>
            @endif

            @if($this->record->bonos_especiales > 0)
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Bono especial</span>
                <span class="text-green-600">+ S/ {{ number_format($this->record->bonos_especiales, 2) }}</span>
            </div>
            @endif

            @if($this->record->descuento_tardanzas > 0)
            <div class="flex justify-between text-sm text-amber-600">
                <span>Descuento tardanzas ({{ $this->record->total_minutos_tarde }} min)</span>
                <span>- S/ {{ number_format($this->record->descuento_tardanzas, 2) }}</span>
            </div>
            @endif
        </div>

        {{-- Bruto --}}
        <div class="flex justify-between text-sm font-semibold border-t border-gray-200 dark:border-gray-700 pt-3 mb-4">
            <span>Remuneracion bruta</span>
            <span>S/ {{ number_format($this->record->remuneracion_bruta, 2) }}</span>
        </div>

        {{-- Descuentos ley --}}
        <div class="space-y-2 mb-4">
            <p class="text-xs font-semibold text-gray-400 uppercase">Descuentos de ley</p>

            <div class="flex justify-between text-sm text-red-500">
                <span>{{ $this->record->sistema_pensiones_label }} ({{ number_format($this->record->porcentaje_pension * 100, 2) }}%)</span>
                <span>- S/ {{ number_format($this->record->descuento_pension, 2) }}</span>
            </div>

            @if($this->record->descuento_5ta_categoria > 0)
            <div class="flex justify-between text-sm text-red-500">
                <span>Renta 5ta categoria</span>
                <span>- S/ {{ number_format($this->record->descuento_5ta_categoria, 2) }}</span>
            </div>
            @endif
        </div>

        {{-- Neto --}}
        <div class="flex justify-between text-base font-bold border-t-2 border-gray-300 dark:border-gray-600 pt-3 text-green-600 dark:text-green-400">
            <span>NETO A PAGAR</span>
            <span>S/ {{ number_format($this->record->neto_pagar, 2) }}</span>
        </div>
    </div>

    {{-- Footer --}}
    <p class="text-xs text-center text-gray-400">
        Calculado el {{ $this->record->calculado_at?->format('d/m/Y H:i') ?? '—' }}
    </p>

</div>

</x-filament-panels::page>
