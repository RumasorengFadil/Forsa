<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require_login();

use Forsa\Dashboard\DashboardService;

$selection = isset($_GET['selection']) ? (string) $_GET['selection'] : null;

$service = new DashboardService();
$data = $service->buildDashboard($selection);

json_success($data);
