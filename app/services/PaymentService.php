<?php
namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class PaymentService
{
    private string $secret;
    private string $currency;

    public function __construct()
    {
        $this->secret=(string)Env::get('STRIPE_SECRET_KEY','');
        $this->currency=strtolower((string)Env::get('STRIPE_CURRENCY','usd'));
    }

    public function configured(): bool { return $this->secret!==''; }

    public function createCheckout(array $invoice): array
    {
        if(!$this->configured()) throw new RuntimeException('Stripe is not configured.');
        $amount=(int)round(((float)$invoice['balance_due'])*100);
        if($amount<50) throw new RuntimeException('Invoice balance is too small for online payment.');
        $success=(string)Env::get('PAYMENT_SUCCESS_URL',rtrim((string)Env::get('APP_URL',''),'/').'/payment/success');
        $cancel=(string)Env::get('PAYMENT_CANCEL_URL',rtrim((string)Env::get('APP_URL',''),'/').'/invoice/'.$invoice['public_token']);
        $params=[
            'mode'=>'payment',
            'success_url'=>$success.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'=>$cancel,
            'customer_email'=>$invoice['email'] ?? '',
            'client_reference_id'=>(string)$invoice['id'],
            'metadata[invoice_id]'=>(string)$invoice['id'],
            'metadata[invoice_number]'=>(string)$invoice['invoice_number'],
            'line_items[0][quantity]'=>'1',
            'line_items[0][price_data][currency]'=>$this->currency,
            'line_items[0][price_data][unit_amount]'=>(string)$amount,
            'line_items[0][price_data][product_data][name]'=>'Rock Bluffs Invoice '.$invoice['invoice_number'],
        ];
        $ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->secret,'Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>http_build_query($params)]);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        $data=json_decode((string)$raw,true)?:[];
        if($code>=300||empty($data['id'])||empty($data['url'])) throw new RuntimeException('Stripe checkout failed: '.($data['error']['message']??$err?:'unknown error'));
        return ['id'=>$data['id'],'url'=>$data['url'],'expires_at'=>isset($data['expires_at'])?date('Y-m-d H:i:s',(int)$data['expires_at']):null,'amount'=>$amount/100,'currency'=>$this->currency];
    }

    public function verifyWebhook(string $payload,string $signature): array
    {
        $secret=(string)Env::get('STRIPE_WEBHOOK_SECRET','');
        if($secret==='') throw new RuntimeException('Stripe webhook secret is not configured.');
        $parts=[];foreach(explode(',',$signature) as $piece){[$k,$v]=array_pad(explode('=',$piece,2),2,'');$parts[$k][]=$v;}
        $timestamp=(int)($parts['t'][0]??0);$signed=$timestamp.'.'.$payload;$expected=hash_hmac('sha256',$signed,$secret);$valid=false;
        foreach($parts['v1']??[] as $sig){if(hash_equals($expected,$sig)){$valid=true;break;}}
        if(!$valid||abs(time()-$timestamp)>300) throw new RuntimeException('Invalid Stripe webhook signature.');
        $event=json_decode($payload,true);if(!is_array($event)) throw new RuntimeException('Invalid Stripe webhook payload.');
        return $event;
    }
}
