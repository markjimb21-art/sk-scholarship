<?php
declare(strict_types=1);

/**
 * Seats of an academic-year intake (decision D5, docs/CHANGE_PLAN_5.md §6): returning scholars, pending renewals and
 * new scholars share the program's slots. Program 2 (2027-2028) gets 300 slots: 120 returning, 15 renewals waiting
 * for a decision and 165 new — so nobody else can be approved.
 */
final class SeatsTest extends DatabaseTestCase
{
    private const RETURNING = 120, PENDING = 15, NEW = 165;

    protected function setUp(): void
    {
        $pdo = Database::conn();
        $pdo->exec('UPDATE scholarship_programs SET slots_available = 300 WHERE id = 2');
        $users = $applicants = $apps = $scholars = $terms = $requests = [];
        $termId = 1000;
        for ($n = 1; $n <= self::RETURNING + self::PENDING + self::NEW; $n++) {
            $id = 1000 + $n;
            $kind = $n <= self::RETURNING ? 'returning' : ($n <= self::RETURNING + self::PENDING ? 'pending' : 'new');
            $origin = $kind === 'new' ? 2 : 1;
            $users[] = "($id, (SELECT id FROM roles WHERE role_name = 'applicant'), 'Seat $n', 'seat$n@test.local', 'x', 1, 1)";
            $applicants[] = "($id, $id)";
            $apps[] = "($id, 'APP-SEAT-$n', $id, $origin, 'Approved', NOW())";
            $scholars[] = "($id, 'SCH-SEAT-$n', $id, $id, $origin, CURDATE(), 'Active')";
            if ($kind === 'new') {
                $terms[] = '(' . ++$termId . ", $id, 2, '2027-2028', '1st Semester', 'approval', 'Waiting for Requirements')";
                continue;
            }
            $terms[] = '(' . ++$termId . ", $id, 1, '2026-2027', '1st Semester', 'approval', 'Released')";
            $terms[] = '(' . ++$termId . ", $id, 1, '2026-2027', '2nd Semester', 'continuation', 'Released')";
            if ($kind === 'returning') {
                $terms[] = '(' . ++$termId . ", $id, 2, '2027-2028', '1st Semester', 'renewal', 'Waiting for Requirements')";
                $requests[] = "($id, 2, " . ($termId - 1) . ", '2027-2028', '1st Semester', 'Approved', NOW())";
            } else {
                $requests[] = "($id, 2, $termId, '2027-2028', '1st Semester', 'Submitted', NOW())";
            }
        }
        $pdo->exec('INSERT INTO users (id, role_id, full_name, email, password_hash, is_active, email_verified) VALUES ' . implode(',', $users));
        $pdo->exec('INSERT INTO applicants (id, user_id) VALUES ' . implode(',', $applicants));
        $pdo->exec('INSERT INTO applications (id, application_code, applicant_id, program_id, status, submitted_at) VALUES ' . implode(',', $apps));
        $pdo->exec('INSERT INTO scholars (id, scholar_code, applicant_id, application_id, program_id, approval_date, status) VALUES ' . implode(',', $scholars));
        $pdo->exec('INSERT INTO scholar_terms (id, scholar_id, program_id, academic_year, semester, opened_via, claim_status) VALUES ' . implode(',', $terms));
        $pdo->exec('INSERT INTO renewal_requests (scholar_id, program_id, from_term_id, academic_year, semester, status, submitted_at) VALUES ' . implode(',', $requests));
        // One more applicant, Interviewed for program 2 (Ivy, applicant 6, applied to program 1 last year).
        $pdo->exec("INSERT INTO applications (id, application_code, applicant_id, program_id, status, submitted_at, qualified_at)
                    VALUES (2000, 'APP-SEAT-NEXT', 6, 2, 'Interviewed', NOW(), NOW())");
    }

    protected function tearDown(): void
    {
        self::rebuild();
    }

    public function testReturningPendingAndNewShareTheSlots(): void
    {
        $this->assertSame(['slots' => 300, 'returning' => 120, 'new' => 165, 'pending' => 15, 'used' => 285, 'free' => 0], Program::seats(2));
        $this->assertSame('Slots 300 · Returning 120 (+15 pending renewal) · New approved 165 · Free 0', Program::seatsLine(Program::seats(2)));
        $this->assertSame(285, Program::slotsUsed(2), 'pending renewals are not counted as used');
        $this->assertSame([0, 136], [Program::seats(1)['returning'], Program::seats(1)['new']],
            'program 1 (2026-2027) keeps its seats: Scott and the 135 who started there, now returning or pending in 2027-2028');
    }

    public function testOneMoreNewApprovalIsBlocked(): void
    {
        $r = Application::approve(2000, 1);
        $this->assertFalse($r['ok']);
        $this->assertSame('All 300 slots are taken: 120 returning (+15 pending renewal), 165 new approved. Nobody else can be approved unless a slot opens.', $r['error']);
        $this->assertSame('Interviewed', self::value('SELECT status FROM applications WHERE id = 2000'));
    }

    public function testAfterTheDeadlinePendingRenewalsStillHoldSeatsAndScholarsWhoNeverSubmittedHoldNone(): void
    {
        $pdo = Database::conn();
        $pdo->exec("INSERT INTO term_schedules (program_id, academic_year, semester, purpose, opens_at, submission_deadline, status, created_by)
                    VALUES (2, '2027-2028', '1st Semester', 'renewal', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 1 DAY, 'Open', 1)");
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Released' WHERE id = 2");   // Scott could have renewed, never did
        $this->assertSame(['SCH-TEST-0001'], array_column(Renewal::didNotRenew()['end'], 'scholar_code'));
        $this->assertSame(['pending' => 15, 'free' => 0], array_intersect_key(Program::seats(2), ['pending' => 0, 'free' => 0]));
        $this->assertFalse(Application::approve(2000, 1)['ok']);
    }

    public function testDecidingRenewalsMovesSeats(): void
    {
        $first = (int)self::value("SELECT id FROM renewal_requests WHERE program_id = 2 AND status = 'Submitted' ORDER BY id LIMIT 1");
        $this->assertTrue(Renewal::approve($first, 3)['ok']);
        $this->assertSame([121, 14, 0], [Program::seats(2)['returning'], Program::seats(2)['pending'], Program::seats(2)['free']], 'pending → returning');

        // A pending renewal of a scholar whose scholarship ended frees its seat.
        $sid = (int)self::value("SELECT scholar_id FROM renewal_requests WHERE program_id = 2 AND status = 'Submitted' ORDER BY id LIMIT 1");
        $this->assertTrue(Scholar::end($sid, 'Dropped out', 1)['ok']);
        $this->assertSame([13, 1], [Program::seats(2)['pending'], Program::seats(2)['free']]);

        $r = Application::approve(2000, 1);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame([121, 166, 0], [Program::seats(2)['returning'], Program::seats(2)['new'], Program::seats(2)['free']]);
    }

    public function testAReturningScholarEndedInTheirFirstSemesterThereFreesTheSeat(): void
    {
        $r = Scholar::end(1001, 'Dropped out', 1);   // returning, only 2027-2028 1st Semester in program 2
        $this->assertSame(['ok' => true, 'slot_freed' => true, 'program_id' => 2], $r, 'the seat of the current program');
        $this->assertSame([119, 1], [Program::seats(2)['returning'], Program::seats(2)['free']]);
        $this->assertSame(0, Program::seats(1)['returning'] + Program::seats(1)['pending'], 'program 1 is not affected');
    }

    /**
     * Analytics: projected = 120 returning + 15 pending + 165 new + expected approvals, capped at 300; the 15 pending
     * have no semester in program 2 yet, so projected need = committed (285 scholars × 2 semesters × ₱4,000)
     * + 15 × 2 × ₱4,000 = 300 × ₱8,000.
     */
    public function testAnalyticsCountReturningAndPendingScholars(): void
    {
        $p = AnalyticsFunnel::projectedScholars(2);
        $this->assertEqualsWithDelta(300.0, $p['value'], 1e-9);
        $this->assertEqualsWithDelta(15.0, $p['not_committed'], 1e-9);
        $this->assertSame(0, AnalyticsFunnel::slotsRemaining(2));
        $this->assertEqualsWithDelta(285 * 2 * 4000.0, AnalyticsBudget::committed(2, '2027-2028'), 0.001);
        $this->assertEqualsWithDelta(300 * 8000.0, AnalyticsBudget::projectedNeed(2, '2027-2028'), 0.001);
    }

    public function testTheRenewalPageWarnsWhenApprovingGoesOverTheSlots(): void
    {
        $req = Renewal::find((int)self::value("SELECT id FROM renewal_requests WHERE program_id = 2 AND status = 'Submitted' ORDER BY id LIMIT 1"));
        $this->assertNull(Renewal::seatWarning($req), '120 + 165 + 1 = 286 of 300');
        Database::conn()->exec('UPDATE scholarship_programs SET slots_available = 285 WHERE id = 2');
        $this->assertSame('Approving this renewal makes 286 scholars in Other Test Program for 285 slots (121 returning with this one, 165 new). '
            . 'Renewals are not blocked — decide knowing the slots are over.', Renewal::seatWarning($req));
        $this->assertTrue(Renewal::approve((int)$req['id'], 3)['ok'], 'never blocked');
    }
}
