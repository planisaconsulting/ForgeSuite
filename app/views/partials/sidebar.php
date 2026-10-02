<?php
/** Primary navigation. Coming-soon items are not links. */
$activeNav = $activeNav ?? '';
$groups = [
    ['label' => null, 'links' => [
        ['dashboard', 'Dashboard', '/', 'fa-gauge-high', 'dashboard.view'],
        ['my-work', 'My work', '/work', 'fa-list-check', 'schedule.view'],
        ['my-dashboard', 'My dashboard', '/my/dashboard', 'fa-table-columns', 'dashboard.view'],
        ['approvals', 'Approvals', '/approvals', 'fa-stamp', 'approvals.view'],
        ['reviews', 'Review queue', '/reviews', 'fa-inbox', 'review_queue.view'],
        ['command', 'Quick actions', '/command', 'fa-bolt', 'dashboard.view'],
    ]],
    ['label' => 'Planning', 'links' => [
        ['planning', 'Business overview', '/planning', 'fa-chart-pie', 'planning.view'],
        ['planning-sales', 'Sales forecast', '/planning/sales', 'fa-chart-line', 'forecast.sales'],
        ['planning-backlog', 'Backlog', '/planning/backlog', 'fa-layer-group', 'planning.view'],
        ['planning-cash', 'Cash visibility', '/planning/cash', 'fa-coins', 'forecast.cash'],
        ['planning-materials', 'Material planning', '/planning/materials', 'fa-boxes-stacked', 'forecast.materials'],
        ['planning-mrp', 'MRP', '/planning/mrp', 'fa-diagram-project', 'mrp.view'],
        ['planning-purchasing', 'Purchase forecast', '/planning/purchasing', 'fa-cart-shopping', 'purchase_recommendations.review'],
        ['planning-capacity', 'Capacity forecast', '/planning/capacity', 'fa-gauge', 'forecast.capacity'],
        ['planning-calendar', 'Planning calendar', '/planning/calendar', 'fa-calendar', 'planning.view'],
        ['planning-scenarios', 'Scenarios', '/planning/scenarios', 'fa-sliders', 'scenarios.view'],
    ]],
    ['label' => 'Workshop', 'links' => [
        ['workshop-floor', 'Workshop floor', '/workshop', 'fa-industry', 'workshop.view'],
        ['workshop-scan', 'Scan', '/workshop/scan', 'fa-qrcode', 'workshop.scan'],
        ['workshop-queue', 'Production queue', '/workshop/queue', 'fa-list', 'workshop.view'],
        ['workshop-qc', 'QC', '/workshop/qc', 'fa-clipboard-check', 'qc.perform'],
        ['workshop-ready', 'Ready for dispatch', '/dispatch', 'fa-truck-ramp-box', 'dispatch.view'],
        ['workshop-reprints', 'Reprints', '/workshop/reprints', 'fa-rotate-right', 'production.reprint'],
    ]],
    ['label' => 'CRM', 'links' => [
        ['leads', 'Lead inbox', '/leads', 'fa-inbox', 'leads.view'],
        ['customers', 'Customers', '/customers', 'fa-users', 'customers.view'],
        ['activities', 'Activities', '/activities', 'fa-comments', 'activities.view'],
        ['opportunities', 'Opportunities', '/opportunities', 'fa-bullseye', 'opportunities.view'],
        ['follow-ups', 'Follow-ups', '/follow-ups', 'fa-clock', 'leads.view'],
        ['my-sales', 'My sales', '/sales/desk', 'fa-user-check', 'leads.view'],
        ['surveys', 'Site surveys', '/surveys', 'fa-ruler-combined', 'site_surveys.view'],
    ]],
    ['label' => 'Communications', 'links' => [
        ['communications', 'Communication centre', '/communications', 'fa-comments', 'communications.view'],
        ['message-templates', 'Templates', '/communications/templates', 'fa-envelope', 'communication_templates.view'],
        ['feedback', 'Customer feedback', '/feedback', 'fa-star', 'feedback.view'],
    ]],
    ['label' => 'Sales', 'links' => [
        ['calculator', 'Calculator', '/calculator', 'fa-calculator', 'calculator.use'],
        ['estimates', 'Estimates', '/estimates', 'fa-ruler', 'estimates.view'],
        ['quotes', 'Quotes', '/quotes', 'fa-file-invoice', 'quotes.view'],
        ['templates', 'Signage templates', '/templates', 'fa-swatchbook', 'templates.view'],
    ]],
    ['label' => 'Estimating', 'links' => [
        ['estimate-new', 'New estimate', '/estimates/new', 'fa-plus', 'estimates.create'],
        ['sheets', 'Sheet optimiser', '/estimating/sheets', 'fa-table-cells', 'yield.view'],
        ['rolls', 'Roll optimiser', '/estimating/rolls', 'fa-scroll', 'yield.view'],
        ['install-est', 'Installation estimator', '/estimating/installation', 'fa-person-digging', 'estimates.view'],
        ['vehicle-est', 'Vehicle branding', '/estimating/vehicles', 'fa-truck', 'estimates.view'],
        ['signage', 'Advanced estimating', '/estimating/signage', 'fa-compass-drafting', 'estimators.use'],
        ['specifications', 'Specifications', '/specifications', 'fa-book', 'specifications.view'],
        ['pricing-intel', 'Pricing intelligence', '/estimating/intelligence', 'fa-chart-line', 'pricing_intelligence.view'],
        ['pricing-rec', 'Recommendations', '/estimating/recommendations', 'fa-lightbulb', 'pricing_intelligence.view'],
    ]],
    ['label' => 'Operations', 'links' => [
        ['jobs', 'Jobs', '/jobs', 'fa-clipboard-list', 'jobs.view'],
        ['projects', 'Projects', '/projects', 'fa-diagram-project', 'projects.view_assigned'],
        ['assets', 'Assets', '/assets', 'fa-sign-hanging', 'assets.view'],
        ['service', 'Service', '/service', 'fa-screwdriver-wrench', 'service_requests.view'],
        ['today', 'Today', '/today', 'fa-sun', 'schedule.view'],
        ['workshop', 'Production', '/jobs/workshop', 'fa-industry', 'production.view'],
        ['schedule', 'Schedule', '/schedule', 'fa-calendar-day', 'schedule.view'],
        ['capacity', 'Capacity', '/capacity', 'fa-chart-bar', 'capacity.view'],
        ['installations', 'Installations', '/installations/planner', 'fa-location-dot', 'installations.view'],
        ['dispatch', 'Dispatch', '/dispatch', 'fa-truck-ramp-box', 'dispatch.view'],
        ['snags', 'Snags', '/snags', 'fa-triangle-exclamation', 'snags.view'],
        ['design', 'Artwork', '/jobs/design', 'fa-pen-ruler', 'jobs.view'],
    ]],
    ['label' => 'Resources', 'links' => [
        ['resources', 'Staff and resources', '/resources', 'fa-people-group', 'resources.view'],
        ['maintenance', 'Maintenance', '/maintenance', 'fa-screwdriver-wrench', 'maintenance.view'],
        ['vehicles', 'Vehicles', '/vehicles', 'fa-truck', 'vehicles.view'],
        ['calendar', 'Calendar', '/resources/calendar', 'fa-calendar', 'resources.view'],
    ]],
    ['label' => 'Inventory', 'links' => [
        ['inventory', 'Inventory', '/inventory', 'fa-warehouse', 'inventory.view'],
        ['products', 'Products', '/products', 'fa-box', 'products.view'],
        ['offcuts', 'Offcuts', '/inventory/offcuts', 'fa-scissors', 'inventory.view'],
        ['movements', 'Stock movements', '/inventory/movements', 'fa-right-left', 'inventory.view'],
        ['counts', 'Stock counts', '/inventory/counts', 'fa-clipboard-check', 'inventory.view'],
        ['requirements', 'Material requirements', '/inventory/requirements', 'fa-list-check', 'inventory.view'],
        ['categories', 'Categories', '/categories', 'fa-tags', 'categories.manage'],
    ]],
    ['label' => 'Purchasing', 'links' => [
        ['requests', 'Purchase requests', '/purchasing/requests', 'fa-cart-plus', 'purchasing.view'],
        ['orders', 'Purchase orders', '/purchasing/orders', 'fa-file-contract', 'purchasing.view'],
        ['purchasing', 'Goods receiving', '/purchasing', 'fa-dolly', 'purchasing.view'],
        ['suppliers', 'Suppliers', '/suppliers', 'fa-truck', 'suppliers.view'],
        ['planning-purchasing', 'Recommendations', '/planning/purchasing', 'fa-lightbulb', 'purchase_recommendations.review'],
    ]],
    ['label' => 'Budgets', 'links' => [
        ['budgets', 'Operational budget', '/budgets', 'fa-scale-balanced', 'budgets.view'],
        ['planning-targets', 'Targets', '/budgets/targets', 'fa-bullseye', 'targets.view'],
        ['budget-actual', 'Budget vs actual', '/budgets', 'fa-chart-column', 'budgets.view'],
    ]],
    ['label' => 'Finance', 'links' => [
        ['invoices', 'Invoices', '/invoices', 'fa-receipt', 'invoices.view'],
        ['payments', 'Payments', '/payments', 'fa-money-bill', 'payments.view'],
        ['credits', 'Credit notes', '/credit-notes', 'fa-file-circle-minus', 'credit_notes.view'],
        ['statements', 'Statements', '/finance/statements', 'fa-file-lines', 'statements.view'],
        ['debtors', 'Debtors', '/finance/debtors', 'fa-scale-balanced', 'debtors.view'],
        ['vat', 'VAT summary', '/finance/vat', 'fa-percent', 'finance.vat_report.view'],
    ]],
    ['label' => 'Automation', 'links' => [
        ['workflows', 'Workflows', '/workflows', 'fa-diagram-project', 'workflows.view'],
        ['workflow-history', 'Workflow history', '/workflows/history', 'fa-clock-rotate-left', 'workflows.view'],
        ['recipes', 'Recipes', '/recipes', 'fa-flask', 'recipes.view'],
        ['recurring', 'Recurring jobs', '/recurring', 'fa-rotate', 'recurring_jobs.view'],
        ['subcontracts', 'Subcontractors', '/subcontracts', 'fa-handshake', 'subcontractors.view'],
        ['recipe-test', 'Recipe test', '/recipes/test', 'fa-vial', 'recipes.test'],
        ['setup', 'Production templates', '/jobs/setup', 'fa-sliders', 'settings.manage'],
    ]],
    ['label' => 'Marketing', 'links' => [
        ['campaigns', 'Campaigns', '/marketing/campaigns', 'fa-bullhorn', 'campaigns.view'],
        ['lead-sources', 'Lead sources', '/marketing/sources', 'fa-share-nodes', 'marketing_reports.view'],
        ['retention', 'Customer retention', '/marketing/retention', 'fa-rotate', 'customer_retention.view'],
        ['bulk', 'Checked lists', '/marketing/lists', 'fa-list', 'bulk_communications.send'],
    ]],
    ['label' => 'Reports', 'links' => [
        ['report-executive', 'Executive', '/reports/executive', 'fa-chart-line', 'reports.executive'],
        ['report-sales', 'Sales', '/reports/sales', 'fa-chart-simple', 'reports.sales'],
        ['report-customers', 'Customers', '/reports/customers', 'fa-user-group', 'reports.sales'],
        ['report-jobs', 'Jobs', '/reports/jobs', 'fa-clipboard-list', 'reports.operations'],
        ['report-profitability', 'Profitability', '/reports/profitability', 'fa-chart-pie', 'reports.profitability'],
        ['report-production', 'Production', '/reports/production', 'fa-industry', 'reports.operations'],
        ['report-utilisation', 'Resource utilisation', '/reports/utilisation', 'fa-chart-bar', 'capacity.view'],
        ['report-capacity', 'Capacity and demand', '/reports/capacity-demand', 'fa-scale-balanced', 'capacity.view'],
        ['report-downtime', 'Downtime', '/reports/downtime', 'fa-screwdriver-wrench', 'maintenance.view'],
        ['report-late', 'Late jobs', '/reports/late-jobs', 'fa-clock', 'reports.operations'],
        ['report-eva', 'Estimate vs actual', '/reports/estimate-actual', 'fa-scale-balanced', 'historical_costing.view'],
        ['report-recipe', 'Recipe accuracy', '/reports/recipe-accuracy', 'fa-flask', 'pricing_intelligence.view'],
        ['report-yield', 'Material yield', '/reports/material-yield', 'fa-shapes', 'yield.view'],
        ['report-price-health', 'Pricing health', '/reports/pricing-health', 'fa-heart-pulse', 'pricing_intelligence.view'],
        ['report-waste', 'Materials and waste', '/reports/waste', 'fa-recycle', 'reports.operations'],
        ['report-inventory', 'Inventory', '/reports/inventory', 'fa-warehouse', 'reports.inventory'],
        ['report-purchasing', 'Purchasing', '/reports/purchasing', 'fa-truck', 'reports.inventory'],
        ['report-finance', 'Finance', '/reports/finance', 'fa-coins', 'reports.finance'],
        ['report-debtors', 'Debtors', '/reports/debtors', 'fa-scale-balanced', 'reports.finance'],
        ['documents', 'Document centre', '/documents', 'fa-folder-open', 'dashboard.view'],
        ['doc-cards', 'Job cards', '/documents/job-cards', 'fa-id-card', 'documents.internal.view'],
        ['doc-delivery', 'Delivery notes', '/documents/delivery-notes', 'fa-file-lines', 'dispatch.view'],
        ['doc-complete', 'Completion certificates', '/documents/certificates', 'fa-certificate', 'jobs.view'],
        ['doc-labels', 'Labels', '/admin/label-templates', 'fa-tag', 'documents.templates.manage'],
        ['report-activity', 'Communication activity', '/reports/communication-activity', 'fa-comments', 'marketing_reports.view'],
        ['report-throughput', 'Production throughput', '/reports/throughput', 'fa-gauge', 'reports.operations'],
        ['report-qc', 'QC / rework', '/reports/qc-rework', 'fa-clipboard-check', 'reports.operations'],
        ['report-trace', 'Traceability', '/reports/traceability', 'fa-route', 'tracking.traceability.view'],
        ['report-accuracy', 'Forecast accuracy', '/reports/forecast-accuracy', 'fa-chart-line', 'planning.view'],
        ['report-coverage', 'Stock coverage', '/reports/stock-coverage', 'fa-warehouse', 'forecast.materials'],
        ['report-quality', 'Data quality', '/reports/data-quality', 'fa-clipboard-check', 'data_quality.view'],
        ['report-workflows', 'Workflow performance', '/reports/workflow-performance', 'fa-diagram-project', 'workflows.view'],
    ]],
    ['label' => 'Integrations', 'links' => [
        ['integrations-home', 'Overview', '/integrations', 'fa-plug', 'integrations.view'],
        ['payment-links', 'Payments', '/finance/payment-links', 'fa-link', 'payment_links.manage'],
        ['api-clients', 'API clients', '/admin/api-clients', 'fa-key', 'api.manage'],
        ['webhooks', 'Webhooks', '/admin/webhooks', 'fa-tower-broadcast', 'webhooks.manage'],
        ['integrations', 'Accounting', '/admin/integrations', 'fa-plug', 'integrations.view'],
        ['integration-logs', 'Integration logs', '/admin/integration-logs', 'fa-list', 'integration_logs.view'],
        ['integration-issues', 'Integration issues', '/integrations/issues', 'fa-triangle-exclamation', 'integration_logs.view'],
    ]],
    ['label' => 'Mobile and devices', 'links' => [
        ['mobile-home', 'Field home', '/m', 'fa-mobile-screen', 'mobile.use'],
        ['mobile-devices', 'Devices', '/admin/devices', 'fa-tablet-screen-button', 'device.manage_all'],
        ['mobile-sync', 'Sync status', '/admin/sync-status', 'fa-arrows-rotate', 'device.manage_all'],
        ['mobile-conflicts', 'Sync conflicts', '/admin/sync-conflicts', 'fa-code-compare', 'sync_conflicts.view'],
        ['mobile-push', 'Push notifications', '/admin/push', 'fa-bell', 'device.manage_all'],
        ['mobile-settings', 'Offline settings', '/admin/offline-settings', 'fa-sliders', 'configuration.manage'],
        ['mobile-packs', 'Field packs', '/admin/field-packs', 'fa-suitcase', 'device.manage_all'],
    ]],
    ['label' => 'Administration', 'links' => [
        ['pricing', 'Pricing levels', '/pricing-levels', 'fa-layer-group', 'pricing.view'],
        ['users', 'Users', '/users', 'fa-user-gear', 'users.manage'],
        ['roles', 'Roles', '/admin/roles', 'fa-user-shield', 'users.manage'],
        ['portal', 'Portal access', '/admin/portal', 'fa-id-card', 'portal.access_manage'],
        ['notifications', 'Notifications', '/notifications', 'fa-bell', null],
        ['automations', 'Automations', '/admin/automations', 'fa-robot', 'automations.view'],
        ['audit', 'Audit log', '/admin/audit', 'fa-list-check', 'audit.view'],
        ['backups', 'Backups', '/admin/backups', 'fa-database', 'system.backup'],
        ['health', 'System health', '/admin/health', 'fa-heart-pulse', 'system.health'],
        ['logs', 'Error log', '/admin/logs', 'fa-bug', 'system.logs'],
        ['targets', 'KPI targets', '/admin/targets', 'fa-bullseye', 'settings.manage'],
        ['export', 'Data export', '/admin/export', 'fa-file-export', 'reports.export'],
        ['integrations', 'Integrations', '/admin/integrations', 'fa-plug', 'integrations.view'],
        ['doc-templates', 'Document templates', '/admin/document-templates', 'fa-file-code', 'documents.templates.manage'],
        ['label-templates', 'Label templates', '/admin/label-templates', 'fa-tag', 'documents.templates.manage'],
        ['settings', 'Settings', '/settings', 'fa-gear', 'settings.manage'],
        ['configuration', 'Configuration', '/admin/configuration', 'fa-sliders', 'configuration.manage'],
        ['approval-policies', 'Approval policies', '/admin/approval-policies', 'fa-stamp', 'approvals.view'],
        ['custom-fields', 'Custom fields', '/admin/custom-fields', 'fa-i-cursor', 'custom_fields.manage'],
        ['custom-forms', 'Custom forms', '/admin/custom-forms', 'fa-rectangle-list', 'custom_forms.manage'],
        ['feature-flags', 'Feature flags', '/admin/feature-flags', 'fa-toggle-on', 'feature_flags.manage'],
        ['ai', 'AI assistance', '/admin/ai', 'fa-wand-magic-sparkles', 'ai.admin'],
        ['dictionary', 'Data dictionary', '/admin/dictionary', 'fa-book', 'configuration.manage'],
        ['imports', 'Imports', '/admin/imports', 'fa-file-import', 'imports.perform'],
        ['exports-planning', 'Planning export', '/planning/export.csv', 'fa-file-export', 'exports.perform'],
    ]],
];
?>
<div class="offcanvas-header sf-offcanvas-header d-lg-none">
    <span class="sf-brand-name">Menu</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appNav" aria-label="Close menu"></button>
