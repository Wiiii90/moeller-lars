/*
 * Visual state for native Filament Create/Command Action dialogs.
 *
 * The canonical form state lives at mountedActions[index].data (Filament
 * 5.7.6). Input/change events account for on-blur input bindings before
 * Livewire updates that state. No persistence, validation or modal lifecycle
 * is replaced by this visual projection.
 */

function clone(value) {
    return JSON.parse(JSON.stringify(value ?? {}));
}

function parts(path) {
    const result = path.split('.');
    return result.every((part) => part && !['__proto__', 'constructor', 'prototype'].includes(part))
        ? result : [];
}

function fieldValue(state, path) {
    return parts(path).reduce((current, part) => current?.[part], state);
}

function withField(state, path, value) {
    const fields = parts(path);
    if (!fields.length) return;

    let cursor = state;
    fields.forEach((part, index) => {
        if (index === fields.length - 1) {
            cursor[part] = value;
        } else {
            cursor[part] ??= /^\d+$/.test(fields[index + 1]) ? [] : {};
            cursor = cursor[part];
        }
    });
}

function sameInputValue(a, b) {
    if (Array.isArray(a) || Array.isArray(b)) {
        return JSON.stringify(a ?? []) === JSON.stringify(b ?? []);
    }

    if (typeof a === 'boolean' || typeof b === 'boolean') {
        return a === b;
    }

    return String(a ?? '') === String(b ?? '');
}

function provided(value) {
    if (value == null || value === false) return false;
    if (typeof value === 'string') return value.trim() !== '';
    if (typeof value === 'number') return Number.isFinite(value);
    if (Array.isArray(value)) return value.some(provided);
    if (typeof value === 'object') return Object.values(value).some(provided);
    return true;
}

function inputValue(input) {
    if (input.type === 'checkbox') return input.checked;
    if (input.type === 'radio') return input.checked ? input.value : null;
    if (input.tagName === 'SELECT' && input.multiple) {
        return Array.from(input.selectedOptions, (option) => option.value);
    }

    return input.value;
}

export function adminDialogDraft() {
    return {
        draftIndex: -1,
        draftBaseline: null,
        draftActive: false,
        draftDirty: false,
        draftReady: true,
        readinessFields: [],
        pendingInputs: {},

        init() {
            this.readinessFields = (this.$el.getAttribute('data-admin-dialog-readiness-fields') ?? '')
                .split(',').filter(Boolean);
        },

        captureDraft(mountedActions) {
            const actions = Array.isArray(mountedActions) ? mountedActions : [];
            this.draftIndex = actions.length - 1;
            this.draftBaseline = JSON.stringify(clone(actions[this.draftIndex]?.data));
            this.draftActive = true;
            this.pendingInputs = {};
            this.refreshDraft(actions);
        },

        observeInput(event, mountedActions) {
            if (!this.draftActive || !event.target?.attributes) return;

            let element = event.target;
            while (element && this.$el.contains(element)) {
                const binding = Array.from(element.attributes).find((attr) =>
                    attr.name === 'wire:model' || attr.name.startsWith('wire:model.'));

                if (binding) {
                    const match = binding.value.match(/^mountedActions\.(\d+)\.data\.(.+)$/);
                    if (match && Number(match[1]) === this.draftIndex && parts(match[2]).length) {
                        if (element.type !== 'radio' || element.checked) {
                            this.pendingInputs[match[2]] = inputValue(element);
                        }
                    }
                    break;
                }
                if (element === this.$el) break;
                element = element.parentElement;
            }

            this.refreshDraft(mountedActions);
        },

        refreshDraft(mountedActions) {
            if (!this.draftActive || this.draftIndex < 0) return;

            const data = clone(mountedActions?.[this.draftIndex]?.data);
            const effective = clone(data);

            for (const [path, value] of Object.entries(this.pendingInputs)) {
                if (sameInputValue(fieldValue(data, path), value)) {
                    delete this.pendingInputs[path];
                } else {
                    withField(effective, path, value);
                }
            }

            this.draftDirty = JSON.stringify(effective) !== this.draftBaseline;
            this.draftReady = this.readinessFields.every((path) => provided(fieldValue(effective, path)));
        },

        clearDraft() {
            this.draftIndex = -1;
            this.draftBaseline = null;
            this.draftActive = false;
            this.draftDirty = false;
            this.draftReady = true;
            this.pendingInputs = {};
        },
    };
}
