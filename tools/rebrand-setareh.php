<?php
/**
 * Rebrand site to اتو سرویس ستاره — automatic transmission oil change.
 */
declare(strict_types=1);

$joomlaRoot = getenv('JOOMLA_ROOT') ?: 'D:/WebServer/wamp64/www/autoserviceali';
require_once $joomlaRoot . '/configuration.php';

$c = new JConfig();
$mysqli = new mysqli($c->host, $c->user, $c->password, $c->db);
$mysqli->set_charset('utf8mb4');
$p = $c->dbprefix;

$brand = 'اتو سرویس ستاره';
$oldBrand = 'اتوسرویس علی';
$metaDesc = 'تعویض تخصصی روغن گیربکس اتوماتیک، سرویس دوره‌ای و فروش روغن و فیلتر — اتو سرویس ستاره';

function q(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException($db->error . ' | SQL: ' . substr($sql, 0, 200));
    }
}

echo "=== Rebrand to {$brand} ===\n";

// 1. configuration.php
$configFile = $joomlaRoot . '/configuration.php';
$config = file_get_contents($configFile);
$config = str_replace("public \$sitename = '{$oldBrand}'", "public \$sitename = '{$brand}'", $config);
$config = str_replace("public \$fromname = '{$oldBrand}'", "public \$fromname = '{$brand}'", $config);
$config = preg_replace("/public \\\$MetaDesc = '[^']*'/", "public \$MetaDesc = '{$metaDesc}'", $config);
file_put_contents($configFile, $config);
echo "[OK] configuration.php\n";

// 2. Content categories
foreach ([8 => 'مقالات', 9 => 'نگهداری گیربکس', 10 => 'تعویض روغن'] as $id => $title) {
    q($mysqli, "UPDATE {$p}categories SET title = '" . $mysqli->real_escape_string($title) . "' WHERE id = {$id}");
}
echo "[OK] article categories\n";

// 3. VM categories
$vmCats = [
    2 => ['روغن گیربکس ATF', 'انواع روغن گیربکس اتوماتیک با کیفیت اصل'],
    3 => ['روغن موتور', 'روغن موتور مناسب خودروهای شما'],
    4 => ['فیلتر گیربکس', 'فیلتر و واشر سرویس گیربکس'],
    7 => ['سرویس و تعمیر', 'سرویس تخصصی گیربکس اتوماتیک'],
    11 => ['پکیج سرویس', 'پکیج‌های کامل تعویض روغن'],
    12 => ['لوازم یدکی', 'قطعات و متعلقات گیربکس'],
];
foreach ($vmCats as $id => [$name, $desc]) {
    q($mysqli, "UPDATE {$p}virtuemart_categories_en_gb SET category_name = '" . $mysqli->real_escape_string($name)
        . "', category_description = '" . $mysqli->real_escape_string($desc) . "' WHERE virtuemart_category_id = {$id}");
}
echo "[OK] VM categories\n";

