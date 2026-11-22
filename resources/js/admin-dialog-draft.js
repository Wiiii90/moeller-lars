/*
 * A submitted Create/Command dialog discards data only after its current draft
 * differs from the form's opening state. Track the mounted Filament Action
 * data, not DOM input guesses: defaults, editors, toggles and repeaters all
 * share that canonical state. Livewire/Filament still own form submission.
 */
export function adminDialogDraft() {
    return {
        index: -1,
        initialState: null,
        active: false,
        dirty: false,

        open(mountedActionsData) {
            const actions = Array.isArray(mountedActionsData) ? mountedActionsData : [];
            this.index = actions.length - 1;
            this.initialState = JSON.stringify(actions[this.index] ?? null);
            this.active = true;
            this.dirty = false;
        },

        track(currentState) {
            this.dirty = this.active && currentState !== this.initialState;
        },

        close() {
            this.active = false;
            this.index = -1;
            this.initialState = null;
            this.dirty = false;
        },
    };
}
