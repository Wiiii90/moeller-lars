<x-admin.table class="admin-table--data" aria-label="Artwork positions that will change">
    <table>
        <thead>
            <tr>
                <th scope="col">Current</th>
                <th scope="col">New</th>
                <th scope="col">Artwork</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($changes as $change)
                <tr>
                    <td class="admin-table__position"><span class="admin-position">{{ $change['from'] }}</span></td>
                    <td class="admin-table__position"><span class="admin-position">{{ $change['to'] }}</span></td>
                    <td>{{ $change['title'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-admin.table>
