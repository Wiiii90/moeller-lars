<div class="general-appearance-tabs" role="tablist" aria-label="Appearance">
    @foreach (["global" => "Global", "title" => "Title", "menu" => "Menu", "page" => "Page"] as $key => $label)
        <button
            id="general-appearance-{{ $key }}-tab"
            type="button"
            role="tab"
            class="general-appearance-tabs__tab"
            aria-controls="general-appearance-{{ $key }}-panel"
            x-bind:aria-selected="appearanceTab === '{{ $key }}' ? 'true' : 'false'"
            x-bind:tabindex="appearanceTab === '{{ $key }}' ? 0 : -1"
            x-bind:class="{ 'is-active': appearanceTab === '{{ $key }}' }"
            x-on:click="appearanceTab = '{{ $key }}'"
            x-on:keydown.arrow-right.prevent="const tabs = [...$el.parentElement.querySelectorAll('[role=tab]')]; tabs[(tabs.indexOf($el) + 1) % tabs.length].click(); tabs[(tabs.indexOf($el) + 1) % tabs.length].focus()"
            x-on:keydown.arrow-left.prevent="const tabs = [...$el.parentElement.querySelectorAll('[role=tab]')]; tabs[(tabs.indexOf($el) - 1 + tabs.length) % tabs.length].click(); tabs[(tabs.indexOf($el) - 1 + tabs.length) % tabs.length].focus()"
        >{{ $label }}</button>
    @endforeach
</div>
