<div class="contents" x-on:publication-commit.window="$wire.commitPublication()"></div>

@script
<script>
    if (! window.__publicationStateInterceptorRegistered) {
        window.__publicationStateInterceptorRegistered = true

        Livewire.interceptMessage(({ message, onSuccess, onFinish }) => {
            if (message.component.name === 'admin.publication-state-bridge') {
                return
            }

            let succeeded = false

            onSuccess(() => {
                succeeded = true
            })

            onFinish(() => {
                if (! succeeded) {
                    return
                }

                Livewire.getByName('admin.publication-state-bridge')[0]?.refreshState()
            })
        })
    }
</script>
@endscript
