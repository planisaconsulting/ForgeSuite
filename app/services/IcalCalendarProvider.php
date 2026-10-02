<?php

declare(strict_types=1);

namespace App\Services;

/**
 * iCalendar text for a subscription or a single download.
 * There is no Google or Microsoft connector in this release.
 */
final class IcalCalendarProvider implements CalendarProviderInterface
{
    public function name(): string
    {
        return 'ical';
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    public function render(array $events, string $timezone): string
    {
        $timezone = $timezone !== '' ? $timezone : 'Africa/Johannesburg';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sign-Forge//ERP//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-TIMEZONE:' . $this->text($timezone),
            'BEGIN:VTIMEZONE',
            'TZID:' . $this->text($timezone),
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0200',
            'TZNAME:SAST',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
        foreach ($events as $event) {
            $lines = array_merge($lines, $this->event($event, $timezone));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<string, mixed> $event
     * @return list<string>
     */
    private function event(array $event, string $timezone): array
    {
        $uid = (string) ($event['uid'] ?? '');
        $date = (string) ($event['date'] ?? '');
        $time = trim((string) ($event['time'] ?? ''));
        $stamp = gmdate('Ymd\THis\Z');
        $status = strtoupper((string) ($event['status'] ?? ''));
        $cancelled = in_array($status, ['CANCELLED', 'CANCELED'], true);
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $this->text($uid),
            'DTSTAMP:' . $stamp,
        ];
        if ($time === '') {
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $date);
            $lines[] = 'DTEND;VALUE=DATE:' . gmdate('Ymd', strtotime($date . ' +1 day') ?: time());
        } else {
            $start = str_replace('-', '', $date) . 'T' . str_replace(':', '', substr($time, 0, 8));
            if (strlen($start) === 13) {
                $start .= '00';
            }
            $lines[] = 'DTSTART;TZID=' . $this->text($timezone) . ':' . $start;
            $end = date('Ymd\THis', strtotime($date . ' ' . $time . ' +1 hour') ?: time());
            $lines[] = 'DTEND;TZID=' . $this->text($timezone) . ':' . $end;
        }
        $lines[] = 'SUMMARY:' . $this->text((string) ($event['summary'] ?? 'Work'));
        $description = trim((string) ($event['description'] ?? ''));
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . $this->text($description);
        }
        $location = trim((string) ($event['location'] ?? ''));
        if ($location !== '') {
            $lines[] = 'LOCATION:' . $this->text($location);
        }
        if (!empty($event['url'])) {
            $lines[] = 'URL:' . $this->text((string) $event['url']);
        }
        $lines[] = 'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function text(string $value): string
    {
        return str_replace(["\\", "\r\n", "\n", ",", ";"], ["\\\\", "\\n", "\\n", "\\,", "\\;"], $value);
    }
}
