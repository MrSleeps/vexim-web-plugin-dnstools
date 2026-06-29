<x-filament-panels::page>
    @if($dmarc && $dmarc->valid)
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain</h4>
                    <p class="text-xl font-semibold mt-1">{{ $record->domain }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Policy</h4>
                    <p class="text-xl font-semibold mt-1">
                        <span class="px-2 py-1 rounded text-sm font-medium bg-{{ $dmarc->getPolicyColor() }}-100 text-{{ $dmarc->getPolicyColor() }}-800">
                            {{ $dmarc->getPolicyLabel() }}
                        </span>
                        <span class="text-sm text-gray-500 ml-2">({{ $dmarc->policy }})</span>
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Checked</h4>
                    <p class="text-xl font-semibold mt-1">{{ $dmarc->last_checked_at?->diffForHumans() ?? 'Never' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">DKIM Alignment</h4>
                    <p class="text-lg font-semibold mt-1">{{ ucfirst($dmarc->adkim ?? 'Not set') }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">SPF Alignment</h4>
                    <p class="text-lg font-semibold mt-1">{{ ucfirst($dmarc->aspf ?? 'Not set') }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Enforcement Percentage</h4>
                    <p class="text-lg font-semibold mt-1">{{ $dmarc->percentage ?? 100 }}%</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Testing Mode</h4>
                    <p class="text-lg font-semibold mt-1">{{ $dmarc->t === 'y' ? '✅ On' : '❌ Off' }}</p>
                </div>
            </div>

            @if($dmarc->rua || $dmarc->ruf)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Reporting Addresses</h4>
                    <div class="space-y-2">
                        @if($dmarc->rua)
                            <p class="text-sm"><span class="font-medium">RUA:</span> {{ implode(', ', $dmarc->rua) }}</p>
                        @endif
                        @if($dmarc->ruf)
                            <p class="text-sm"><span class="font-medium">RUF:</span> {{ implode(', ', $dmarc->ruf) }}</p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">DNS Record</h4>
                <div class="p-4 bg-gray-100 dark:bg-gray-900 rounded font-mono text-sm overflow-x-auto">
                    <code>v=DMARC1; {{ $dmarc->record }}</code>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    <p>Next check: {{ $dmarc->next_check_at?->diffForHumans() ?? 'Not scheduled' }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <div class="mb-4 text-4xl">📡</div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">No DMARC Record Found</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Domain: <span class="font-mono">{{ $record->domain }}</span>
            </p>
            @if($dmarc?->error_message)
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $dmarc->error_message }}</p>
            @endif
            <div class="mt-6">
                <a href="{{ \VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource::getUrl('generate', ['record' => $record]) }}" 
                   class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg">
                    Generate DMARC Record
                </a>
            </div>
        </div>
    @endif
</x-filament-panels::page>
