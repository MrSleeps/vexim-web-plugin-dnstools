<div class="space-y-6 text-foreground">

    {{-- Header --}}
    <div class="flex items-start justify-between">

        <div>
            <h3 class="text-lg font-semibold text-foreground">
                {{ $domain }}
            </h3>

            @if($dmarc && $dmarc->valid)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400">
                    Valid
                </span>
            @elseif($dmarc)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400">
                    Invalid
                </span>
            @else
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                    Not Checked
                </span>
            @endif
        </div>

        @if($dmarc && $dmarc->last_checked_at)
            <div class="text-xs text-muted-foreground text-right">
                Last checked<br>
                <span class="text-foreground">
                    {{ $dmarc->last_checked_at->format('Y-m-d H:i:s') }}
                </span>
            </div>
        @endif
    </div>

    @if($dmarc)

        {{-- Error --}}
        @if(!$dmarc->valid && $dmarc->error_message)
            <div class="p-4 rounded-lg border border-red-500/20 bg-red-500/5">
                <div class="text-sm font-medium text-red-500 flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-circle"
                        class="w-5 h-5"
                    />
                    Error
                </div>
                <div class="mt-1 text-sm text-red-400">
                    {{ $dmarc->error_message }}
                </div>
            </div>
        @endif

        {{-- Record --}}
        @if($dmarc->record)
            <div>
                <div class="text-xs text-muted-foreground uppercase">
                    DMARC Record
                </div>

                <div class="mt-1 p-3 rounded-lg border border-border bg-card">
                    <code class="text-sm text-foreground break-all">
                        {{ $dmarc->record }}
                    </code>
                </div>
            </div>
        @endif

        {{-- Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">Policy</div>
                <div class="mt-1 text-sm text-foreground">
                    {{ ucfirst($dmarc->policy ?? 'Not set') }}
                    @if($dmarc->policy === 'reject')
                        <span class="ml-2 text-xs text-red-600 dark:text-red-400">(Strictest)</span>
                    @elseif($dmarc->policy === 'quarantine')
                        <span class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Moderate)</span>
                    @elseif($dmarc->policy === 'none')
                        <span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Monitor Only)</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">Subdomain Policy</div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $dmarc->subdomain_policy ? ucfirst($dmarc->subdomain_policy) : 'Not set (inherits from parent)' }}
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">DKIM Alignment</div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $dmarc->adkim ? ucfirst($dmarc->adkim) : 'Not set' }}
                    @if($dmarc->adkim === 's')
                        <span class="ml-2 text-xs text-green-600 dark:text-green-400">(Strict)</span>
                    @elseif($dmarc->adkim === 'r')
                        <span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Relaxed)</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase">SPF Alignment</div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $dmarc->aspf ? ucfirst($dmarc->aspf) : 'Not set' }}
                    @if($dmarc->aspf === 's')
                        <span class="ml-2 text-xs text-green-600 dark:text-green-400">(Strict)</span>
                    @elseif($dmarc->aspf === 'r')
                        <span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Relaxed)</span>
                    @endif
                </div>
            </div>

            @if($dmarc->percentage !== null)
                <div class="p-3 rounded-lg border border-border bg-card">
                    <div class="text-xs text-muted-foreground uppercase">Percentage</div>
                    <div class="mt-1 text-sm text-foreground">
                        {{ $dmarc->percentage }}%
                        @if($dmarc->percentage < 100)
                            <span class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Partial rollout)</span>
                        @endif
                    </div>
                </div>
            @endif

        </div>

        {{-- RUA / RUF --}}
        @if($dmarc->rua || $dmarc->ruf)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @if($dmarc->rua)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-envelope"
                                class="w-4 h-4"
                            />
                            Aggregate Reports (RUA)
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            {{ implode(', ', $dmarc->rua) }}
                        </div>
                    </div>
                @endif

                @if($dmarc->ruf)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-shield-exclamation"
                                class="w-4 h-4"
                            />
                            Forensic Reports (RUF)
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            {{ implode(', ', $dmarc->ruf) }}
                        </div>
                    </div>
                @endif

            </div>
        @endif

    @else

        <div class="text-center py-10 text-muted-foreground">
            <x-filament::icon
                icon="heroicon-o-information-circle"
                class="w-12 h-12 mx-auto mb-3 text-muted-foreground/50"
            />
            No DMARC record has been checked yet.
        </div>

    @endif

</div>