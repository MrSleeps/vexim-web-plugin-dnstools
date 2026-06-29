<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                Generate DMARC Record for {{ $record->domain }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Configure your DMARC policy below. The generated record will be saved and can be viewed in the DMARC management page.
            </p>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
