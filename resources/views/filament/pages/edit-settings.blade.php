<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        <div class="flex items-center justify-end gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </div>
</x-filament-panels::page>