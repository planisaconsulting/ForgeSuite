<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Capacity and demand</h1><p class="sf-muted mb-0">Unscheduled estimated demand in this window: <?= e((string) $unscheduled_minutes) ?> minutes. Use this before promising a customer date.</p></div>
<?php require base_path('app/views/planning/capacity.php'); ?>
