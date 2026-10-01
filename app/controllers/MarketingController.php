<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\MarketingRepository;
use App\Services\AuditService;
use App\Services\SettingsService;

final class MarketingController
{
    /** @var list<string> */
    private const CHANNELS = ['GOOGLE', 'FACEBOOK', 'INSTAGRAM', 'EMAIL', 'WHATSAPP', 'PRINT', 'VEHICLE', 'SIGNAGE', 'REFERRAL', 'DIRECT', 'OTHER'];

    public function campaigns(): void
    {
        View::render('marketing/campaigns', [
            'title' => 'Campaigns',
            'activeNav' => 'campaigns',
            'rows' => (new MarketingRepository())->campaigns(),
            'channels' => self::CHANNELS,
            'canManage' => can('campaigns.manage'),
        ]);
    }

    public function storeCampaign(): void
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['campaign_code'] ?? '')) ?? '');
        $channel = strtoupper((string) ($_POST['channel'] ?? 'OTHER'));
        if ($code === '' || trim((string) ($_POST['name'] ?? '')) === '') {
            flash('error', 'Name and campaign code are required.');
            redirect('/marketing/campaigns');
        }
        $budget = trim((string) ($_POST['budget'] ?? ''));
        (new MarketingRepository())->insertCampaign([
            'name' => mb_substr(trim((string) $_POST['name']), 0, 160),
            'campaign_code' => mb_substr($code, 0, 60),
            'channel' => in_array($channel, self::CHANNELS, true) ? $channel : 'OTHER',
            'start_date' => blank_to_null($_POST['start_date'] ?? null),
            'end_date' => blank_to_null($_POST['end_date'] ?? null),
            'budget' => $budget !== '' && Decimal::isNumeric(str_replace(',', '.', $budget)) ? Decimal::money(str_replace(',', '.', $budget)) : null,
            'description' => blank_to_null($_POST['description'] ?? null),
            'status' => 'ACTIVE',
            'created_by' => (int) auth_user()['id'],
        ]);
        (new AuditService())->record('campaign', null, 'CAMPAIGN_CREATED', null, ['code' => $code], (int) auth_user()['id']);
        flash('success', 'Campaign saved.');
        redirect('/marketing/campaigns');
    }

    public function showCampaign(string $id): void
    {
        $campaign = (new MarketingRepository())->campaign(route_id($id));
        if ($campaign === null) {
            abort_not_found('That campaign was not found.');
        }
        $figures = (new MarketingRepository())->campaignFigures((int) $campaign['id']);
        $valid = (int) ($figures['valid_leads'] ?? 0);
        $budget = $campaign['budget'];
        $costPerLead = ($budget !== null && $valid > 0) ? Decimal::div((string) $budget, (string) $valid, 2) : null;
        $converted = (int) ($figures['accepted'] ?? 0);
        $cac = ($budget !== null && $converted > 0) ? Decimal::div((string) $budget, (string) $converted, 2) : null;
        View::render('marketing/campaign', [
            'title' => (string) $campaign['name'],
            'activeNav' => 'campaigns',
            'campaign' => $campaign,
            'figures' => $figures,
            'costPerLead' => $costPerLead,
            'cac' => $cac,
        ]);
    }

    public function sources(): void
    {
        View::render('marketing/sources', [
            'title' => 'Lead sources',
            'activeNav' => 'lead-sources',
            'rows' => (new MarketingRepository())->sourceReport(),
        ]);
    }

    public function retention(): void
    {
        $months = max(1, (int) SettingsService::get('dormant_customer_months', '12'));
        $repeat = (new MarketingRepository())->repeatCustomers();
        View::render('marketing/retention', [
            'title' => 'Customer retention',
            'activeNav' => 'retention',
            'months' => $months,
            'dormant' => (new MarketingRepository())->dormant($months),
            'repeat' => $repeat,
            'rate' => $repeat['with_job'] > 0 ? Decimal::div((string) $repeat['repeaters'], (string) $repeat['with_job'], 4) : null,
        ]);
    }

    public function activity(): void
    {
        View::render('marketing/activity', [
            'title' => 'Communication activity',
            'activeNav' => 'report-activity',
            'rows' => (new MarketingRepository())->activity(date('Y-m-01'), date('Y-m-d')),
            'response' => (new MarketingRepository())->responseMinutes(date('Y-m-01'), date('Y-m-d')),
        ]);
    }

    public function feedback(): void
    {
        View::render('marketing/feedback', [
            'title' => 'Customer feedback',
            'activeNav' => 'feedback',
            'rows' => (new MarketingRepository())->feedback(),
            'summary' => (new MarketingRepository())->feedbackSummary(),
            'canManage' => can('feedback.manage'),
        ]);
    }

    public function storeFeedback(): void
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $rating = trim((string) ($_POST['rating'] ?? ''));
        $score = null;
        if ($rating !== '') {
            if (!ctype_digit($rating) || (int) $rating < 1 || (int) $rating > 5) {
                flash('error', 'Use a rating from 1 to 5, or leave it blank.');
                redirect('/feedback');
            }
            $score = (int) $rating;
        }
        $id = (new MarketingRepository())->insertFeedback([
            'customer_id' => $customerId,
            'contact_id' => ((int) ($_POST['contact_id'] ?? 0)) > 0 ? (int) $_POST['contact_id'] : null,
            'job_id' => ((int) ($_POST['job_id'] ?? 0)) > 0 ? (int) $_POST['job_id'] : null,
            'rating' => $score,
            'feedback_text' => blank_to_null($_POST['feedback_text'] ?? null),
            'source' => 'STAFF',
            'submitted_at' => date('Y-m-d H:i:s'),
            'followup_required' => isset($_POST['followup_required']) ? 1 : 0,
        ]);
        if (isset($_POST['followup_required'])) {
            (new \App\Repositories\NotificationRepository())->insertReminder([
                'user_id' => (int) auth_user()['id'],
                'entity_type' => 'customer',
                'entity_id' => $customerId,
                'title' => 'Feedback follow-up',
                'description' => 'A service follow-up was requested. This is not a separate complaint file.',
                'remind_at' => date('Y-m-d H:i:s', time() + 3600),
                'dedupe_key' => 'feedback:' . $id,
            ]);
        }
        (new AuditService())->record('customer', $customerId, 'FEEDBACK_RECEIVED', null, ['rating' => $score], (int) auth_user()['id']);
        flash('success', 'Feedback stored, including a low rating when one was given.');
        redirect('/feedback');
    }

    public function review(): void
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        (new MarketingRepository())->insertReview([
            'customer_id' => $customerId,
            'contact_id' => null,
            'job_id' => ((int) ($_POST['job_id'] ?? 0)) > 0 ? (int) $_POST['job_id'] : null,
            'channel' => 'EMAIL',
            'destination' => (string) SettingsService::get('review_request_url', ''),
            'requested_at' => date('Y-m-d H:i:s'),
            'requested_by' => (int) auth_user()['id'],
        ]);
        (new AuditService())->record('customer', $customerId, 'REVIEW_REQUESTED', null, [
            'destination' => (string) SettingsService::get('review_request_url', ''),
        ], (int) auth_user()['id']);
        flash('success', 'Review request recorded. Completion is not assumed.');
        redirect('/feedback');
    }

    public function bulkPreview(): void
    {
        $rows = (new MarketingRepository())->marketingRecipients(500);
        View::render('marketing/bulk', [
            'title' => 'Checked customer list',
            'activeNav' => 'campaigns',
            'count' => count($rows),
            'sample' => array_slice($rows, 0, 20),
        ]);
    }
}
