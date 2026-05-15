<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Advisora - Modules Overview</title>
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; margin: 24px; }
  h1 { font-size: 22px; margin: 0 0 6px; color: #0f172a; }
  .meta { color: #475569; margin-bottom: 18px; }
  .module { margin-bottom: 14px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; }
  .module h2 { margin: 0 0 6px; font-size: 16px; color: #0b3a66; }
  .desc { margin: 0 0 6px; }
  ul { margin: 4px 0 0 18px; padding: 0; }
  li { margin: 2px 0; }
</style>
</head>
<body>
  <h1>Advisora - 6 Modules Overview</h1>
  <div class="meta">Generated on May 15, 2026. Concise summary of implemented major functionalities.</div>

  <div class="module">
    <h2>1) Strategies Module</h2>
    <p class="desc">This module manages strategic planning linked to projects, from creation to validation and strategic execution follow-up.</p>
    <ul>
      <li>Back-office strategy CRUD with filters (search, status, type), sorting, and pagination.</li>
      <li>SWOT matrix management (create/edit/delete and bulk item insert).</li>
      <li>Objective management, including TOWS objective creation and AI-assisted objective generation.</li>
      <li>Risk-based automatic status rules (pending/in-progress) and lock timestamp sync after approval.</li>
      <li>Decision workflow for admin/client actions (approve/reject paths).</li>
      <li>Strategy PDF generation and project recommendation flow with auto-generate options.</li>
    </ul>
  </div>

  <div class="module">
    <h2>2) Users Module</h2>
    <p class="desc">This module handles user administration, profile management, and secure authentication flows.</p>
    <ul>
      <li>Back-office user listing with search and sorting.</li>
      <li>User create/edit/delete with role-based usage, CSRF checks, and password hashing.</li>
      <li>Profile update with user image upload and replacement handling.</li>
      <li>Authentication features: login, register, logout, forgot password, and reset password.</li>
      <li>OTP verification flow (verify, resend, cancel) for stronger login security.</li>
      <li>Google OAuth entry/check and dedicated client profile views.</li>
    </ul>
  </div>

  <div class="module">
    <h2>3) Projects Module</h2>
    <p class="desc">This module manages project lifecycle, operational tasks, and decision tracking between client and back office.</p>
    <ul>
      <li>Front and back project listings with filters, status views, and dashboard insights.</li>
      <li>Project CRUD with permission rules by role and ownership.</li>
      <li>Decision history/versioning and automatic mapping from decision state to project status.</li>
      <li>Task board operations (add/edit/delete/move) with role-aware permissions.</li>
      <li>Decision feed endpoint and project voice-message support integration.</li>
      <li>Project dashboard PDF export and notification hooks for key events.</li>
    </ul>
  </div>

  <div class="module">
    <h2>4) Events Module</h2>
    <p class="desc">This module covers event publishing and end-to-end booking management for clients and managers.</p>
    <ul>
      <li>Event catalog with search filters and client-facing recommendations.</li>
      <li>Calendar JSON endpoint for event scheduling display.</li>
      <li>Back-office event CRUD for admin/manager roles.</li>
      <li>Client booking flow (create/edit/delete) with capacity checks and duplicate-booking guard.</li>
      <li>Booking workflow states (pending/accepted/refused) managed in back office.</li>
      <li>Client bookings list and PDF export of booking history.</li>
    </ul>
  </div>

  <div class="module">
    <h2>5) Investment Module</h2>
    <p class="desc">This module manages client investments linked to projects, including decision support and historical reporting.</p>
    <ul>
      <li>Client investment listing with filters and yearly chart aggregation.</li>
      <li>Investment creation (general or project-prefilled) with form validation and normalization.</li>
      <li>AI/analytics support for top project recommendations and per-investment prediction.</li>
      <li>Manage/edit/delete flows with lock rules when transactions are already processed.</li>
      <li>Back-office visibility for all investments and detailed management view.</li>
      <li>Investment history pagination and PDF export generation.</li>
    </ul>
  </div>

  <div class="module">
    <h2>6) Ressources Module</h2>
    <p class="desc">This module manages resource inventory, reservations, and advanced operational analysis for back office.</p>
    <ul>
      <li>Front and back resource catalogs with filtering and role-based access controls.</li>
      <li>Back-office resource dashboard with metrics and chart visualizations.</li>
      <li>Resource CRUD with normalization and dependency checks before deletion.</li>
      <li>Reservation workflows for clients and back office (create/edit/delete).</li>
      <li>Stock availability tracking (reserved vs available) per resource.</li>
      <li>Resource analysis engine with async run, filtered results, CSV/PDF exports, and analysis chat.</li>
    </ul>
  </div>
</body>
</html>
HTML;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->setIsRemoteEnabled(true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outputPath = __DIR__ . '/modules_overview.pdf';
file_put_contents($outputPath, $dompdf->output());

echo "Generated: {$outputPath}" . PHP_EOL;
