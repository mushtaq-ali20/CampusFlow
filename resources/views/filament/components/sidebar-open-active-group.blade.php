{{-- Opens the sidebar group that holds the current screen. Filament keeps every other group as the person left it. --}}
<script>
    (() => {
        const openActiveGroup = () => {
            const sidebar = window.Alpine?.store('sidebar')

            if (! sidebar) {
                return
            }

            document.querySelectorAll('.fi-sidebar-group.fi-active').forEach((group) => {
                const label = group.dataset.groupLabel

                if (sidebar.groupIsCollapsed(label)) {
                    sidebar.toggleCollapsedGroup(label)
                }
            })
        }

        openActiveGroup()
        document.addEventListener('alpine:initialized', openActiveGroup)
        document.addEventListener('livewire:navigated', openActiveGroup)
    })()
</script>
