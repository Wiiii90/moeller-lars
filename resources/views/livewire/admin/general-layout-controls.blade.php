<section class="general-layout-controls" aria-label="Public page geometry">
    <div class="general-layout-controls__heading">
        <span>Page geometry</span>
        <small>Applied to the public site</small>
    </div>

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
                    aria-describedby="general-page-width-help"
                >
                <small>px</small>
            </span>
            <small id="general-page-width-help">Overall public content shell</small>
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
                    aria-describedby="general-content-padding-help"
                >
                <small>px</small>
            </span>
            <small id="general-content-padding-help">Inset between page edge and artwork</small>
            @error('contentPadding') <em>{{ $message }}</em> @enderror
        </label>
    </div>
</section>
