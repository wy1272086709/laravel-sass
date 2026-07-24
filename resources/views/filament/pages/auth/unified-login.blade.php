<x-filament-panels::page.simple>
    <x-slot name="subheading">
        <span class="flex items-center justify-center gap-2">
            <x-filament::button
                :href="$this->getPanelSwitchUrl('platform')"
                tag="a"
                size="sm"
                :color="filament()->getCurrentPanel()->getId() === 'platform' ? 'primary' : 'gray'"
            >
                平台管理员
            </x-filament::button>

            <x-filament::button
                :href="$this->getPanelSwitchUrl('merchant')"
                tag="a"
                size="sm"
                :color="filament()->getCurrentPanel()->getId() === 'merchant' ? 'primary' : 'gray'"
            >
                商户用户
            </x-filament::button>
        </span>
    </x-slot>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form id="form" wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
