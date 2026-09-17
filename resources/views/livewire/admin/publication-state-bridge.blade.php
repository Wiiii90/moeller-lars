<div class="contents" x-on:publication-commit.window="$wire.commitPublication()"></div>

@script
<script>
    if (! window.__publicationStateInterceptorRegistered) {
        window.__publicationStateInterceptorRegistered = true

        Livewire.interceptRequest(({ onResponse }) => {
            onResponse(({ response }) => {
                const pending = response.headers.get('X-Publication-Pending')

                if (pending !== '0' && pending !== '1') {
                    return
                }

                window.dispatchEvent(new CustomEvent('publication-state-changed', {
                    detail: { pending: pending === '1' },
                }))
            })
        })
    }
</script>
@endscript
