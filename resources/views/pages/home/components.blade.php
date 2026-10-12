@include('pages.partials.component-sequence', [
    'blocks' => $components,
    'componentSettings' => new \App\Models\CustomPageSetting,
    'label' => 'Home presentation',
    'isHome' => true,
])
