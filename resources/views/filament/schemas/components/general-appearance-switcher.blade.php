<x-admin.stage-switcher
    storage-key="admin-general.appearance"
    :default-pane="$generalPage->appearanceTab"
    :panes="['global' => 'Global', 'title' => 'Title', 'menu' => 'Menu', 'page' => 'Page']"
    aria-label="Appearance"
    external-state="appearanceTab"
/>
