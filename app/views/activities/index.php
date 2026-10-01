<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Activities</h1>
    <p class="sf-muted mb-0">Calls, emails, meetings, and notes across customers.</p>
</div>
<form class="sf-filters" method="get" action="<?= e(url('/activities')) ?>">
    <label class="visually-hidden" for="activity-q">Search</label>
    <input class="form-control" id="activity-q" type="search" name="q" value="<?= e($term) ?>" placeholder="Subject, note, or customer">
    <button class="btn btn-sf" type="submit">Search</button>
</form>
<?php if ($rows === []): ?>
    <section class="sf-panel"><div class="sf-empty"><p>No activity matches that search.</p></div></section>
<?php else: ?>
    <div class="table-responsive sf-panel">
        <table class="table sf-table sf-stack align-middle mb-0">
            <thead>
                <tr><th>Date</th><th>Customer</th><th>Type</th><th>Subject</th><th>Follow-up</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Date"><?= e(format_date((string) $row['activity_date'])) ?></td>
                        <td data-label="Customer"><a href="<?= e(url('/customers/' . $row['customer_id'])) ?>"><?= e(customer_label($row)) ?></a></td>
                        <td data-label="Type"><?= e(enum_label(App\Domain\ActivityType::class, (string) $row['activity_type'])) ?></td>
                        <td data-label="Subject"><?= e((string) $row['subject']) ?></td>
                        <td data-label="Follow-up"><?= e(format_date((string) ($row['follow_up_date'] ?? ''))) ?></td>
                        <td data-label="Status"><?= (int) $row['completed'] === 1 ? 'Done' : 'Open' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
