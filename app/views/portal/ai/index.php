<?php
$canConfigure = \App\Core\Auth::can('owner', 'administrator');
$statusClass = match ($health['status'] ?? 'warning') { 'ok' => 'success', 'failed' => 'danger', 'disabled' => 'secondary', default => 'warning' };
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div><h1 class="h3 mb-1"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>AI Assistant</h1><p class="text-muted mb-0">Ask operational questions using an optional aggregate business context.</p></div>
  <span class="badge text-bg-<?=$statusClass?> p-2"><?=e($health['detail'] ?? 'Unknown')?></span>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card card-body"><small class="text-muted">Requests this month</small><strong class="fs-4"><?=number_format((int)($usageSummary['requests']??0))?></strong></div></div>
  <div class="col-md-3"><div class="card card-body"><small class="text-muted">Successful</small><strong class="fs-4"><?=number_format((int)($usageSummary['successful']??0))?></strong></div></div>
  <div class="col-md-3"><div class="card card-body"><small class="text-muted">Estimated cost</small><strong class="fs-4">$<?=number_format((float)($usageSummary['estimated_cost']??0),4)?></strong></div></div>
  <div class="col-md-3"><div class="card card-body"><small class="text-muted">Average latency</small><strong class="fs-4"><?=number_format((float)($usageSummary['average_latency_ms']??0))?> ms</strong></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="card card-body mb-4">
      <form method="post" action="/portal/ai/ask">
        <?=csrf_field()?>
        <label class="form-label fw-semibold">Ask CastorWorks</label>
        <textarea class="form-control" name="prompt" rows="6" maxlength="8000" required placeholder="Summarize today's operational risks and suggest the three most important actions."><?=e($lastPrompt)?></textarea>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="include_context" value="1" id="aiContext" checked><label class="form-check-label" for="aiContext">Include aggregate operational context</label></div>
          <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Run Assistant</button>
        </div>
        <small class="text-muted">Context excludes customer messages, addresses, files, and other free-form personal data.</small>
      </form>
    </div>

    <?php if(is_string($answer) && $answer !== ''):?>
      <div class="card card-body mb-4"><h2 class="h5">Response</h2><div style="white-space:pre-wrap"><?=e($answer)?></div></div>
    <?php endif?>

    <div class="card card-body mb-4">
      <h2 class="h5">Create governed draft</h2>
      <form method="post" action="/portal/ai/drafts" class="row g-3"><?=csrf_field()?>
        <div class="col-md-4"><label class="form-label">Draft type</label><select class="form-select" name="draft_type"><option value="estimate">Estimate narrative</option><option value="customer_reply">Customer reply</option><option value="route_recommendation">Route recommendation</option><option value="staffing_recommendation">Staffing recommendation</option></select></div>
        <div class="col-md-4"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Estimate #, customer, route, or date"></div>
        <div class="col-md-4"><label class="form-label">Instruction</label><input class="form-control" name="instruction" placeholder="Tone or objective"></div>
        <div class="col-12"><label class="form-label">Grounding details</label><textarea class="form-control" name="details" rows="4" maxlength="8000" required></textarea></div>
        <div class="col-12 text-end"><button class="btn btn-outline-primary">Create reviewable draft</button></div>
      </form>
    </div>

    <div class="card card-body mb-4">
      <h2 class="h5">Draft approval queue</h2>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Created</th><th>Type / Reference</th><th>Status</th><th>Content</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($drafts as $draft):?><tr><td><?=e($draft['created_at'])?><br><small class="text-muted"><?=e($draft['created_by_name']??'System')?></small></td><td><?=e($draft['draft_type'])?><br><small><?=e($draft['reference_key'])?></small></td><td><span class="badge text-bg-<?=match($draft['status']){'approved'=>'success','rejected'=>'danger','used'=>'primary',default=>'warning'}?>"><?=e($draft['status'])?></span></td><td style="min-width:320px;white-space:pre-wrap"><?=e(mb_strimwidth((string)$draft['content'],0,600,'…'))?></td><td>
      <?php if($draft['status']==='draft' && \App\Core\Auth::can('owner','administrator','office')):?><form class="mb-2" method="post" action="/portal/ai/drafts/<?=$draft['id']?>/approve"><?=csrf_field()?><button class="btn btn-sm btn-success">Approve</button></form><?php endif?>
      <?php if(in_array($draft['status'],['draft','approved'],true) && \App\Core\Auth::can('owner','administrator','office')):?><form class="mb-2" method="post" action="/portal/ai/drafts/<?=$draft['id']?>/reject"><?=csrf_field()?><input class="form-control form-control-sm mb-1" name="reason" placeholder="Reason"><button class="btn btn-sm btn-outline-danger">Reject</button></form><?php endif?>
      <?php if($draft['status']==='approved' || ($draft['status']==='draft' && !(int)$draft['requires_approval'])):?><form method="post" action="/portal/ai/drafts/<?=$draft['id']?>/use"><?=csrf_field()?><input class="form-control form-control-sm mb-1" name="target_type" value="<?=e($draft['source_target_type']??$draft['draft_type'])?>"><input class="form-control form-control-sm mb-1" type="number" name="target_id" value="<?=e($draft['source_target_id']??'')?>" placeholder="Record ID" required><div class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="human_reviewed" value="1" id="reviewed<?=$draft['id']?>" required><label class="form-check-label" for="reviewed<?=$draft['id']?>">I reviewed this content</label></div><button class="btn btn-sm btn-primary">Apply approved draft</button></form><?php endif?>
      </td></tr><?php endforeach?>
      <?php if(!$drafts):?><tr><td colspan="5" class="text-muted">No AI drafts have been created.</td></tr><?php endif?>
      </tbody></table></div>
    </div>

    <div class="card card-body">
      <h2 class="h5">Recent usage</h2>
      <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Time</th><th>User</th><th>Provider</th><th>Status</th><th>Latency</th><th>Characters</th><th>Est. cost</th></tr></thead><tbody>
      <?php foreach($usage as $row):?><tr><td><?=e($row['created_at'])?></td><td><?=e($row['user_name']??'System')?></td><td><?=e($row['provider'])?><br><small class="text-muted"><?=e($row['model'])?></small></td><td><span class="badge text-bg-<?=$row['status']==='success'?'success':'danger'?>"><?=e($row['status'])?></span></td><td><?=e($row['latency_ms'])?> ms</td><td><?=e($row['input_chars'])?> / <?=e($row['output_chars'])?></td><td>$<?=number_format((float)($row['estimated_cost_usd']??0),6)?></td></tr><?php endforeach?>
      <?php if(!$usage):?><tr><td colspan="7" class="text-muted">No AI usage has been recorded.</td></tr><?php endif?>
      </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-4">
    <?php if($canConfigure):?>
    <div class="card card-body mb-4">
      <h2 class="h5">Provider settings</h2>
      <form method="post" action="/portal/ai/settings">
        <?=csrf_field()?>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" id="aiEnabled" <?=$settings['enabled']?'checked':''?>><label class="form-check-label" for="aiEnabled">Enable AI Assistant</label></div>
        <label class="form-label">Provider</label><select class="form-select mb-3" name="provider"><?php foreach(['disabled'=>'Disabled','openai'=>'OpenAI','azure_openai'=>'Azure OpenAI','ollama'=>'Ollama / local'] as $key=>$label):?><option value="<?=$key?>" <?=$settings['provider']===$key?'selected':''?>><?=$label?></option><?php endforeach?></select>
        <label class="form-label">Model or deployment label</label><input class="form-control mb-3" name="model" value="<?=e($settings['model'])?>" placeholder="gpt-4.1-mini or llama3.2">
        <label class="form-label">Custom endpoint</label><input class="form-control mb-3" name="endpoint" value="<?=e($settings['endpoint'])?>" placeholder="Optional; required for Ollama">
        <div class="row g-2 mb-3"><div class="col"><label class="form-label">Temperature</label><input class="form-control" type="number" step="0.1" min="0" max="2" name="temperature" value="<?=e($settings['temperature'])?>"></div><div class="col"><label class="form-label">Max tokens</label><input class="form-control" type="number" min="64" max="4000" name="max_tokens" value="<?=e($settings['max_tokens'])?>"></div></div>
        <div class="row g-2 mb-3"><div class="col"><label class="form-label">Daily requests</label><input class="form-control" type="number" min="0" name="daily_request_limit" value="<?=e($settings['daily_request_limit']??0)?>"></div><div class="col"><label class="form-label">Monthly requests</label><input class="form-control" type="number" min="0" name="monthly_request_limit" value="<?=e($settings['monthly_request_limit']??0)?>"></div></div>
        <label class="form-label">Monthly cost limit (USD)</label><input class="form-control mb-3" type="number" min="0" step="0.01" name="monthly_cost_limit_usd" value="<?=e($settings['monthly_cost_limit_usd']??0)?>">
        <div class="row g-2 mb-3"><div class="col"><label class="form-label">Input $ / 1M tokens</label><input class="form-control" type="number" min="0" step="0.000001" name="input_cost_per_million_tokens" value="<?=e($settings['input_cost_per_million_tokens']??0)?>"></div><div class="col"><label class="form-label">Output $ / 1M tokens</label><input class="form-control" type="number" min="0" step="0.000001" name="output_cost_per_million_tokens" value="<?=e($settings['output_cost_per_million_tokens']??0)?>"></div></div>
        <label class="form-label">Allowed roles</label><input class="form-control mb-3" name="allowed_roles" value="<?=e($settings['allowed_roles']??'owner,administrator,office,estimator')?>">
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="redact_sensitive_data" id="redactAi" <?=($settings['redact_sensitive_data']??1)?'checked':''?>><label class="form-check-label" for="redactAi">Redact sensitive data</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="approval_required_estimate" id="approveEstimate" <?=($settings['approval_required_estimate']??1)?'checked':''?>><label class="form-check-label" for="approveEstimate">Require estimate-draft approval</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="approval_required_customer_reply" id="approveReply" <?=($settings['approval_required_customer_reply']??1)?'checked':''?>><label class="form-check-label" for="approveReply">Require customer-reply approval</label></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="approval_required_other" id="approveOther" <?=($settings['approval_required_other']??0)?'checked':''?>><label class="form-check-label" for="approveOther">Require approval for internal recommendations</label></div>
        <label class="form-label">System instructions</label><textarea class="form-control mb-3" rows="5" name="system_prompt"><?=e($settings['system_prompt'])?></textarea>
        <button class="btn btn-outline-primary w-100">Save provider settings</button>
      </form>
    </div>
    <?php endif?>

    <?php if($canConfigure):?>
    <div class="card card-body mb-4">
      <h2 class="h5">Provider usage this month</h2>
      <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Provider</th><th>Requests</th><th>Failures</th><th>Cost</th></tr></thead><tbody>
      <?php foreach($providerUsage as $row):?><tr><td><?=e($row['provider'])?><br><small class="text-muted"><?=e($row['model'])?></small></td><td><?=e($row['requests'])?></td><td><?=e($row['failed'])?></td><td>$<?=number_format((float)$row['estimated_cost'],4)?></td></tr><?php endforeach?>
      <?php if(!$providerUsage):?><tr><td colspan="4" class="text-muted">No provider usage this month.</td></tr><?php endif?>
      </tbody></table></div>
    </div>

    <div class="card card-body mb-4">
      <h2 class="h5">User AI budgets</h2>
      <?php foreach($userUsage as $row):?><form method="post" action="/portal/ai/budgets/<?=$row['user_id']?>" class="border rounded p-2 mb-2"><?=csrf_field()?><div class="fw-semibold"><?=e($row['name'])?> <small class="text-muted"><?=e($row['role'])?> · <?=e($row['requests'])?> request(s) · $<?=number_format((float)$row['estimated_cost'],4)?></small></div><div class="row g-1 mt-1"><div class="col-4"><input class="form-control form-control-sm" type="number" min="0" name="daily_request_limit" value="<?=e($row['daily_request_limit']??0)?>" title="Daily request limit"></div><div class="col-4"><input class="form-control form-control-sm" type="number" min="0" name="monthly_request_limit" value="<?=e($row['monthly_request_limit']??0)?>" title="Monthly request limit"></div><div class="col-4"><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="monthly_cost_limit_usd" value="<?=e($row['monthly_cost_limit_usd']??0)?>" title="Monthly cost limit"></div></div><button class="btn btn-sm btn-outline-primary mt-2">Save budget</button></form><?php endforeach?>
    </div>
    <?php endif?>

    <div class="card card-body">
      <h2 class="h5">Saved prompts</h2>
      <?php foreach($prompts as $prompt):?><button type="button" class="btn btn-sm btn-outline-secondary text-start mb-2 ai-prompt-button" data-prompt="<?=e($prompt['prompt_template'])?>"><?=e($prompt['name'])?></button><?php endforeach?>
      <?php if(!$prompts):?><p class="text-muted">No saved prompts.</p><?php endif?>
      <?php if(\App\Core\Auth::can('owner','administrator','office')):?><hr><form method="post" action="/portal/ai/prompts"><?=csrf_field()?><input class="form-control mb-2" name="name" placeholder="Prompt name" required><textarea class="form-control mb-2" name="prompt_template" rows="3" placeholder="Prompt text" required></textarea><button class="btn btn-sm btn-outline-primary">Save prompt</button></form><?php endif?>
      <?php if($promptHistory):?><hr><h3 class="h6">Recent prompt versions</h3><?php foreach(array_slice($promptHistory,0,12) as $version):?><div class="border rounded p-2 mb-2"><div class="small fw-semibold"><?=e($version['current_name'])?> v<?=e($version['version'])?></div><div class="small text-muted mb-1"><?=e($version['created_at'])?> · <?=e($version['created_by_name']??'System')?></div><form method="post" action="/portal/ai/prompts/<?=$version['prompt_id']?>/rollback"><?=csrf_field()?><input type="hidden" name="version" value="<?=$version['version']?>"><button class="btn btn-sm btn-outline-secondary">Restore this version</button></form></div><?php endforeach?><?php endif?>
    </div>
  </div>
</div>
<script>document.querySelectorAll('.ai-prompt-button').forEach(function(b){b.addEventListener('click',function(){var t=document.querySelector('textarea[name="prompt"]');if(t){t.value=b.dataset.prompt||'';t.focus();}});});</script>
