{{--
    Right after signing in, every sidebar group starts closed. After that, the group that holds the current screen
    opens, and Filament keeps every other group as the person left it.
--}}
<script>
    (() => {
        const collapseAllOnce = @js((bool) session()->pull('sidebar.collapse_groups'))

        const collapseAll = (sidebar) => {
            document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]').forEach((group) => {
                sidebar.collapseGroup(group.dataset.groupLabel)
            })
        }

        let collapsed = false

        const openActiveGroup = () => {
            const sidebar = window.Alpine?.store('sidebar')

            if (! sidebar) {
                return
            }

            if (collapseAllOnce && ! collapsed) {
                collapseAll(sidebar)
                collapsed = true
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
