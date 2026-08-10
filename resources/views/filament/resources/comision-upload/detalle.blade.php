<x-filament-panels::page>

	@if($this->getHuerfanas()->count() > 0)
<div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl p-4 mb-6">
    <div class="flex items-start gap-3">
        <span class="text-amber-500 text-xl mt-0.5">?</span>
        <div>
            <p class="font-semibold text-amber-800 dark:text-amber-300">
                {{ $this->getHuerfanas()->count() }} factura(s) cobradas no encontradas en el Excel de comisiones
            </p>
            <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                Estas facturas fueron cobradas en {{ $this->record->mes_nombre }} pero no están en el archivo de comisiones. 
                Agréguelas al Excel y reprocese para incluir su comisión correctamente.
            </p>
            <div class="mt-2 space-y-1">
                @foreach($this->getHuerfanas() as $h)
                    <span class="inline-block bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 text-xs px-2 py-0.5 rounded font-mono">
                        {{ $h->numdoc }} - S/ {{ number_format($h->base_comision_cobrada, 2) }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

    {{-- ── Tarjetas resumen por vendedor ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getResumenVendedores() as $v)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">

                {{-- Nombre vendedor --}}
                <div class="flex items-center justify-between mb-3">
                    <span class="font-semibold text-gray-800 dark:text-gray-100 text-sm truncate">
                        {{ $v->vendedor }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $v->total }} fact.</span>
                </div>

                {{-- Chips de estado --}}
                <div class="flex gap-2 mb-4 text-xs">
                    <span class="bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-2 py-0.5 rounded-full">
                        ✓ {{ $v->cobradas }} cobradas
                    </span>
                    <span class="bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded-full">
                        ⏳ {{ $v->pendientes }} pend.
                    </span>
                </div>

                {{-- Montos --}}
                <div class="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-1">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Base cobrada:</span>
                        <span>S/ {{ number_format($v->base_cobrada, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-green-600 dark:text-green-400">
                        <span>Comisión (1.5%):</span>
                        <span>S/ {{ number_format($v->comision, 2) }}</span>
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    {{-- ── Totales generales ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $this->record->total_facturas }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Total facturas</div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->record->total_cobradas }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Cobradas</div>
        </div>
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->record->total_pendientes }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Pendientes</div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                S/ {{ number_format($this->record->total_comision, 2) }}
            </div>
            <div class="text-xs text-gray-400 mt-0.5">Comisión total</div>
        </div>
    </div>

    {{-- ── Tabla detalle ────────────────────────────────────────────────────── --}}
    {{ $this->table }}

</x-filament-panels::page>
