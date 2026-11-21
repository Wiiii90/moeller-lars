<x-admin.stage-switcher
    storage-key="admin-general.legal"
    default-pane="copyright"
    :panes="[
        'copyright' => 'Default Copyright Notice',
        'imprint' => 'Imprint',
        'privacy' => 'Privacy Policy',
        'usage' => 'Copyright & AI Use',
        'links' => 'External Links Notice',
    ]"
    aria-label="Legal section"
    external-state="legalTab"
    :pane-help="$help"
/>
