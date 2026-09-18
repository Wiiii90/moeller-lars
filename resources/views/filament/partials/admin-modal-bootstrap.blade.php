<script data-navigate-once>
    (() => {
        const wrapFilamentModalRegistration = () => {
            const Alpine = window.Alpine;

            if (! Alpine || Alpine.__adminModalRegistrationWrapped) {
                return;
            }

            Alpine.__adminModalRegistrationWrapped = true;

            const originalData = Alpine.data.bind(Alpine);

            Alpine.data = (name, provider) => {
                if (name !== 'filamentModal') {
                    return originalData(name, provider);
                }

                const wrappedProvider = (options = {}) => {
                    const id = options?.id;
                    const modal = id === undefined || id === null || id === ''
                        ? null
                        : document.getElementById(String(id));
                    const isAdminTaskDialog = modal?.querySelector(
                        ':scope > .fi-modal-window-ctn > .fi-modal-window.admin-task-dialog',
                    ) !== null;

                    return provider({
                        ...options,
                        isScrollLocked: isAdminTaskDialog ? false : options?.isScrollLocked,
                    });
                };

                const registration = originalData(name, wrappedProvider);

                // Only the Filament modal provider needs interception. Restore
                // Alpine's native registry immediately for all later providers.
                Alpine.data = originalData;

                return registration;
            };
        };

        document.addEventListener('alpine:init', wrapFilamentModalRegistration, { once: true });

        if (window.Alpine) {
            wrapFilamentModalRegistration();
        }
    })();
</script>