// 4. VM products
$productsByCat = [
    2 => ['روغن ATF Castrol Transmax', 'روغن ATF Valvoline MaxLife', 'روغن ATF Mobil 1 Synthetic', 'روغن ATF Total Fluide', 'روغن ATF Idemitsu Type-T', 'روغن ATF Pentosin CHF'],
    3 => ['روغن موتور 5W30 سینتتیک', 'روغن موتور 10W40 نیمه‌سینتتیک', 'روغن موتور 5W40 فول سینتتیک', 'روغن موتور 0W20 اقتصادی', 'روغن موتور 15W40 معدنی', 'روغن موتور دیزل 10W30'],
    4 => ['فیلتر گیربکس اصلی', 'فیلتر گیربکس یدکی', 'کیت واشر پان گیربکس', 'فیلتر مغناطیسی ATF', 'صفحه فیلتر داخلی', 'اورینگ سرویس گیربکس'],
    7 => ['سرویس تعویض روغن گیربکس', 'فلاش و تعویض کامل ATF', 'تشخیص سلامت گیربکس', 'تعویض فیلتر و روغن', 'سرویس گیربکس CVT', 'سرویس گیربکس DSG'],
    11 => ['پکیج سرویس پایه', 'پکیج سرویس استاندارد', 'پکیج سرویس کامل', 'پکیج سرویس CVT', 'پکیج سرویس لوکس', 'پکیج سرویس فوری'],
    12 => ['سنسور دمای ATF', 'شلنگ کولر گیربکس', 'بوش پمپ گیربکس', 'بلبرینگ ورودی گیربکس', 'سویچ سلونوئید', 'گasket مجموعه سرویس'],
];
$res = $mysqli->query("SELECT p.virtuemart_product_id, pc.virtuemart_category_id FROM {$p}virtuemart_products p
    LEFT JOIN {$p}virtuemart_product_categories pc ON pc.virtuemart_product_id = p.virtuemart_product_id ORDER BY p.virtuemart_product_id");
$catCounters = [];
$count = 0;
while ($row = $res->fetch_assoc()) {
    $pid = (int) $row['virtuemart_product_id'];
    $cid = (int) ($row['virtuemart_category_id'] ?? 2);
    $idx = $catCounters[$cid] ?? 0;
    $names = $productsByCat[$cid] ?? $productsByCat[2];
    $name = $names[$idx % count($names)];
    $catCounters[$cid] = $idx + 1;
    $slug = 'product-' . $pid;
    $sdesc = "محصول {$brand} — {$name}";
    q($mysqli, "UPDATE {$p}virtuemart_products_en_gb SET product_name = '" . $mysqli->real_escape_string($name)
        . "', product_s_desc = '" . $mysqli->real_escape_string($sdesc)
        . "', slug = '{$slug}' WHERE virtuemart_product_id = {$pid}");
    $count++;
}
echo "[OK] VM products ({$count})\n";

// 5. Articles
$articles = [
    1 => ['حریم خصوصی', 'سیاست حفظ حریم خصوصی مشتریان ' . $brand . '.'],
    2 => ['قوانین و مقررات', 'شرایط استفاده از خدمات ' . $brand . '.'],
    3 => ['اهمیت تعویض به‌موقع روغن گیربکس', '<p>روغن گیربکس اتوماتیک وظیفه انتقال نیرو، خنک‌کاری و روانکاری دنده‌ها را دارد.</p>'],
    4 => ['علائم خرابی گیربکس اتوماتیک', '<p>تأخیر در تعویض دنده، لرزش و بو سوختگی از علائم نیاز به سرویس است.</p>'],
    5 => ['تفاوت ATF و روغن موتور', '<p>روغن گیربکس فرمولاسیون مخصوص دارد. در ' . $brand . ' از روغن استاندارد استفاده می‌شود.</p>'],
    6 => ['سرویس گیربکس CVT چیست؟', '<p>گیربکس CVT نیاز به روغن تخصصی دارد.</p>'],
    7 => ['هر چند کیلومتر روغن گیربکس عوض کنیم؟', '<p>معمولاً هر ۴۰ تا ۶۰ هزار کیلومتر طبق دفترچه سازنده.</p>'],
    8 => ['فلاش گیربکس لازم است؟', '<p>در روغن کهنه، فلاش قبل از تعویض توصیه می‌شود.</p>'],
    9 => ['انتخاب روغن مناسب گیربکس', '<p>نوع ATF باید با استاندارد سازنده مطابقت داشته باشد.</p>'],
    10 => ['مزایای سرویس دوره‌ای در ' . $brand, '<p>افزایش عمر گیربکس و رانندگی نرم‌تر.</p>'],
    11 => ['هزینه تعویض روغن گیربکس', '<p>هزینه به نوع خودرو و حجم روغن بستگی دارد.</p>'],
    12 => ['گیربکس DSG و سرویس آن', '<p>گیربکس دوکلاچه به سرویس دقیق نیاز دارد.</p>'],
    13 => ['نقش فیلتر گیربکس', '<p>فیلتر آلودگی را جذب می‌کند.</p>'],
    14 => ['اشتباهات رایج در نگهداری گیربکس', '<p>تعویض نکردن به‌موقع و روغن نامناسب.</p>'],
    15 => ['چک‌لیست قبل از سرویس', '<p>بررسی نشتی و سطح روغن قبل از سرویس.</p>'],
    16 => ['تعویض روغن در خودروهای ایرانی', '<p>پژو، دنا، تیبا و سایر خودروها.</p>'],
    17 => ['تعویض روغن در خودروهای خارجی', '<p>هیوندای، کیا، تویوتا و بنز.</p>'],
    18 => ['زمان‌بندی سرویس فصلی', '<p>قبل از سفر سلامت گیربکس را بررسی کنید.</p>'],
    19 => ['گارانتی سرویس ' . $brand, '<p>سرویس با ضمانت کیفیت روغن و دستگاه.</p>'],
    20 => ['رزرو نوبت آنلاین سرویس', '<p>از طریق سایت یا تماس رزرو کنید.</p>'],
    21 => ['درباره ' . $brand, '<p>' . $brand . ' مرکز تخصصی تعویض روغن گیربکس اتوماتیک.</p>'],
];
foreach ($articles as $id => [$title, $body]) {
    $extra = $id === 21 ? ", alias = 'about-setareh'" : '';
    q($mysqli, "UPDATE {$p}content SET title = '" . $mysqli->real_escape_string($title)
        . "', introtext = '" . $mysqli->real_escape_string($body)
        . "', `fulltext` = '', language = 'fa-IR'{$extra} WHERE id = {$id}");
}
echo "[OK] articles\n";

// 6. Tags
foreach ([2 => 'گیربکس', 3 => 'ATF', 4 => 'سرویس', 5 => 'تعمیر', 6 => 'فیلتر', 7 => 'CVT'] as $id => $title) {
    q($mysqli, "UPDATE {$p}tags SET title = '" . $mysqli->real_escape_string($title) . "' WHERE id = {$id}");
}
echo "[OK] tags\n";

// 7. Menus
$menuUpdates = [
    101 => ['خانه', 1], 119 => ['درباره ما', 1], 120 => ['لیست خدمات', 0],
    121 => ['فروشگاه', 1], 122 => ['مقالات', 1], 123 => ['تماس با ما', 1],
    124 => ['خدمات', 1], 125 => ['جزئیات سرویس', 1], 126 => ['تیم کارشناسان', 1],
    127 => ['پروفایل کارشناس', 0], 128 => ['رزرو نوبت', 1], 129 => ['تاریخچه ما', 1],
    116 => ['صفحه دوم', 0], 142 => ['صفحات نمونه', 0], 245 => ['نمونه محصول', 0],
];
$unpublish = [117, 118, 162, 163, 164, 165, 166, 167, 168, 169, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161];
foreach ($menuUpdates as $id => [$title, $pub]) {
    q($mysqli, "UPDATE {$p}menu SET title = '" . $mysqli->real_escape_string($title) . "', published = {$pub} WHERE id = {$id}");
}
foreach ($unpublish as $id) {
    q($mysqli, "UPDATE {$p}menu SET published = 0 WHERE id = {$id}");
}
echo "[OK] menus\n";

// 8. SP pages (content column holds page JSON; text is legacy/empty)
$spTitles = [1 => 'صفحه اصلی', 2 => 'صفحه اصلی ۲', 3 => 'درباره ما', 4 => 'لیست خدمات', 5 => 'خدمات', 6 => 'جزئیات سرویس', 7 => 'رزرو نوبت', 8 => 'تیم کارشناسان', 9 => 'پروفایل کارشناس', 10 => 'تماس با ما', 11 => 'تاریخچه ما', 20 => 'فوتر', 21 => 'هدر'];
foreach ($spTitles as $id => $title) {
    q($mysqli, "UPDATE {$p}sppagebuilder SET title = '" . $mysqli->real_escape_string($title) . "' WHERE id = {$id}");
}
$spColumns = ['content', 'text', 'css', 'og_title', 'og_description'];
$replacements = [
    $oldBrand => $brand,
    'اتوسرویس و تعویض روغنی ستاره' => $brand,
    'UT Resto' => $brand,
    'Buy Your Favorite Pizza' => 'تعویض روغن گیربکس تخصصی',
    'Today with 30% Off' => 'با تخفیف ویژه امروز',
    'We have many varieties of pizzas!' => 'سرویس تخصصی انواع گیربکس اتوماتیک',
    'Discover More' => 'اطلاعات بیشتر',
    'Get To Know Us' => 'آشنایی با ما',
    'Pizza is a beloved Italian dish that has become a global phenomenon' => 'اتو سرویس ستاره مرکز تخصصی تعویض روغن گیربکس اتوماتیک',
    'Call Anytime' => 'تماس در هر ساعت',
    '100% Organic' => '۱۰۰٪ تضمین کیفیت',
    'Special Offer' => 'پیشنهاد ویژه',
    'Free Delivery With Pizza Of The Day' => 'سرویس فوری با روغن اصل',
    'Finally we are here' => 'در خدمت شما هستیم',
    'Choose From Our Most Popular Dishes' => 'محبوب‌ترین خدمات ما',
    'Testimonials' => 'نظرات مشتریان',
    'Our Clients Feedback' => 'بازخورد مشتریان',
    'Delicious pizza and fast food! Quick service, friendly staff, and great flavors. Highly recommend for a satisfying meal. Will definitely come back!' => 'سرویس عالی و سریع! کارشناسان حرفه‌ای و روغن اصل. گیربکس ماشینم نرم‌تر شد. حتماً دوباره مراجعه می‌کنم.',
    'Need Help' => 'نیاز به راهنمایی',
    "We're leader in organic market" => 'پیشرو در سرویس گیربکس',
    'Blog & News' => 'مقالات و اخبار',
    'Get Update Our Foods' => 'آخرین مطالب آموزشی',
    'Get Promotions:' => 'دریافت پیشنهادها:',
    'Get Now' => 'عضویت',
    'Email' => 'ایمیل',
    'Contact' => 'تماس',
    'Category' => 'دسته‌بندی',
    'Links' => 'لینک‌ها',
    'Company' => 'شرکت',
    'Open Hours' => 'ساعات کاری',
    'About Us' => 'درباره ما',
    'Our Chefs' => 'تیم کارشناسان',
    'Next Events' => 'رویدادها',
    'Latest News' => 'آخرین اخبار',
    'Contact Us' => 'تماس با ما',
    'FAQ Page' => 'سوالات متداول',
    'Terms of Service' => 'قوانین و مقررات',
    'Privacy Policy' => 'حریم خصوصی',
    'Delivery Terms' => 'شرایط سرویس',
    'Returns Policy' => 'گارانتی خدمات',
    'Pizza' => 'گیربکس',
    'pizza' => 'گیربکس',
    'Restaurant' => 'اتو سرویس',
    'restaurant' => 'اتو سرویس',
    'Fast Food' => 'تعویض روغن',
    'Chef' => 'کارشناس',
    'Chefs' => 'کارشناسان',
    'Menu' => 'خدمات',
    'Organic' => 'تخصصی',
    'Burger' => 'روغن ATF',
    'Burritos' => 'روغن موتور',
    'Burrito' => 'روغن موتور',
    'Chicken' => 'فیلتر گیربکس',
    'Tacos' => 'لوازم یدکی',
    'Desserts' => 'پکیج سرویس',
    'Reservation' => 'رزرو نوبت',
    'Our History' => 'تاریخچه ما',
    'Services' => 'خدمات',
    'Order Now' => 'رزرو نوبت',
    'Book a Table' => 'رزرو سرویس',
    'Home 1' => 'صفحه اصلی',
    'Home 2' => 'صفحه اصلی ۲',
    '80 Road Brooklyn Street, 600 New York, USA' => 'تهران، خیابان ولیعصر، نرسیده به میدان ونک',
    'info@mycompany.xyz' => 'info@setareh-auto.ir',
    'contact@email.com' => 'info@setareh-auto.ir',
    '+92 666 888 0000' => '۰۲۱-۸۸۷۷۶۶۵۵',
    '+92 (800) - 6780' => '۰۲۱-۴۴۴۴۳۳۲۲',
    '+228 872 4444' => '۰۲۱-۸۸۷۷۶۶۵۵',
    '+775 872 4444' => '۰۹۱۲-۳۳۴۴۵۵۶',
    'Unitemplates' => $brand,
    'گیربکس is a beloved Italian dish that has become a global phenomenon' => 'مرکز تخصصی سرویس گیربکس اتوماتیک',
    'Free Delivery With گیربکس Of The Day' => 'سرویس فوری با روغن اصل',
    'Choose From Our Most Popular Dishes' => 'محبوب‌ترین خدمات ما',
    'تماس Us' => 'تماس با ما',
    'Non augue egestas, commodo velit eget, vestibulum tellus. Curabitur vulputate justo elit, at elementum orci pulvinar vel.' => 'تعویض روغن با دستگاه تمام‌اتوماتیک و ضمانت کیفیت در اتو سرویس ستاره.',
];
uksort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
foreach ($spColumns as $col) {
    foreach ($replacements as $from => $to) {
        q($mysqli, "UPDATE {$p}sppagebuilder SET `{$col}` = REPLACE(`{$col}`, '"
            . $mysqli->real_escape_string($from) . "', '" . $mysqli->real_escape_string($to) . "')");
    }
}
q($mysqli, "UPDATE {$p}sppagebuilder_assets SET assets = REPLACE(assets, 'Burger', 'روغن ATF')");
q($mysqli, "UPDATE {$p}sppagebuilder_assets SET assets = REPLACE(assets, 'Pizza', 'گیربکس')");
echo "[OK] SP pages\n";

// 8b. VM media + category slugs
$vmSlugMap = [
    'burger' => 'atf-oil', 'burritos' => 'engine-oil', 'chicken' => 'filter',
    'desserts' => 'service-repair', 'pizza' => 'vm-packages', 'tacos' => 'spare-parts',
];
foreach ($vmSlugMap as $from => $to) {
    q($mysqli, "UPDATE {$p}virtuemart_categories_en_gb SET slug = '{$to}' WHERE slug = '{$from}' AND virtuemart_category_id NOT IN (SELECT virtuemart_category_id FROM (SELECT virtuemart_category_id FROM {$p}virtuemart_categories_en_gb WHERE slug = '{$to}') t)");
}
q($mysqli, "UPDATE {$p}virtuemart_medias SET file_title = REPLACE(file_title, 'Burger', 'روغن ATF'), file_description = REPLACE(file_description, 'Burger', 'روغن ATF'), file_meta = REPLACE(file_meta, 'Burger', 'روغن ATF')");
q($mysqli, "UPDATE {$p}virtuemart_medias SET file_title = REPLACE(file_title, 'Pizza', 'گیربکس'), file_description = REPLACE(file_description, 'Pizza', 'گیربکس'), file_meta = REPLACE(file_meta, 'Pizza', 'گیربکس')");
q($mysqli, "UPDATE {$p}virtuemart_medias SET file_title = REPLACE(file_title, 'Burrito', 'روغن موتور'), file_description = REPLACE(file_description, 'Burrito', 'روغن موتور')");
echo "[OK] VM media + slugs\n";

// 8c. VirtueMart fa-IR tables (clone every *_en_gb VM lang table)
$res = $mysqli->query("SHOW TABLES LIKE '{$p}virtuemart%\\_en\\_gb'");
while ($row = $res->fetch_row()) {
    $src = $row[0];
    $dst = preg_replace('/_en_gb$/', '_fa_ir', $src);
    q($mysqli, "CREATE TABLE IF NOT EXISTS {$dst} LIKE {$src}");
    q($mysqli, "TRUNCATE {$dst}");
    q($mysqli, "INSERT INTO {$dst} SELECT * FROM {$src}");
}
echo "[OK] VM fa-IR tables\n";

// 9. Modules
foreach ([110 => 'مشتریان ما', 112 => 'آخرین مقالات', 113 => 'مقالات ویژه', 116 => 'جستجو در فروشگاه', 118 => 'سبد خرید', 120 => 'جدیدترین مطالب', 125 => 'فوتر', 127 => 'نوار بالا', 128 => 'مطالب مرتبط'] as $id => $title) {
    q($mysqli, "UPDATE {$p}modules SET title = '" . $mysqli->real_escape_string($title) . "' WHERE id = {$id}");
}
echo "[OK] modules\n";

// 10. Contact + template
q($mysqli, "UPDATE {$p}contact_details SET name = '" . $mysqli->real_escape_string($brand) . "', con_position = 'مرکز تعویض روغن گیربکس اتوماتیک' WHERE id = 1");
q($mysqli, "UPDATE {$p}template_styles SET title = '" . $mysqli->real_escape_string($brand) . "' WHERE client_id = 0 AND home = 1");
echo "[OK] contact + template\n";

// 11. Clear Joomla cache
$cacheDir = $joomlaRoot . '/cache';
$adminCache = $joomlaRoot . '/administrator/cache';
foreach ([$cacheDir, $adminCache] as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file) && basename($file) !== 'index.html') {
            @unlink($file);
        }
    }
}
echo "[OK] cache cleared\n";

echo "\n=== Done! Site rebranded to {$brand} ===\n";
