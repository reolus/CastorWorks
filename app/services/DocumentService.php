<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use RuntimeException;

final class DocumentService
{
    public function generate(string $entityType,int $entityId): array
    {
        $pdo=Database::connection();
        if($entityType==='estimate'){
            $s=$pdo->prepare('SELECT e.*,c.display_name,c.email,c.phone,p.address1,p.address2,p.city,p.state,p.postal_code FROM estimates e JOIN customers c ON c.id=e.customer_id LEFT JOIN properties p ON p.id=e.property_id WHERE e.id=?');$s->execute([$entityId]);$record=$s->fetch();
            $i=$pdo->prepare('SELECT * FROM estimate_items WHERE estimate_id=? ORDER BY id');$i->execute([$entityId]);$items=$i->fetchAll();$number=$record['estimate_number'];$title='Estimate';
        }elseif($entityType==='invoice'){
            $s=$pdo->prepare('SELECT i.*,c.display_name,c.email,c.phone,j.job_number,j.service_summary FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN jobs j ON j.id=i.job_id WHERE i.id=?');$s->execute([$entityId]);$record=$s->fetch();$items=[['description'=>$record['service_summary']?:'Exterior services','quantity'=>1,'unit_price'=>$record['subtotal'],'line_total'=>$record['subtotal']]];$number=$record['invoice_number'];$title='Invoice';
        }elseif($entityType==='agreement'){
            $s=$pdo->prepare('SELECT a.*,c.display_name,c.email,c.phone FROM service_agreements a JOIN customers c ON c.id=a.customer_id WHERE a.id=?');$s->execute([$entityId]);$record=$s->fetch();$items=[];$number=$record['agreement_number']??'';$title='Service Agreement';
        }else throw new RuntimeException('Unsupported document type.');
        if(!$record) throw new RuntimeException(ucfirst($entityType).' not found.');
        $html=$entityType==='agreement'?$this->renderAgreement($record):$this->render($title,$record,$items);
        $dir=dirname(__DIR__,2).'/storage/generated/'.$entityType.'s';if(!is_dir($dir))mkdir($dir,0770,true);
        $safe=preg_replace('/[^A-Za-z0-9_-]/','_',$number);$pdfPath=$dir.'/'.$safe.'.pdf';$htmlPath=$dir.'/'.$safe.'.html';file_put_contents($htmlPath,$html,LOCK_EX);
        $this->renderPdf($html,$pdfPath);
        $q=$pdo->prepare("INSERT INTO documents(reference_type,reference_id,document_type,original_name,local_path,storage_status,uploaded_by) VALUES(?,?,?,?,?,'local',?)");
        $q->execute([$entityType,$entityId,$entityType.'_pdf',basename($pdfPath),$pdfPath,\App\Core\Auth::id()]);
        return ['id'=>(int)$pdo->lastInsertId(),'path'=>$pdfPath,'filename'=>basename($pdfPath),'html_path'=>$htmlPath];
    }

