        <div class="admin-bottom-add">
            <button class="admin-bottom-add__button" type="button" wire:click="mountAction('addComponent')">
                <span class="admin-bottom-add__icon" aria-hidden="true">+</span>
                <span class="admin-bottom-add__label">Add component</span>
            </button>
        </div>

        <footer class="admin-pager">
            <label class="admin-pager__size">
                <span>Per page</span>
                <select wire:model.live.number="pageSize">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <span class="admin-pager__range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
            <div class="admin-pager__actions admin-toolbar">
                <button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button>
                <button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button>
            </div>
        </footer>
