<?php
declare(strict_types=1);

/**
 * Eligibility check (B2, also shown in B5): one pass/fail row per program rule — age, family income, GWA, Certificate of
 * Residency — computed from what the applicant submitted.
 */
final class ApplicationEligibility
{
    /**
     * Rows: ['label','rule','value','result' => 'Pass'|'Fail'|'Not provided'|'No limit'].
     * Nothing is decided automatically — staff see the rows next to the buttons.
     */
    public static function evaluate(array $app): array
    {
        $personal  = $app['personal']  ?? Applicant::personalInfo((int)$app['applicant_id']);
        $education = $app['education'] ?? Applicant::education((int)$app['applicant_id']);
        $family    = $app['family']    ?? Applicant::familyBackground((int)$app['applicant_id']);
        $documents = $app['documents'] ?? Document::byApplication((int)$app['id']);
        $rows = [];

        // 1. Age on the submission date.
        $maxAge = (int)($app['max_age'] ?? 0);
        $onDate = $app['submitted_at'] ?: date('Y-m-d');
        $age = !empty($personal['date_of_birth'])
            ? (new DateTime($personal['date_of_birth']))->diff(new DateTime(substr((string)$onDate, 0, 10)))->y : null;
        $rows[] = [
            'label' => 'Age', 'rule' => $maxAge > 0 ? "at most $maxAge on the submission date" : 'no limit',
            'value' => $age === null ? '—' : (string)$age,
            'result' => $maxAge <= 0 ? 'No limit' : ($age === null ? 'Not provided' : ($age <= $maxAge ? 'Pass' : 'Fail')),
        ];

        // 2. Total family income.
        $maxIncome = (float)($app['max_family_income'] ?? 0);
        $income = $family ? (float)$family['total_family_income'] : null;
        $rows[] = [
            'label' => 'Total family income (monthly)', 'rule' => $maxIncome > 0 ? 'at most ' . peso($maxIncome) : 'no limit',
            'value' => peso($income, '—'),
            'result' => $maxIncome <= 0 ? 'No limit' : ($income === null ? 'Not provided' : ($income <= $maxIncome ? 'Pass' : 'Fail')),
        ];

        // 3. GWA (Philippine scale: 1.00 highest, so a LOWER number is better).
        $minGwa = (float)($app['min_gpa'] ?? 0);
        $gwa = isset($education['general_average']) ? (float)$education['general_average'] : null;
        $rows[] = [
            'label' => 'GWA', 'rule' => $minGwa > 0 ? number_format($minGwa, 2) . ' or better (1.00 is highest)' : 'no limit',
            'value' => $gwa === null ? '—' : number_format($gwa, 2),
            'result' => $minGwa <= 0 ? 'No limit' : ($gwa === null ? 'Not provided' : ($gwa <= $minGwa ? 'Pass' : 'Fail')),
        ];

        // 4. Residency document present.
        $hasResidency = false;
        foreach ($documents as $d) {
            if ($d['document_type'] === 'Certificate of Residency' && $d['file_path'] !== '') $hasResidency = true;
        }
        $rows[] = [
            'label' => 'Certificate of Residency', 'rule' => 'uploaded',
            'value' => $hasResidency ? 'Uploaded' : 'Missing', 'result' => $hasResidency ? 'Pass' : 'Fail',
        ];

        $failed = count(array_filter($rows, static fn($r) => $r['result'] === 'Fail' || $r['result'] === 'Not provided'));
        return ['rows' => $rows, 'failed' => $failed, 'summary' => $failed === 0 ? 'Meets all rules' : "$failed rule(s) not met"];
    }
}
