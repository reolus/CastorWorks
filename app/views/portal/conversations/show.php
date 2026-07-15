<div class="d-flex justify-content-between align-items-start mb-4"><div><a href="/portal/conversations" class="small text-decoration-none">&larr; Conversations</a><h1 class="h3 mt-2 mb-1"><?=e($thread['subject'])?></h1><p class="text-muted"><?=e($thread['customer_name'])?> · <?=e($thread['status'])?> · <?=e($thread['priority'])?> priority</p></div><?php if($thread['status']!=='closed'):?><form method="post" action="/portal/conversations/<?=$thread['id']?>/close"><?=csrf_field()?><button class="btn btn-outline-secondary">Close Thread</button></form><?php endif?></div>
<div class="row g-4"><div class="col-lg-8"><div class="card shadow-sm"><div class="card-body"><div class="conversation-list"><?php foreach($messages as $m):?><div class="border rounded p-3 mb-3 <?=$m['is_internal']?'bg-warning-subtle':'bg-light'?>"><div class="d-flex justify-content-between"><strong><?=e($m['sender_name']??ucfirst($m['sender_type']))?></strong><span class="small text-muted"><?=e($m['channel'])?> · <?=e($m['created_at'])?></span></div><div class="mt-2" style="white-space:pre-wrap"><?=e($m['body'])?></div><?php if($m['is_internal']):?><span class="badge text-bg-warning mt-2">Internal</span><?php endif?></div><?php endforeach?></div></div></div></div><div class="col-lg-4"><div class="card shadow-sm"><div class="card-body"><h2 class="h5">Reply</h2><form method="post" action="/portal/conversations/<?=$thread['id']?>/reply" class="row g-3"><?=csrf_field()?><div class="col-12"><select name="channel" class="form-select"><option>portal</option><option>email</option><option>sms</option><option>phone</option><option>internal</option></select></div><div class="col-12"><textarea name="body" class="form-control" rows="7" required></textarea></div><div class="col-12"><select name="status" class="form-select"><option value="waiting_customer">Waiting for customer</option><option value="waiting_staff">Waiting for staff</option><option value="open">Keep open</option></select></div><div class="col-12 form-check ms-2"><input type="checkbox" name="is_internal" class="form-check-input" id="ri"><label for="ri" class="form-check-label">Internal note</label></div><div class="col-12"><button class="btn btn-primary w-100">Add Message</button></div></form></div></div></div></div>

<div class="card shadow-sm mt-4"><div class="card-body">
  <h2 class="h5"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>AI reply draft</h2>
  <p class="text-muted">Create an internal, reviewable customer-reply draft. CastorWorks will not send it automatically.</p>
  <form method="post" action="/portal/ai/drafts" class="row g-3">
    <?=csrf_field()?>
    <input type="hidden" name="draft_type" value="customer_reply">
    <input type="hidden" name="reference" value="Conversation #<?=$thread['id']?> - <?=e($thread['subject'])?>">
    <input type="hidden" name="target_type" value="customer_reply">
    <input type="hidden" name="target_id" value="<?=$thread['id']?>">
    <input type="hidden" name="return_to" value="/portal/conversations/<?=$thread['id']?>">
    <textarea class="d-none" name="details"><?php foreach(array_slice($messages,-8) as $m):?><?=e(($m['sender_name']??ucfirst($m['sender_type'])).': '.$m['body']."
")?><?php endforeach?></textarea>
    <div class="col-12"><label class="form-label">Instruction</label><input class="form-control" name="instruction" value="Draft a concise, courteous response that addresses the customer's latest message. Do not invent facts or promises." maxlength="500"></div>
    <div class="col-12 text-end"><button class="btn btn-outline-primary">Create AI reply draft</button></div>
  </form>
</div></div>
