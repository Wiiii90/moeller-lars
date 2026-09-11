<section class="general-layout-controls" aria-label="Public page geometry">
    <div class="general-layout-controls__fields">
        <label class="general-layout-field">
            <span>Page width</span>
            <span class="general-layout-field__control">
                <input
                    type="number"
                    min="640"
                    max="1440"
                    step="10"
                    wire:model.live.debounce.350ms="pageWidth"
                >
                <small>px</small>
            </span>
            @error('pageWidth') <em>{{ $message }}</em> @enderror
        </label>

        <label class="general-layout-field">
            <span>Content padding</span>
            <span class="general-layout-field__control">
                <input
                    type="number"
                    min="24"
                    max="180"
                    step="1"
                    wire:model.live.debounce.350ms="contentPadding"
                >
                <small>px</small>
            </span>
            @error('contentPadding') <em>{{ $message }}</em> @enderror
        </label>
    </div>
</section>
