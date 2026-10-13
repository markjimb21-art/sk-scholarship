<?php
declare(strict_types=1);

// Tests only (ErrorAfterOutputTest). With SK_TEST_FAULTS=1 the test router runs this file after public/index.php has
// printed the whole page. When the request asks for it, it fails right there, the way a late error in a page would.
// The application itself contains no such hook.
$fault = $_SERVER['HTTP_X_TEST_FAULT'] ?? '';
if ($fault === 'exception') throw new RuntimeException('Injected test fault after the page was printed');
if ($fault === 'fatal') trigger_error('Injected fatal test fault after the page was printed', E_USER_ERROR);
