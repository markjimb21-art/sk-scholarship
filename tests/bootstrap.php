<?php
declare(strict_types=1);

/*
 * PHPUnit bootstrap: loads the application exactly like public/index.php does, but against the separate test database
 * and a temporary storage folder. Refuses to run unless DB_NAME is the test database.
 */
require __DIR__ . '/support/TestDatabase.php';
TestDatabase::requireTestEnvironment();

// Uploads and logs go to a temporary folder, never into the real storage/.
$storage = sys_get_temp_dir() . '/sk_scholarship_test_storage';
if (!is_dir($storage)) mkdir($storage, 0700, true);
putenv("STORAGE_PATH=$storage");
$_ENV['STORAGE_PATH'] = $storage;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Notification.php';
require_once __DIR__ . '/../core/Otp.php';
require_once __DIR__ . '/../vendor/autoload.php';
foreach (glob(__DIR__ . '/../app/models/*.php') as $model) require_once $model;

require __DIR__ . '/support/TestServer.php';
require __DIR__ . '/support/HttpClient.php';
require __DIR__ . '/support/DatabaseTestCase.php';

// The seed is loaded once here; test classes that change data call TestDatabase::rebuild() themselves.
TestDatabase::rebuild();
