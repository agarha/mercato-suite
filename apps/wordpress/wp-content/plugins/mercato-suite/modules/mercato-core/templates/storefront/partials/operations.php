<?php /** @var array $data */ /** @var \Closure $esc */ ?>
<section class="section" id="operations">
  <div class="section-head">
    <div><h2>Service operations cockpit</h2><p>Booking, dispatch, estimate, and referral records come from the shared Mercato service-ops module.</p></div>
    <span class="pill">Soft-launch ops</span>
  </div>
  <div class="ops-grid">
    <div class="ops-score">
      <div><span>Bookings</span><strong><?= (int) $data['booking_count'] ?></strong></div>
      <div><span>Jobs</span><strong><?= (int) $data['job_count'] ?></strong></div>
      <div><span>Estimates</span><strong><?= (int) $data['estimate_count'] ?></strong></div>
      <div><span>Referrals</span><strong><?= (int) $data['referral_count'] ?></strong></div>
    </div>
    <div class="account-panel">
      <h3>Recent jobs</h3>
      <table class="table">
        <thead><tr><th>Job</th><th>Provider</th><th>Status</th><th>Assignee</th><th>Updated</th></tr></thead>
        <tbody>
          <?php if (empty($data['jobs'])): ?>
            <tr><td colspan="5">No service jobs yet.</td></tr>
          <?php else: foreach ($data['jobs'] as $job): $assignee = (int) ($job['assigned_user_id'] ?? 0); ?>
            <tr>
              <td>#<?= $esc($job['job_id']) ?></td>
              <td><?= $esc($job['business_name'] ?: 'Provider') ?></td>
              <td><?= $esc($job['status']) ?></td>
              <td><?= $assignee > 0 ? $esc($assignee) : 'Unassigned' ?></td>
              <td><?= $esc($job['updated_at']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
