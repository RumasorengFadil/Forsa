<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require_login();

use Forsa\Dashboard\DashboardService;

$service = new DashboardService();
json_success($service->historyOptions());
