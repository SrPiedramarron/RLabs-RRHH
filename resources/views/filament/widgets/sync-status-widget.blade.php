<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Estado de Sincronización de Relojes</x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($this->getLocations() as $location)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="font-semibold text-sm">{{ $location->nombre }}</p>
                        <p class="text-xs text-gray-500">{{ $location->company->razon_social }}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                        {{ $location->sync_estado === 'ok' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $location->sync_estado === 'error' ? 'bg-red-100 text-red-700' : '' }}
                        {{ $location->sync_estado === 'pendiente' ? 'bg-yellow-100 text-yellow-700' : '' }}
                    ">
                        {{ match($location->sync_estado) {
                            'ok'        => '✅ OK',
                            'error'     => '❌ Error',
                            'pendiente' => '⏳ Pendiente',
                            default     => '—'
                        } }}
                    </span>
                </div>

                <div class="text-xs text-gray-500 mb-3">
                    <p>IP: {{ $location->reloj_ip ?? '—' }}:{{ $location->reloj_puerto }}</p>
                    <p>Última sync: {{ $location->ultima_sync?->diffForHumans() ?? 'Nunca' }}</p>
                    @if($location->sync_error_msg)
                        <p class="text-red-500 mt-1 truncate">{{ $location->sync_error_msg }}</p>
                    @endif
                </div>

                <button
                    wire:click="sincronizar({{ $location->id }})"
                    class="w-full text-xs bg-primary-600 hover:bg-primary-700 text-white py-1.5 px-3 rounded-md transition"
                >
                    🔄 Sincronizar Ahora
                </button>
            </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>