</div>
<div class="offcanvas-body sf-sidebar-body">
    <a class="sf-brand" href="<?= e(url('/')) ?>">
        <img src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="36" height="36">
        <span>
            <strong>Sign-Forge</strong>
            <small>Management System</small>
        </span>
    </a>

    <?php foreach ($groups as $group): ?>
        <?php
        $visible = [];
        foreach ($group['links'] as $link) {
            $permission = $link[4];
            if ($permission !== null && !can($permission)) {
                continue;
            }
            $visible[] = $link;
        }
        if ($visible === []) {
            continue;
        }
        ?>
        <?php if ($group['label'] !== null): ?>
            <p class="sf-nav-label"><?= e($group['label']) ?></p>
        <?php endif; ?>
        <nav class="sf-nav" aria-label="<?= e($group['label'] ?? 'Main') ?>">
            <?php foreach ($visible as [$key, $label, $href, $icon]): ?>
                <?php if ($href === null): ?>
                    <span class="sf-nav-link is-disabled" aria-disabled="true">
                        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                        <small>Soon</small>
                    </span>
                <?php else: ?>
                    <a class="<?= $key === $activeNav ? 'sf-nav-link active' : 'sf-nav-link' ?>"
                       href="<?= e(url($href)) ?>"
                       <?= $key === $activeNav ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>
    <p class="sf-nav-label"><a href="<?= e(url('/help')) ?>"><?= e(\App\Services\RuntimeService::releaseLabel()) ?></a></p>
</div>
