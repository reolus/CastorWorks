<?php
namespace App\Services;
use App\Core\Database;
final class TemplateService
{
    public static function render(string $key, array $vars): array
    {
        $s=Database::connection()->prepare('SELECT subject,html_body FROM email_templates WHERE template_key=? AND active=1');
        $s->execute([$key]); $row=$s->fetch();
        if(!$row) throw new \RuntimeException('Email template not found: '.$key);
        foreach($vars as $k=>$v){$row['subject']=str_replace('{{'.$k.'}}',(string)$v,$row['subject']);$row['html_body']=str_replace('{{'.$k.'}}',(string)$v,$row['html_body']);}
        return $row;
    }
}
