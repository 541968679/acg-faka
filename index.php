<?php
declare(strict_types=1);

/**
 * 开启DEBUG
 */
define('DEBUG', filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN));
require("kernel/Kernel.php");
