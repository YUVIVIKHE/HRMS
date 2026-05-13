<div class="form-group" style="margin-bottom:14px;">
  <label>Leave Type Name <span class="req">*</span></label>
  <input type="text" name="name" class="form-control" placeholder="e.g. Casual Leave" required>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
  <div class="form-group">
    <label>Days per Credit <span class="req">*</span></label>
    <input type="number" name="days_per_credit" class="form-control" min="0.5" step="0.5" value="1" required>
  </div>
  <div class="form-group">
    <label>Color</label>
    <input type="color" name="color" class="form-control" value="#4f46e5" style="height:40px;padding:4px;">
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
  <div class="form-group">
    <label>Credit Cycle</label>
    <select name="credit_cycle" class="form-control" onchange="toggleCreditDay(this)">
      <option value="monthly">Monthly</option>
      <option value="yearly">Yearly</option>
      <option value="manual">Manual Only</option>
    </select>
  </div>
  <div class="form-group" id="creditDayWrap_add">
    <label>Credit on Day</label>
    <input type="number" name="credit_day" class="form-control" min="1" max="28" value="1">
    <span style="font-size:11px;color:var(--muted-light);">Day of month (for monthly/yearly)</span>
  </div>
</div>
<div class="form-group">
  <label>Max Carry Forward (days, 0 = none)</label>
  <input type="number" name="max_carry_fwd" class="form-control" min="0" step="0.5" value="0">
</div>