    private function renderPdf(string $html,string $path): void
    {
        $autoload=dirname(__DIR__,2).'/vendor/autoload.php';
        if(!is_file($autoload)) throw new RuntimeException('Dompdf is not installed. Run composer install in the project directory.');
        require_once $autoload;
        if(!class_exists('Dompdf\Dompdf')) throw new RuntimeException('Dompdf is unavailable. Run composer install and verify vendor/autoload.php.');
        $options=new \Dompdf\Options();
        $options->set('isRemoteEnabled',false);
        $options->set('isHtml5ParserEnabled',true);
        $options->set('defaultFont','DejaVu Sans');
        $dompdf=new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html,'UTF-8');
        $dompdf->setPaper('letter','portrait');
        $dompdf->render();
        $bytes=$dompdf->output();
        if($bytes==='') throw new RuntimeException('Dompdf returned an empty document.');
        if(file_put_contents($path,$bytes,LOCK_EX)===false) throw new RuntimeException('Unable to save generated PDF.');
    }

    private function renderAgreement(array $record): string
    {
        $logo=dirname(__DIR__,2).'/public/assets/img/logo-primary.png';
        $logoData=is_file($logo)?'data:image/png;base64,'.base64_encode((string)file_get_contents($logo)):'';
        $signature='';
        if(!empty($record['signed_at'])) $signature='<div class="signature"><strong>Signed by:</strong> '.htmlspecialchars((string)$record['signed_name']).'<br><strong>Email:</strong> '.htmlspecialchars((string)$record['signed_email']).'<br><strong>Date:</strong> '.htmlspecialchars((string)$record['signed_at']).'</div>';
        return '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:40px}body{font-family:DejaVu Sans,sans-serif;color:#17233a;font-size:11px;line-height:1.55}.header{border-bottom:3px solid #0a3556;padding-bottom:15px}.header img{width:220px}.title{font-size:26px;color:#0a3556;margin:24px 0 5px}.meta{color:#667}.body{margin-top:25px;white-space:pre-wrap}.signature{margin-top:35px;border-top:1px solid #aaa;padding-top:15px}.footer{position:fixed;bottom:0;text-align:center;width:100%;font-size:9px;color:#667}</style></head><body><div class="header">'.($logoData?'<img src="'.$logoData.'">':'<strong>Rock Bluffs Exterior Services</strong>').'</div><div class="title">'.htmlspecialchars((string)$record['title']).'</div><div class="meta">Agreement '.htmlspecialchars((string)$record['agreement_number']).' · Customer: '.htmlspecialchars((string)$record['display_name']).'</div><div class="body">'.nl2br(htmlspecialchars((string)$record['body'])).'</div>'.$signature.'<div class="footer">Rock Bluffs Exterior Services · A division of Rock Bluffs, LLC</div></body></html>';
    }

    private function render(string $title,array $record,array $items): string
    {
        $logo=dirname(__DIR__,2).'/public/assets/img/logo-primary.png';$logoData=is_file($logo)?'data:image/png;base64,'.base64_encode((string)file_get_contents($logo)):'';$rows='';
        foreach($items as $item){$rows.='<tr><td>'.htmlspecialchars((string)$item['description']).'</td><td style="text-align:right">'.number_format((float)$item['quantity'],2).'</td><td style="text-align:right">$'.number_format((float)$item['unit_price'],2).'</td><td style="text-align:right">$'.number_format((float)$item['line_total'],2).'</td></tr>';}
        $subtotal=(float)($record['subtotal']??0);$tax=(float)($record['tax_amount']??0);$total=(float)($record['total']??0);$number=$record[$title==='Estimate'?'estimate_number':'invoice_number'];
        return '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:35px}body{font-family:Arial,sans-serif;color:#17233a;font-size:12px}.brand{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #0a4b7a;padding-bottom:16px}.brand img{width:210px}.doc{text-align:right}.doc h1{font-size:30px;margin:0;color:#0a3556}table{width:100%;border-collapse:collapse;margin-top:24px}th{background:#0a3556;color:#fff;padding:9px;text-align:left}td{padding:9px;border-bottom:1px solid #d9e1e8}.totals{width:42%;margin-left:auto}.totals td{border:0}.grand{font-size:16px;font-weight:bold;border-top:2px solid #0a3556!important}.customer{margin-top:20px;line-height:1.6}.footer{position:fixed;bottom:0;width:100%;text-align:center;color:#667;font-size:10px}</style></head><body><div class="brand">'.($logoData?'<img src="'.$logoData.'">':'<strong>Rock Bluffs Exterior Services</strong>').'<div class="doc"><h1>'.$title.'</h1><strong>'.htmlspecialchars((string)$number).'</strong><br>Date: '.date('F j, Y').'</div></div><div class="customer"><strong>Prepared for:</strong><br>'.htmlspecialchars((string)$record['display_name']).'<br>'.htmlspecialchars((string)($record['email']??'')).'<br>'.htmlspecialchars((string)($record['phone']??'')).'</div><table><thead><tr><th>Description</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr></thead><tbody>'.$rows.'</tbody></table><table class="totals"><tr><td>Subtotal</td><td style="text-align:right">$'.number_format($subtotal,2).'</td></tr><tr><td>Tax</td><td style="text-align:right">$'.number_format($tax,2).'</td></tr><tr><td class="grand">Total</td><td class="grand" style="text-align:right">$'.number_format($total,2).'</td></tr></table><div class="footer">Rock Bluffs Exterior Services · A division of Rock Bluffs, LLC</div></body></html>';
    }
}
