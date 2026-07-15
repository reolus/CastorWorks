<?php foreach(($customFields??[]) as $f): $name='custom_'.$f['field_key'];$value=$customValues[$f['field_key']]??''; ?>
<div class="col-md-6"><label class="form-label"><?=e($f['label'])?><?=$f['required']?' *':''?></label>
<?php if($f['field_type']==='textarea'):?><textarea name="<?=$name?>" class="form-control" <?=$f['required']?'required':''?>><?=e($value)?></textarea>
<?php elseif($f['field_type']==='select'):?><select name="<?=$name?>" class="form-select" <?=$f['required']?'required':''?>><option value="">Choose...</option><?php foreach($f['options'] as $o):?><option value="<?=e($o)?>" <?=$value===$o?'selected':''?>><?=e($o)?></option><?php endforeach?></select>
<?php elseif($f['field_type']==='checkbox'):?><div class="form-check mt-2"><input type="checkbox" class="form-check-input" name="<?=$name?>" value="1" <?=$value==='1'?'checked':''?>><label class="form-check-label">Yes</label></div>
<?php else:?><input type="<?=in_array($f['field_type'],['number','date','datetime','email','url'],true)?e($f['field_type']==='datetime'?'datetime-local':$f['field_type']):'text'?>" name="<?=$name?>" class="form-control" value="<?=e($value)?>" <?=$f['required']?'required':''?>><?php endif?></div>
<?php endforeach?>
