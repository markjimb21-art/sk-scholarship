-- Migration: staff roles (docs/PROCESS.md "Roles").
--
-- reviewer   screening, interviews, decisions — no budget, claims or releases
-- treasurer  budget, claims, releases, renewals, scholars — no applicant review
-- admin      everything, including users and settings (unchanged)
--
-- What each role may open is decided in code (AdminController::ROUTES, default deny). Admins give staff these roles
-- under Users. Safe to run once (INSERT IGNORE; role_name is unique).

INSERT IGNORE INTO `roles` (`role_name`, `description`) VALUES
  ('reviewer', 'Screening, interviews and decisions'),
  ('treasurer', 'Budget, claims, releases and renewals');
