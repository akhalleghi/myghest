<style>
    :root {
        --table-row-stripe: rgba(15, 23, 42, 0.035);
    }

    html[data-theme="dark"] {
        --table-row-stripe: rgba(148, 163, 184, 0.06);
    }

    table tbody tr:nth-child(even) {
        background-color: var(--table-row-stripe);
    }
</style>
