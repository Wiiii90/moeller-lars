<div class="admin-delete-impact">
    <p>The following child pages will also be deleted:</p>
    @foreach ($groups as $group)
        <div>
            <strong>{{ $group['parent'] }}</strong>
            <ul aria-label="Child pages of {{ $group['parent'] }}">
                @foreach ($group['children'] as $child)
                    <li>{{ $child['label'] }} ({{ $child['type'] }})</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
