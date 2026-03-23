<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 max-w-2xl">
        @foreach($this->companies as $company)
            <button
                wire:click="cambiar({{ $company->id }})"
                class="text-left bg-white dark:bg-gray-800 rounded-2xl shadow-sm border-2 transition-all p-6 group
                    {{ $this->company_id == $company->id
                        ? 'border-primary-500 ring-2 ring-primary-200'
                        : 'border-transparent hover:border-primary-400 hover:shadow-md' }}"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center transition-colors
                        {{ $this->company_id == $company->id ? 'bg-primary-100' : 'bg-gray-100 group-hover:bg-primary-50' }}">
                        <x-heroicon-o-building-office-2 class="w-6 h-6
                            {{ $this->company_id == $company->id ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-500' }}" />
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $company->razon_social }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">RUC {{ $company->ruc }}</p>
                        @if($this->company_id == $company->id)
                            <span class="text-xs text-primary-600 font-medium">✓ Activa</span>
                        @endif
                    </div>
                </div>
            </button>
        @endforeach
    </div>
</x-filament-panels::page>
