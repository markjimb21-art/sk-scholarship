<?php
declare(strict_types=1);

/**
 * Interview slot suggestions (B3, A4): existing schedules with room first, then free 2-hour blocks on working days after
 * the application period — never on holidays, another program's interview day, or over an existing schedule.
 */
final class InterviewSlots
{
    /** Interview blocks offered as suggestions (Mon–Sat, lunch hour skipped). */
    public const SUGGESTION_BLOCKS = [['08:00', '10:00'], ['10:00', '12:00'], ['13:00', '15:00'], ['15:00', '17:00']];

    /** How far ahead suggestions may reach, in days (Settings → interview_suggestion_window_days). */
    public const WINDOW_MIN_DAYS = 7;
    public const WINDOW_MAX_DAYS = 180;
    public const WINDOW_DEFAULT_DAYS = 60;

    public static function suggestionWindowDays(): int
    {
        return self::clampWindowDays((int)setting('interview_suggestion_window_days', (string)self::WINDOW_DEFAULT_DAYS));
    }

    /** $days kept within WINDOW_MIN_DAYS..WINDOW_MAX_DAYS. */
    public static function clampWindowDays(int $days): int
    {
        return max(self::WINDOW_MIN_DAYS, min(self::WINDOW_MAX_DAYS, $days));
    }

    /** Holidays in [$from, $to] as [Y-m-d => name]. */
    public static function holidays(string $from, string $to): array
    {
        $stmt = Database::conn()->prepare("SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Ranked interview slots for a program — a suggestion only; the admin can still pick any time. ONE function for the
     * New-schedule form, the reschedule page and the applicant's slot picker. In order:
     *   1. existing Open schedules of THIS program, from tomorrow, that still have free capacity (soonest first);
     *   2. new free 2-hour blocks (SUGGESTION_BLOCKS), Monday–Saturday, from the day after application_end (or tomorrow),
     *      skipping holidays, dates on which ANOTHER program already has an interview day, and blocks that overlap any
     *      Open schedule.
     * Window end = the later of application_end and today + suggestionWindowDays() (so reschedules late in the cycle still
     * get suggestions). $withSchedules = false leaves out group 1 (e.g. for a form that creates a new schedule).
     * Each item: ['kind' => 'schedule'|'block', 'schedule_id' => int|null, 'date','start','end' (H:i), 'venue' => ?string,
     * 'free' => ?int, 'label']. Returns [] with $why set when there is nothing to suggest.
     */
    public static function suggestSlots(int $programId, int $limit = 5, ?string &$why = null, bool $withSchedules = true): array
    {
        $why = null;
        $program = Program::find($programId);
        if (!$program) { $why = 'Program not found.'; return []; }
        $days = self::suggestionWindowDays();
        $tomorrow = new DateTimeImmutable('tomorrow');
        $end = new DateTimeImmutable($program['application_end']);
        $last = max($end, new DateTimeImmutable('today'))->modify("+$days days");
        $firstBlock = max($end->modify('+1 day'), $tomorrow);
        $range = [$tomorrow->format('Y-m-d'), $last->format('Y-m-d')];
        $pdo = Database::conn();
        $out = [];

        if ($withSchedules) {
            $stmt = $pdo->prepare(
                "SELECT s.id, s.interview_date, s.start_time, s.end_time, s.venue, s.max_slots, COUNT(ia.id) AS assigned
                 FROM interview_schedules s LEFT JOIN interview_assignments ia ON ia.schedule_id = s.id
                 WHERE s.program_id = ? AND s.status = 'Open' AND s.interview_date BETWEEN ? AND ?
                 GROUP BY s.id HAVING assigned < s.max_slots ORDER BY s.interview_date, s.start_time"
            );
            $stmt->execute([$programId, ...$range]);
            foreach ($stmt->fetchAll() as $s) {
                $free = (int)$s['max_slots'] - (int)$s['assigned'];
                $out[] = [
                    'kind' => 'schedule', 'schedule_id' => (int)$s['id'], 'date' => $s['interview_date'],
                    'start' => substr($s['start_time'], 0, 5), 'end' => substr($s['end_time'], 0, 5), 'venue' => $s['venue'], 'free' => $free,
                    'label' => date('D, M j', strtotime($s['interview_date'])) . ' · ' . date('g:i', strtotime($s['start_time']))
                        . '–' . date('g:i A', strtotime($s['end_time'])) . " · {$s['venue']} ($free open)",
                ];
                if (count($out) >= $limit) return $out;
            }
        }

        $stmt = $pdo->prepare("SELECT interview_date, start_time, end_time, program_id FROM interview_schedules
                               WHERE status = 'Open' AND interview_date BETWEEN ? AND ?");
        $stmt->execute($range);
        $busy = [];
        $otherProgramDays = [];
        foreach ($stmt->fetchAll() as $s) {
            $busy[$s['interview_date']][] = [substr($s['start_time'], 0, 5), substr($s['end_time'], 0, 5)];
            if ((int)$s['program_id'] !== $programId) $otherProgramDays[$s['interview_date']] = true;
        }
        $holidays = self::holidays(...$range);

        for ($d = $firstBlock; $d <= $last && count($out) < $limit; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if ((int)$d->format('N') === 7 || isset($holidays[$date]) || isset($otherProgramDays[$date])) continue;
            foreach (self::SUGGESTION_BLOCKS as [$from, $to]) {
                foreach ($busy[$date] ?? [] as [$bFrom, $bTo]) {
                    if ($bFrom < $to && $bTo > $from) continue 2;   // overlaps an existing schedule
                }
                $out[] = [
                    'kind' => 'block', 'schedule_id' => null, 'date' => $date, 'start' => $from, 'end' => $to, 'venue' => null, 'free' => null,
                    'label' => $d->format('D, M j') . ' · ' . date('g:i', strtotime($from)) . '–' . date('g:i A', strtotime($to)) . ' (new)',
                ];
                if (count($out) >= $limit) break;
            }
        }
        if (!$out) {
            $why = $firstBlock > $last
                ? "No suggestions: nothing free up to {$last->format('M j, Y')} ($days-day window, Settings)."
                : "No open schedule or free 2-hour block up to {$last->format('M j, Y')} ($days-day window, Settings).";
        }
        return $out;
    }

    /** Is $item (from suggestSlots) still one of the current top suggestions for the program? (server-side check of a pick) */
    public static function isCurrentSuggestion(int $programId, array $item, int $limit): ?array
    {
        foreach (self::suggestSlots($programId, $limit) as $s) {
            if ($s['kind'] === $item['kind'] && $s['date'] === $item['date'] && $s['start'] === $item['start']
                && (int)$s['schedule_id'] === (int)($item['schedule_id'] ?? 0)) return $s;
        }
        return null;
    }
}
