<?php
declare(strict_types=1);

/**
 * Releasing of budget → Academic years: create a year (optionally with a budget per program at the suggested amount),
 * make one current (new scholars start in it), delete an unused one; per year, what each program has and what is missing.
 */
final class AcademicYearController
{
    public function index(): void
    {
        $years = AcademicYear::all();
        $overview = [];
        foreach ($years as $y) $overview[$y['label']] = AcademicYear::overview($y['label']);
        $suggested = AcademicYear::suggestNext();
        $hasCurrent = AcademicYear::current() !== null;
        render('admin/academic_years', compact('years', 'overview', 'suggested', 'hasCurrent'), 'Academic years');
    }

    public function save(): void
    {
        require_post('admin/academic-years');
        $r = AcademicYear::create(post_str('label'), post_bool('make_current'), post_bool('with_budgets'), admin_id());
        if (!$r['ok']) { flash('danger', $r['error']); redirect('admin/academic-years'); }
        $n = count($r['budgets']);
        $total = array_sum(array_column($r['budgets'], 'total'));
        flash('success', "Created {$r['label']}" . ($r['current'] ? ' (now the current academic year)' : '')
            . ($n ? " and $n budget" . ($n === 1 ? '' : 's') . ' (' . peso($total) . ')' : '') . '.');
        redirect('admin/academic-years');
    }

    public function setCurrent(): void
    {
        require_post('admin/academic-years');
        $r = AcademicYear::setCurrent(post_int('id'), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? "{$r['label']} is now the current academic year. New scholars start in its 1st Semester." : $r['error']);
        redirect('admin/academic-years');
    }

    public function delete(): void
    {
        require_post('admin/academic-years');
        $r = AcademicYear::delete(post_int('id'), admin_id());
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? "Academic year {$r['label']} deleted." : $r['error']);
        redirect('admin/academic-years');
    }
}
