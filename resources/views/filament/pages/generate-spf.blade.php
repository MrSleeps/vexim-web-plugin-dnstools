<x-filament-panels::page>
    <form wire:submit="generateSpf">
        {{ $this->form }}
    </form>

    <x-filament::modal id="dmarc-record-modal" width="2xl">
        <x-slot name="heading">
            Generated SPF Record
        </x-slot>

        <div class="space-y-4">
            <pre class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-sm overflow-x-auto whitespace-pre-wrap break-all">{{ $generatedRecord }}</pre>

<button
    type="button"
    x-on:click="navigator.clipboard.writeText(@js($generatedRecord))"
    class="fi-btn"
>
    Copy to clipboard
</button>
        </div>
    </x-filament::modal>
<div wire:poll.5s>
</div>    
</x-filament-panels::page>