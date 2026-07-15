<?php
namespace App\Controllers;
use App\Core\Database;
final class SignatureController
{
 public function signEstimate(string $token): void {verify_csrf();$name=trim($_POST['signature_name']??'');$data=$_POST['signature_data']??'';if($name===''||$data===''){flash('danger','Signature name and signature are required.');redirect('/estimate/'.$token);} $q=Database::connection()->prepare("UPDATE estimates SET customer_signature_name=?,customer_signature_data=?,signed_at=NOW(),status='accepted',accepted_at=COALESCE(accepted_at,NOW()) WHERE public_token=?");$q->execute([$name,$data,$token]);flash('success','Estimate signed and accepted.');redirect('/estimate/'.$token);}
}
