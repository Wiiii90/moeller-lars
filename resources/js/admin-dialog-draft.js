/*
 * A submitted Create/Command dialog discards data only after its current draft
 * differs from the form's opening state. Track the mounted Filament Action
 * data, not DOM input guesses: defaults, editors, toggles and repeaters all
 * share that canonical state. Livewire/Filament still own form submission.
 */
export function adminDialogDraft() {
    return {
        draftIndex: -1,
        draftBaseline: null,
        draftActive: false,
        draftDirty: false,

        captureDraft(mountedActionsData) {
            const actions = Array.isArray(mountedActionsData) ? mountedActionsData : [];
            this.draftIndex = actions.length - 1;
            this.draftBaseline = JSON.stringify(actions[this.draftIndex] ?? null);
            this.draftActive = true;
            this.draftDirty = false;
        },

        compareDraft(currentState) {
            this.draftDirty = this.draftActive && currentState !== this.draftBaseline;
        },

        clearDraft() {
            this.draftActive = false;
            this.draftIndex = -1;
            this.draftBaseline = null;
            this.draftDirty = false;
        },
    };
}
