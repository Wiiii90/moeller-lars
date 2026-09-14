        <x-admin.add-row wire:click="mountAction('addComponent')">Add component</x-admin.add-row>

        <footer class="admin-pager">
            <x-admin.page-size-picker
                :value="$pageSize"
                :options="[25, 50, 100]"
                wire-model="pageSize"
                aria-label="Custom page components per page"
            />
            <span class="admin-pager__range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
            <div class="admin-pager__actions admin-toolbar">
                <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
            </div>
        </footer>
