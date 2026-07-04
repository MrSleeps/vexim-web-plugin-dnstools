<div class="space-y-6 text-foreground">

    {{-- Header --}}
    <div class="flex items-start justify-between">

        <div>
            <h3 class="text-lg font-semibold text-foreground">
                {{ $domain }}
            </h3>

            @if($dkim && $dkim->enabled)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="w-4 h-4 mr-1"
                    />
                    Active
                </span>
            @elseif($dkim && !$dkim->enabled)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-500/10 text-yellow-600 dark:text-yellow-400">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="w-4 h-4 mr-1"
                    />
                    Disabled
                </span>
            @else
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                    Not Generated
                </span>
            @endif
        </div>

        @if($dkim && $dkim->created_at)
            <div class="text-xs text-muted-foreground text-right">
                Generated<br>
                <span class="text-foreground">
                    {{ $dkim->created_at->format('Y-m-d H:i:s') }}
                </span>
            </div>
        @endif
    </div>

    @if($dkim)

        {{-- Status Message --}}
        @if($dkim->enabled)
            <div class="p-4 rounded-lg border border-green-500/20 bg-green-500/5">
                <div class="text-sm font-medium text-green-600 dark:text-green-400 flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="w-5 h-5"
                    />
                    DKIM is Active
                </div>
                <div class="mt-1 text-sm text-muted-foreground">
                    This domain is configured to sign outgoing emails with DKIM.
                </div>
            </div>
        @else
            <div class="p-4 rounded-lg border border-yellow-500/20 bg-yellow-500/5">
                <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400 flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="w-5 h-5"
                    />
                    DKIM is Disabled
                </div>
                <div class="mt-1 text-sm text-muted-foreground">
                    DKIM keys exist but are not currently enabled for signing.
                </div>
            </div>
        @endif

        {{-- DNS Record --}}
        @php
            $dnsRecord = $dkim->getDnsRecord();
        @endphp
        
        <div>
            <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-document-text"
                    class="w-4 h-4"
                />
                DNS Record
            </div>

            <div class="mt-2 space-y-2">
                <div class="p-3 rounded-lg border border-border bg-card">
                    <div class="text-xs text-muted-foreground">Name</div>
                    <div class="mt-1">
                        <code class="text-sm text-foreground bg-muted px-2 py-1 rounded break-all">
                            {{ $dnsRecord['name'] }}
                        </code>
                    </div>
                </div>

                <div class="p-3 rounded-lg border border-border bg-card">
                    <div class="text-xs text-muted-foreground">Type</div>
                    <div class="mt-1">
                        <code class="text-sm text-foreground bg-muted px-2 py-1 rounded">
                            {{ $dnsRecord['type'] }}
                        </code>
                    </div>
                </div>

                <div class="p-3 rounded-lg border border-border bg-card">
                    <div class="text-xs text-muted-foreground">Value</div>
                    <div class="mt-1">
                        <code class="text-sm text-foreground bg-muted px-2 py-1 rounded break-all block whitespace-pre-wrap">
                            {{ $dnsRecord['value'] }}
                        </code>
                    </div>
                </div>

                <div class="p-3 rounded-lg border border-border bg-card">
                    <div class="text-xs text-muted-foreground">TTL</div>
                    <div class="mt-1">
                        <code class="text-sm text-foreground bg-muted px-2 py-1 rounded">
                            3600 (1 hour)
                        </code>
                    </div>
                </div>
            </div>
        </div>

        {{-- DKIM Details Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-key"
                        class="w-4 h-4"
                    />
                    Selector
                </div>
                <div class="mt-1 text-sm text-foreground">
                    <code class="text-sm bg-muted px-2 py-1 rounded">
                        {{ $dkim->selector }}
                    </code>
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-shield-check"
                        class="w-4 h-4"
                    />
                    Canonicalization
                </div>
                <div class="mt-1 text-sm text-foreground">
                    {{ strtoupper($dkim->canonical ?? 'simple') }}
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-lock-closed"
                        class="w-4 h-4"
                    />
                    Key Type
                </div>
                <div class="mt-1 text-sm text-foreground">
                    RSA 2048-bit
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-clock"
                        class="w-4 h-4"
                    />
                    Status
                </div>
                <div class="mt-1 text-sm text-foreground">
                    @if($dkim->enabled)
                        <span class="text-green-600 dark:text-green-400">Enabled</span>
                    @else
                        <span class="text-yellow-600 dark:text-yellow-400">Disabled</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Public Key (hidden by default, expandable) --}}
        <div class="p-3 rounded-lg border border-border bg-card">
            <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-key"
                    class="w-4 h-4"
                />
                Public Key
            </div>
            <div class="mt-1 text-sm text-foreground">
                <details class="cursor-pointer">
                    <summary class="text-xs text-muted-foreground hover:text-foreground">
                        Click to view public key
                    </summary>
                    <code class="block mt-2 text-xs bg-muted p-2 rounded break-all whitespace-pre-wrap">
                        {{ $dkim->public_key }}
                    </code>
                </details>
            </div>
        </div>

        {{-- Instructions --}}
        <div class="p-4 rounded-lg border border-blue-500/20 bg-blue-500/5">
            <div class="text-sm font-medium text-blue-600 dark:text-blue-400 flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="w-5 h-5"
                />
                Next Steps
            </div>
            <div class="mt-2 text-sm text-muted-foreground space-y-1">
                <p>1. Add the TXT record above to your DNS provider</p>
                <p>2. Wait for DNS propagation (may take up to 24 hours)</p>
                <p>3. Enable DKIM in your email sending settings</p>
                <p class="text-xs text-muted-foreground/70 mt-2">
                    Note: The record name should be <code>{{ $dkim->selector }}._domainkey.{{ $domain }}</code>
                </p>
            </div>
        </div>

    @else

        <div class="text-center py-10 text-muted-foreground">
            <x-filament::icon
                icon="heroicon-o-key"
                class="w-12 h-12 mx-auto mb-3 text-muted-foreground/50"
            />
            No DKIM keys have been generated for this domain yet.
        </div>

    @endif

</div>