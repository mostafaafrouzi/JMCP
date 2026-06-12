<?php
declare(strict_types=1);

require 'D:/WebServer/wamp64/www/autoserviceali/configuration.php';
$c = new JConfig();
$m = new mysqli($c->host, $c->user, $c->password, $c->db);
$m->set_charset('utf8mb4');
$p = $c->dbprefix;
$brand = 'اتو سرویس ستاره';

$fixes = [
    '<span>آشنایی با ما<\/span>گیربکس is a beloved Italian dish that has become a global <span>phenomenon<\/span>' => '<span>آشنایی با ' . $brand . '<\/span> — مرکز تخصصی تعویض روغن گیربکس اتوماتیک',
    'گیربکس is a beloved Italian dish that has become a global phenomenon' => 'مرکز تخصصی سرویس گیربکس اتوماتیک',
    'is a beloved Italian dish that has become a global <span>phenomenon<\/span>' => 'مرکز تخصصی تعویض روغن گیربکس اتوماتیک',
    'is a beloved Italian dish that has become a global phenomenon' => 'مرکز تخصصی تعویض روغن گیربکس',
    '<span>Now with Free Delivery<\/span>' => '<span>سرویس فوری با روغن اصل<\/span>',
    'Free Delivery With گیربکس Of The Day' => 'سرویس فوری با روغن اصل',
    'Free Delivery With' => 'سرویس فوری با',
    ' Of The Day' => ' روغن اصل',
    'Call Us: <strong class=\"text-primary\">(+51) 456 4908<\/strong>' => 'تماس: <strong class=\"text-primary\">۰۲۱-۸۸۷۷۶۶۵۵<\/strong>',
    'Call Us:' => 'تماس:',
    'Choose From Our Most Popular Dishes' => 'محبوب‌ترین خدمات ما',
  'Most Popular Dishes' => 'محبوب‌ترین خدمات',
    '80 Road Brooklyn Street, 600 New York, USA' => 'تهران، خیابان ولیعصر، نرسیده به میدان ونک',
    'Brooklyn Street' => 'خیابان ولیعصر',
    'اتوسرویس و تعویض روغنی ستاره' => $brand,
    'اتوسرویس علی' => $brand,
    'Unitemplates' => $brand,
    'contact@email.com' => 'info@setareh-auto.ir',
    '+228 872 4444' => '۰۲۱-۸۸۷۷۶۶۵۵',
    '+775 872 4444' => '۰۹۱۲-۳۳۴۴۵۵۶',
    '"placeholder":"Email"' => '"placeholder":"ایمیل"',
    'Contact Us' => 'تماس با ما',
    'Terms of Use' => 'قوانین استفاده',
    'I agree with the' => 'با',
    'and I declare that I have read the information that is required in accordance' => 'موافقم و اطلاعات لازم را مطالعه کرده‌ام',
];

uksort($fixes, static fn($a, $b) => strlen($b) <=> strlen($a));

foreach (['content', 'text', 'css', 'og_title', 'og_description'] as $col) {
    foreach ($fixes as $from => $to) {
        $m->query("UPDATE {$p}sppagebuilder SET `{$col}` = REPLACE(`{$col}`, '"
            . $m->real_escape_string($from) . "', '" . $m->real_escape_string($to) . "')");
    }
}

// Template style params (off-canvas contact)
$res = $m->query("SELECT id, params FROM {$p}template_styles WHERE client_id = 0");
while ($row = $res->fetch_assoc()) {
    $params = $row['params'];
    foreach ($fixes as $from => $to) {
        $params = str_replace($from, $to, $params);
    }
    $m->query("UPDATE {$p}template_styles SET params = '" . $m->real_escape_string($params) . "' WHERE id = " . (int) $row['id']);
}

$cacheDir = 'D:/WebServer/wamp64/www/autoserviceali/cache';
foreach (glob($cacheDir . '/*') ?: [] as $file) {
    if (is_file($file) && basename($file) !== 'index.html') {
        @unlink($file);
    }
}

echo "Patch applied.\n";
