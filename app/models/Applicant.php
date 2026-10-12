<?php
declare(strict_types=1);

/** The applicant's profile (one per account): personal information, education and family background. */
final class Applicant
{
    /** The applicants row for a user account, created if it is missing (older accounts). */
    public static function forUser(int $userId): array
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare("SELECT * FROM applicants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($row = $stmt->fetch()) return $row;

        $pdo->prepare("INSERT INTO applicants (user_id) VALUES (?)")->execute([$userId]);
        return ['id' => (int)$pdo->lastInsertId(), 'user_id' => $userId];
    }

    public static function personalInfo(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT p.*, pr.purok_name
             FROM applicants_personal_information p
             LEFT JOIN puroks pr ON pr.id = p.purok_id
             WHERE p.applicant_id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function education(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT * FROM education_records WHERE applicant_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function familyBackground(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare("SELECT * FROM family_background WHERE applicant_id = ? LIMIT 1");
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    public static function withUser(int $applicantId): ?array
    {
        $stmt = Database::conn()->prepare(
            "SELECT a.*, u.full_name, u.email, u.contact_number
             FROM applicants a
             JOIN users u ON u.id = a.user_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$applicantId]);
        return $stmt->fetch() ?: null;
    }

    /* ---------------- Saving the form (A2 autosave) ---------------- */

    /**
     * Composes the stored Complete Address as "<unit> / <purok/subdivision> / BARANGAY ESTEFANIA / BACOLOD CITY".
     * The purok name is looked up server-side from the `puroks` table by id (never taken as free text from the
     * client), and the barangay/city suffix is ALWAYS this hardcoded constant pair — so a manually crafted
     * request can neither spoof the purok name nor register an address outside the scholarship's service area.
     */
    private static function composeAddress(string $unit, string $purokName): string
    {
        $parts = array_values(array_filter([up($unit), $purokName], fn($v) => $v !== ''));
        $parts[] = ADDRESS_BARANGAY;
        $parts[] = ADDRESS_CITY;
        return implode(' / ', $parts);
    }

    /** Unit/house-number part of a stored address, for pre-filling the form (legacy free-form addresses give ''). */
    public static function addressUnit(string $full): string
    {
        $suffix = ' / ' . ADDRESS_BARANGAY . ' / ' . ADDRESS_CITY;
        if (!str_ends_with(strtoupper($full), strtoupper($suffix))) return '';
        $parts = array_map('trim', explode(' / ', substr($full, 0, strlen($full) - strlen($suffix)), 2));
        return $parts[0] ?? '';
    }

    /** A body measurement from the form: a number within [$min, $max] rounded to 1 decimal, else null. */
    private static function measurement($value, float $min, float $max): ?float
    {
        $v = trim((string)$value);
        if (!is_numeric($v) || (float)$v < $min || (float)$v > $max) return null;
        return round((float)$v, 1);
    }

    public static function savePersonal(int $applicantId, array $post): void
    {
        // date_of_birth is NOT NULL; autosave fires before the applicant reaches it, so wait until it is given.
        if (trim((string)($post['date_of_birth'] ?? '')) === '') return;
        $purokId = ($post['purok_id'] ?? '') !== '' ? (int)$post['purok_id'] : null;
        $bloodType = (string)($post['blood_type'] ?? '');
        $citizenship = mb_substr(up($post['citizenship'] ?? ''), 0, 50);
        Database::conn()->prepare(
            "INSERT INTO applicants_personal_information
                (applicant_id, first_name, middle_name, last_name, suffix, date_of_birth, complete_address, purok_id, contact_number,
                 height_cm, weight_kg, blood_type, religion, citizenship, sex)
             VALUES (:applicant_id, :first_name, :middle_name, :last_name, :suffix, :date_of_birth, :complete_address, :purok_id, :contact_number,
                 :height_cm, :weight_kg, :blood_type, :religion, :citizenship, :sex)
             ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name), middle_name = VALUES(middle_name), last_name = VALUES(last_name),
                suffix = VALUES(suffix), date_of_birth = VALUES(date_of_birth), complete_address = VALUES(complete_address),
                purok_id = VALUES(purok_id), contact_number = VALUES(contact_number),
                height_cm = VALUES(height_cm), weight_kg = VALUES(weight_kg),
                blood_type = VALUES(blood_type), religion = VALUES(religion), citizenship = VALUES(citizenship), sex = VALUES(sex)"
        )->execute([
            // Only a listed blood type is stored; anything else (including a crafted value) is saved as "not given".
            // Height 50–250 cm and weight 20–300 kg; blank or anything outside (or not a number) is stored as "not given".
            'height_cm'        => self::measurement($post['height_cm'] ?? '', 50, 250),
            'weight_kg'        => self::measurement($post['weight_kg'] ?? '', 20, 300),
            'blood_type'       => in_array($bloodType, BLOOD_TYPES, true) ? $bloodType : null,
            'religion'         => mb_substr(up($post['religion'] ?? ''), 0, 80) ?: null,
            'citizenship'      => $citizenship !== '' ? $citizenship : 'FILIPINO',
            'sex'              => in_array($post['sex'] ?? '', SEX_OPTIONS, true) ? $post['sex'] : null,
            'applicant_id'     => $applicantId,
            'first_name'       => up($post['first_name'] ?? ''),
            'middle_name'      => up($post['middle_name'] ?? ''),
            'last_name'        => up($post['last_name'] ?? ''),
            'suffix'           => up($post['suffix'] ?? ''),
            'date_of_birth'    => $post['date_of_birth'],
            'complete_address' => self::composeAddress($post['address_unit'] ?? '', Lookup::purokName($purokId)),
            'purok_id'         => $purokId,
            'contact_number'   => digits_only($post['contact_number'] ?? ''),
        ]);
    }

    /** Education: one record per applicant, UPDATED in place (never deleted); key-field changes go to record_history. */
    public static function saveEducation(int $applicantId, array $post, int $userId = 0): void
    {
        // GWA on the Philippine 1.00 (highest) – 5.00 scale; anything else is stored as NULL.
        $ga = trim((string)($post['general_average'] ?? ''));
        $ga = (is_numeric($ga) && (float)$ga >= 1 && (float)$ga <= 5) ? round((float)$ga, 2) : null;
        $yearLevel = in_array($post['year_level'] ?? '', YEAR_LEVELS, true) ? $post['year_level'] : '1st Year';

        $pdo = Database::conn();
        $old = self::education($applicantId);
        $values = [
            'school_name'                  => trim($post['school_name'] ?? ''),
            'year_level'                   => $yearLevel,
            'year_level_other'             => trim($post['year_level_other'] ?? ''),
            'course_name'                  => trim($post['course_name'] ?? ''),
            'major'                        => trim($post['major'] ?? '') ?: null,
            'general_average'              => $ga,
            'previous_scholarship'         => !empty($post['previous_scholarship']) ? 1 : 0,
            'previous_scholarship_details' => trim($post['previous_scholarship_details'] ?? ''),
            'current_scholarship'          => !empty($post['current_scholarship']) ? 1 : 0,
            'current_scholarship_details'  => trim($post['current_scholarship_details'] ?? ''),
        ];
        if ($old) {
            $assign = implode(', ', array_map(static fn($k) => "`$k` = :$k", array_keys($values)));
            $pdo->prepare("UPDATE education_records SET $assign WHERE id = :id")->execute($values + ['id' => (int)$old['id']]);
            RecordHistory::record('education_records', (int)$old['id'], $applicantId, $old, $values, $userId);
            return;
        }
        $pdo->prepare(
            "INSERT INTO education_records
                (applicant_id, school_name, year_level, year_level_other, course_name, major, general_average,
                 previous_scholarship, previous_scholarship_details, current_scholarship, current_scholarship_details)
             VALUES (:applicant_id, :school_name, :year_level, :year_level_other, :course_name, :major, :general_average,
                     :previous_scholarship, :previous_scholarship_details, :current_scholarship, :current_scholarship_details)"
        )->execute(['applicant_id' => $applicantId] + $values);
    }

    /** Family background: upserted in place (one row per applicant); income changes go to record_history. */
    public static function saveFamily(int $applicantId, array $post, int $userId = 0): void
    {
        $status = static fn($v) => in_array($v, ['Available', 'Deceased', 'Unknown', 'Not Applicable'], true) ? $v : 'Available';
        $fi = max(0.0, (float)($post['father_income'] ?? 0));
        $mi = max(0.0, (float)($post['mother_income'] ?? 0));
        $old = self::familyBackground($applicantId);
        if ($old) {
            RecordHistory::record('family_background', (int)$old['id'], $applicantId, $old,
                ['father_income' => $fi, 'mother_income' => $mi, 'total_family_income' => $fi + $mi], $userId);
        }
        Database::conn()->prepare(
            "INSERT INTO family_background
                (applicant_id, father_name, father_age, father_occupation, father_address, father_contact, father_income, father_status,
                 mother_name, mother_age, mother_occupation, mother_address, mother_contact, mother_income, mother_status,
                 total_family_income, family_size)
             VALUES (:applicant_id, :father_name, :father_age, :father_occupation, :father_address, :father_contact, :father_income, :father_status,
                     :mother_name, :mother_age, :mother_occupation, :mother_address, :mother_contact, :mother_income, :mother_status,
                     :total_family_income, :family_size)
             ON DUPLICATE KEY UPDATE
                father_name = VALUES(father_name), father_age = VALUES(father_age), father_occupation = VALUES(father_occupation),
                father_address = VALUES(father_address), father_contact = VALUES(father_contact),
                father_income = VALUES(father_income), father_status = VALUES(father_status),
                mother_name = VALUES(mother_name), mother_age = VALUES(mother_age), mother_occupation = VALUES(mother_occupation),
                mother_address = VALUES(mother_address), mother_contact = VALUES(mother_contact),
                mother_income = VALUES(mother_income), mother_status = VALUES(mother_status),
                total_family_income = VALUES(total_family_income), family_size = VALUES(family_size)"
        )->execute([
            'applicant_id'        => $applicantId,
            'father_name'         => trim($post['father_name'] ?? ''),
            'father_age'          => ($post['father_age'] ?? '') !== '' ? (int)$post['father_age'] : null,
            'father_occupation'   => trim($post['father_occupation'] ?? ''),
            'father_address'      => trim($post['father_address'] ?? ''),
            'father_contact'      => digits_only($post['father_contact'] ?? ''),
            'father_income'       => $fi,
            'father_status'       => $status($post['father_status'] ?? ''),
            'mother_name'         => trim($post['mother_name'] ?? ''),
            'mother_age'          => ($post['mother_age'] ?? '') !== '' ? (int)$post['mother_age'] : null,
            'mother_occupation'   => trim($post['mother_occupation'] ?? ''),
            'mother_address'      => trim($post['mother_address'] ?? ''),
            'mother_contact'      => digits_only($post['mother_contact'] ?? ''),
            'mother_income'       => $mi,
            'mother_status'       => $status($post['mother_status'] ?? ''),
            'total_family_income' => $fi + $mi,
            'family_size'         => ($post['family_size'] ?? '') !== '' ? (int)$post['family_size'] : null,
        ]);
    }
}
