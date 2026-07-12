<x-filament-panels::page>
    <div id="{{ $this->getPanelMountId() }}" class="min-h-[24rem]"></div>

    @vite($this->getPanelScript())
</x-filament-panels::page>
