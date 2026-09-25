<?php

declare(strict_types=1);

namespace App\Services\Interviews;

use App\Models\Interview;

/** Builds an RFC 5545 calendar file for an interview invitation (EF-903). */
class IcsCalendar
{
    /** Returns the .ics document (CRLF line endings, folded at 75 octets). */
    public function build(Interview $interview): string
    {
        $application = $interview->application;
        $application->loadMissing('offer.user');
        $offer = $application->offer;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RecruteSmart//Interview//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:interview-'.$interview->id.'@recrutesmart.local',
            'DTSTAMP:'.self::stamp(now()->utc()),
            'DTSTART:'.self::stamp($interview->starts_at->utc()),
            'DTEND:'.self::stamp($interview->starts_at->utc()->addMinutes($interview->duration_minutes)),
            'SUMMARY:'.self::escape('Entretien — '.$offer->title),
            'DESCRIPTION:'.self::escape('Entretien pour l\'offre '.$offer->title),
        ];

        if ($interview->location_or_link !== null && $interview->location_or_link !== '') {
            $lines[] = 'LOCATION:'.self::escape($interview->location_or_link);
        }

        $lines[] = 'ORGANIZER;CN='.self::escape($offer->user->name).':mailto:'.$offer->user->email;
        $lines[] = 'ATTENDEE;CN='.self::escape($application->full_name).':mailto:'.$application->email;

        foreach ($interview->participants ?? [] as $participant) {
            $lines[] = 'ATTENDEE;CN='.self::escape($participant).':mailto:'.$participant;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** 20260925T143000Z */
    private static function stamp(\DateTimeInterface $date): string
    {
        return $date->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }

    /** Folds a content line beyond 75 octets (RFC 5545 §3.1). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = mb_strcut($line, 0, 74, 'UTF-8');
        $rest = substr($line, strlen($folded));

        while (strlen($rest) > 74) {
            $chunk = mb_strcut($rest, 0, 73, 'UTF-8');
            $folded .= "\r\n ".$chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return $folded."\r\n ".$rest;
    }
}
