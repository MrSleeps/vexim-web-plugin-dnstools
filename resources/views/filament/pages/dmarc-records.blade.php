{{-- resources/views/filament/pages/dmarc-records.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                DMARC Record Configuration
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                Configure Domain-based Message Authentication, Reporting, and Conformance (DMARC) settings.
                This page shows how your current settings would appear as a DNS TXT record.
            </p>
        </div>

        {{-- Current Settings --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Policy Settings --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Policy Settings
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Policy</span>
                        <span class="text-sm font-mono px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">
                            {{ $this->settings['dmarc_policy'] ?? 'none' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Subdomain Policy</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_subdomain_policy'] ?? 'none' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">DKIM Alignment</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_adkim'] ?? 'relaxed' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">SPF Alignment</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_aspf'] ?? 'relaxed' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Percentage</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_percentage'] ?? 100 }}%
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Testing Mode</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ isset($this->settings['dmarc_t']) && $this->settings['dmarc_t'] === 'y' ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Report Interval</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_report_interval'] ?? 86400 }} seconds
                        </span>
                    </div>
                </div>
            </div>

            {{-- Reporting Settings --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Reporting Settings
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400 block mb-2">Aggregate Reports (RUA)</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($this->getRuaDestinations() as $destination)
                                <span class="text-sm font-mono px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                                    {{ $destination }}
                                </span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">No destinations configured</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400 block mb-2">Forensic Reports (RUF)</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($this->getRufDestinations() as $destination)
                                <span class="text-sm font-mono px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded">
                                    {{ $destination }}
                                </span>
                            @empty
                                <span class="text-sm text-gray-500 dark:text-gray-400">No destinations configured</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Reporting Options (FO)</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ implode(', ', $this->settings['dmarc_reporting'] ?? ['all']) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RUA Local Part</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_rua_localpart'] ?? 'dmarc' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">RUF Local Part</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_ruf_localpart'] ?? 'dmarc' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Advanced Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Advanced Settings
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Non-existent Subdomain Policy (NP)</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_np'] ?? 'Not set' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Public Suffix Domain Policy (PSD)</span>
                        <span class="text-sm font-mono px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded">
                            {{ $this->settings['dmarc_psd'] ?? 'Not set' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- DMARC Record Preview --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border-2 border-blue-500 dark:border-blue-600">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20">
                <h3 class="text-lg font-medium text-blue-900 dark:text-blue-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    DMARC DNS Record Preview
                </h3>
            </div>
            <div class="p-6">
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="space-y-2">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Record Type:</span>
                            <span class="text-sm font-mono px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">TXT</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Name:</span>
                            <span class="text-sm font-mono px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded break-all">_dmarc.yourdomain.com</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Value:</span>
                            <div class="text-sm font-mono bg-gray-100 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600 overflow-x-auto">
                                <code class="text-gray-800 dark:text-gray-200 break-all">
                                    {{ $this->getDmarcRecord($this->settings, 'yourdomain.com') }}
                                </code>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Explanation --}}
                <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <h4 class="font-medium text-blue-900 dark:text-blue-200 mb-2">Record Components:</h4>
                    <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1 list-disc list-inside">
                        <li><strong>v=DMARC1</strong> - Version identifier</li>
                        <li><strong>p=</strong> - Policy ({{ $this->settings['dmarc_policy'] ?? 'none' }})</li>
                        @if(!empty($this->settings['dmarc_rua'] ?? []))
                            <li><strong>rua=</strong> - Aggregate report destinations</li>
                        @endif
                        @if(!empty($this->settings['dmarc_ruf'] ?? []))
                            <li><strong>ruf=</strong> - Forensic report destinations</li>
                        @endif
                        @if(isset($this->settings['dmarc_percentage']) && $this->settings['dmarc_percentage'] != 100)
                            <li><strong>pct=</strong> - Percentage of messages to apply policy</li>
                        @endif
                        <li><strong>adkim=</strong> - DKIM alignment ({{ $this->settings['dmarc_adkim'] ?? 'relaxed' }})</li>
                        <li><strong>aspf=</strong> - SPF alignment ({{ $this->settings['dmarc_aspf'] ?? 'relaxed' }})</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap gap-3 justify-end">
            <a href="{{ route('filament.admin.resources.settings.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors">
                Manage Settings
            </a>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $this->getDmarcRecord($this->settings, 'yourdomain.com') }}')"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                </svg>
                Copy DNS Record
            </button>
        </div>
    </div>
</x-filament-panels::page>