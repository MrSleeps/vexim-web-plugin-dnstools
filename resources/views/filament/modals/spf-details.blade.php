<div class="space-y-6 text-foreground">

    {{-- Header --}}
    <div class="flex items-start justify-between">

        <div>
            <h3 class="text-lg font-semibold text-foreground">
                {{ $domain }}
            </h3>

            @if($spf && $spf->valid)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400">
                    Valid
                </span>
            @elseif($spf)
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400">
                    Invalid
                </span>
            @else
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                    Not Checked
                </span>
            @endif
        </div>

        @if($spf && $spf->last_checked_at)
            <div class="text-xs text-muted-foreground text-right">
                Last checked<br>
                <span class="text-foreground">
                    {{ \Carbon\Carbon::parse($spf->last_checked_at)->format('Y-m-d H:i:s') }}
                </span>
            </div>
        @endif
    </div>

    @if($spf)

        {{-- Error --}}
        @if(!$spf->valid && $spf->error_message)
            <div class="p-4 rounded-lg border border-red-500/20 bg-red-500/5">
                <div class="text-sm font-medium text-red-500 flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-circle"
                        class="w-5 h-5"
                    />
                    Error
                </div>
                <div class="mt-1 text-sm text-red-400">
                    {{ $spf->error_message }}
                </div>
            </div>
        @endif

        {{-- Validation Issues --}}
        @php
            $validationIssues = is_string($spf->validation_issues) ? json_decode($spf->validation_issues, true) : $spf->validation_issues;
        @endphp
        @if($validationIssues && count($validationIssues) > 0)
            <div class="p-4 rounded-lg border border-yellow-500/20 bg-yellow-500/5">
                <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400 flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="w-5 h-5"
                    />
                    Validation Issues
                </div>
                <div class="mt-1 text-sm text-yellow-500">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($validationIssues as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Record --}}
        @if($spf->record)
            <div>
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-document-text"
                        class="w-4 h-4"
                    />
                    SPF Record
                </div>

                <div class="mt-1 p-3 rounded-lg border border-border bg-card">
                    <code class="text-sm text-foreground break-all">
                        {{ $spf->record }}
                    </code>
                </div>
            </div>
        @endif

        {{-- Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-tag"
                        class="w-4 h-4"
                    />
                    SPF Version
                </div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $spf->spf_version ?? 'Not set' }}
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-shield-check"
                        class="w-4 h-4"
                    />
                    Policy
                </div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $spf->policy ?? 'Not set' }}
                    @if($spf->policy === '-all')
                        <span class="ml-2 text-xs text-red-600 dark:text-red-400">(Strict - Hard Fail)</span>
                    @elseif($spf->policy === '~all')
                        <span class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Soft Fail)</span>
                    @elseif($spf->policy === '?all')
                        <span class="ml-2 text-xs text-blue-600 dark:text-blue-400">(Neutral)</span>
                    @elseif($spf->policy === '+all')
                        <span class="ml-2 text-xs text-red-600 dark:text-red-400">(Permissive - Not Recommended)</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-calculator"
                        class="w-4 h-4"
                    />
                    DNS Lookup Count
                </div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $spf->lookup_count ?? 0 }}
                    @if(($spf->lookup_count ?? 0) > 10)
                        <span class="ml-2 text-xs text-red-600 dark:text-red-400">(Exceeds 10 limit!)</span>
                    @elseif(($spf->lookup_count ?? 0) > 8)
                        <span class="ml-2 text-xs text-yellow-600 dark:text-yellow-400">(Approaching limit)</span>
                    @endif
                </div>
            </div>

            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-list-bullet"
                        class="w-4 h-4"
                    />
                    Total Mechanisms
                </div>
                <div class="mt-1 text-sm text-foreground">
                    {{ $spf->mechanism_count ?? 0 }}
                </div>
            </div>

        </div>

        {{-- IP Addresses --}}
        @php
            $ip4s = is_string($spf->ip4) ? json_decode($spf->ip4, true) : $spf->ip4;
            $ip6s = is_string($spf->ip6) ? json_decode($spf->ip6, true) : $spf->ip6;
        @endphp
        @if(($ip4s && count($ip4s) > 0) || ($ip6s && count($ip6s) > 0))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @if($ip4s && count($ip4s) > 0)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-globe-alt"
                                class="w-4 h-4"
                            />
                            IPv4 Addresses
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($ip4s as $ip)
                                    <li><code class="text-xs">{{ $ip }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($ip6s && count($ip6s) > 0)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-globe-alt"
                                class="w-4 h-4"
                            />
                            IPv6 Addresses
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($ip6s as $ip)
                                    <li><code class="text-xs">{{ $ip }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        {{-- Domains --}}
        @php
            $aDomains = is_string($spf->a_domains) ? json_decode($spf->a_domains, true) : $spf->a_domains;
            $mxDomains = is_string($spf->mx_domains) ? json_decode($spf->mx_domains, true) : $spf->mx_domains;
            $includes = is_string($spf->includes) ? json_decode($spf->includes, true) : $spf->includes;
        @endphp
        @if(($aDomains && count($aDomains) > 0) || ($mxDomains && count($mxDomains) > 0) || ($includes && count($includes) > 0))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @if($aDomains && count($aDomains) > 0)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-link"
                                class="w-4 h-4"
                            />
                            A Record Domains
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($aDomains as $domain)
                                    <li><code class="text-xs">{{ $domain }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($mxDomains && count($mxDomains) > 0)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-envelope"
                                class="w-4 h-4"
                            />
                            MX Record Domains
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($mxDomains as $domain)
                                    <li><code class="text-xs">{{ $domain }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($includes && count($includes) > 0)
                    <div class="p-3 rounded-lg border border-border bg-card">
                        <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-arrow-right-circle"
                                class="w-4 h-4"
                            />
                            Included Domains
                        </div>
                        <div class="mt-1 text-sm text-foreground break-all">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($includes as $include)
                                    <li><code class="text-xs">{{ $include }}</code></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        {{-- Additional Info --}}
        @php
            $modifiers = is_string($spf->modifiers) ? json_decode($spf->modifiers, true) : $spf->modifiers;
        @endphp
        @if($spf->has_ptr || $spf->has_exists || ($modifiers && count($modifiers) > 0))
            <div class="p-3 rounded-lg border border-border bg-card">
                <div class="text-xs text-muted-foreground uppercase flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-cog"
                        class="w-4 h-4"
                    />
                    Additional Mechanisms
                </div>
                <div class="mt-1 text-sm text-foreground">
                    @if($spf->has_ptr)
                        <span class="inline-flex items-center gap-1 mr-3">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4 text-yellow-500" />
                            Has PTR
                        </span>
                    @endif
                    @if($spf->has_exists)
                        <span class="inline-flex items-center gap-1 mr-3">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4 text-yellow-500" />
                            Has EXISTS
                        </span>
                    @endif
                    @if($modifiers && count($modifiers) > 0)
                        <span class="inline-flex items-center gap-1">
                            <x-filament::icon icon="heroicon-o-cog" class="w-4 h-4" />
                            Modifiers: {{ implode(', ', $modifiers) }}
                        </span>
                    @endif
                </div>
            </div>
        @endif

    @else

        <div class="text-center py-10 text-muted-foreground">
            <x-filament::icon
                icon="heroicon-o-information-circle"
                class="w-12 h-12 mx-auto mb-3 text-muted-foreground/50"
            />
            No SPF record has been checked yet.
        </div>

    @endif

</div>