<div class="space-y-6 text-foreground">

    {{-- Header --}}
    <div class="flex items-start justify-between">

        <div>
            <h3 class="text-lg font-semibold text-foreground">
                {{ $domain }}
            </h3>

            @if($mtaSts && $mtaSts->dns_valid && $mtaSts->policy_valid)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400">
                    Valid
                </span>
            @elseif($mtaSts && ($mtaSts->dns_valid === 0 || $mtaSts->policy_valid === 0))
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400">
                    Invalid
                </span>
            @elseif($mtaSts)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-500/10 text-yellow-600 dark:text-yellow-400">
                    Partial
                </span>
            @else
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                    Not Checked
                </span>
            @endif
        </div>

        @if($mtaSts && $mtaSts->checked_at)
            <div class="text-xs text-muted-foreground text-right">
                Last checked<br>
                <span class="text-foreground">
                    {{ \Carbon\Carbon::parse($mtaSts->checked_at)->format('Y-m-d H:i:s') }}
                </span>
            </div>
        @endif
    </div>

    @if($mtaSts)

        {{-- Error --}}
        @if($mtaSts->error_message)
            <div class="p-4 rounded-lg border border-red-500/20 bg-red-500/5">
                <div class="text-sm font-medium text-red-500">
                    Error
                </div>
                <div class="mt-1 text-sm text-red-400">
                    {{ $mtaSts->error_message }}
                </div>
            </div>
        @endif

        {{-- Record --}}
        @if($mtaSts->dns_policy)
            <div>
                <div class="text-xs text-muted-foreground uppercase">
                    MTA-STS DNS Record
                </div>

                <div class="mt-1 p-3 rounded-lg border border-border bg-card">
                    <code class="text-sm text-foreground break-all">
                        {{ $mtaSts->dns_policy }}
                    </code>
                </div>
            </div>
        @endif

        {{-- Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">Mode</div>
                <div class="mt-1 text-sm text-foreground">
                    {{ ucfirst($mtaSts->dns_mode ?? 'Not set') }}
                    @if($mtaSts->dns_mode === 'enforce')
                        <span class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Enforcing)</span>
                    @elseif($mtaSts->dns_mode === 'testing')
                        <span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Testing)</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">DNS ID</div>
                <div class="mt-1 text-sm text-foreground">
                    @php
                        $rawData = is_string($mtaSts->raw_data) ? json_decode($mtaSts->raw_data, true) : $mtaSts->raw_data;
                    @endphp
                    {{ $rawData['dns_id'] ?? 'Not set' }}
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">DNS Valid</div>
                <div class="mt-1 text-sm text-foreground flex items-center gap-2">
                    @if($mtaSts->dns_valid)
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="w-5 h-5 text-green-600 dark:text-green-400"
                        />
                        <span class="text-green-600 dark:text-green-400">Valid</span>
                    @else
                        <x-filament::icon
                            icon="heroicon-o-x-circle"
                            class="w-5 h-5 text-red-600 dark:text-red-400"
                        />
                        <span class="text-red-600 dark:text-red-400">Invalid</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">Policy Valid</div>
                <div class="mt-1 text-sm text-foreground flex items-center gap-2">
                    @if($mtaSts->policy_valid)
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="w-5 h-5 text-green-600 dark:text-green-400"
                        />
                        <span class="text-green-600 dark:text-green-400">Valid</span>
                    @else
                        <x-filament::icon
                            icon="heroicon-o-x-circle"
                            class="w-5 h-5 text-red-600 dark:text-red-400"
                        />
                        <span class="text-red-600 dark:text-red-400">Invalid</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- MX Details --}}
        @if($mtaSts->dns_mx || $mtaSts->policy_data)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @if($mtaSts->dns_mx)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase">DNS MX Records</div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            {{ $mtaSts->dns_mx }}
                        </div>
                    </div>
                @endif

                @if($mtaSts->policy_data)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase">Policy MX Records</div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            @php
                                $policyData = is_string($mtaSts->policy_data) ? json_decode($mtaSts->policy_data, true) : $mtaSts->policy_data;
                            @endphp
                            @if($policyData && isset($policyData['mx']))
                                {{ implode(', ', $policyData['mx']) }}
                            @else
                                Not set
                            @endif
                        </div>
                    </div>
                @endif

            </div>

            {{-- MX Mismatch --}}
            @if($mtaSts->mx_mismatch !== null)
                <div class="p-3 rounded-lg border {{ $mtaSts->mx_mismatch ? 'border-yellow-500/20 bg-yellow-500/5' : 'border-green-500/20 bg-green-500/5' }}">
                    <div class="text-xs text-muted-foreground uppercase">MX Validation</div>
                    <div class="mt-1 text-sm text-foreground flex items-center gap-2">
                        @if($mtaSts->mx_mismatch)
                            <x-filament::icon
                                icon="heroicon-o-exclamation-triangle"
                                class="w-5 h-5 text-yellow-600 dark:text-yellow-400"
                            />
                            <span class="text-yellow-600 dark:text-yellow-400">MX Mismatch Detected</span>
                        @else
                            <x-filament::icon
                                icon="heroicon-o-check-circle"
                                class="w-5 h-5 text-green-600 dark:text-green-400"
                            />
                            <span class="text-green-600 dark:text-green-400">MX Records Match</span>
                        @endif
                    </div>
                    @if($mtaSts->mx_validation_details)
                        @php
                            $validationDetails = is_string($mtaSts->mx_validation_details) ? json_decode($mtaSts->mx_validation_details, true) : $mtaSts->mx_validation_details;
                        @endphp
                        @if($validationDetails)
                            <div class="mt-2 text-xs text-muted-foreground">
                                @if(isset($validationDetails['missing_in_policy']) && count($validationDetails['missing_in_policy']) > 0)
                                    <div>Missing in policy: {{ implode(', ', $validationDetails['missing_in_policy']) }}</div>
                                @endif
                                @if(isset($validationDetails['missing_in_dns']) && count($validationDetails['missing_in_dns']) > 0)
                                    <div>Missing in DNS: {{ implode(', ', $validationDetails['missing_in_dns']) }}</div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        @endif

    @else

        <div class="text-center py-10 text-muted-foreground">
            No MTA-STS record has been checked yet.
        </div>

    @endif

</div>