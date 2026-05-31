<?php
// تنظیمات مسیرها - با توجه به سیستم شما ست شده است
$acme_dir = 'C:/Users/rr-rad/.acme.sh';
$acme_file_dir = 'C:\Windows\System32\config\systemprofile\.acme.sh';
$git_bash_path = 'D:\git\bin\bash.exe'; // مسیر پیش‌فرض گیت‌بش (اگر فرق دارد اصلاح کنید)

// بررسی وجود گیت بش
$bash_exists = file_exists($git_bash_path);

// پردازش درخواست‌های AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$bash_exists) {
        echo json_encode(['status' => 'error', 'message' => 'مسیر Git Bash یافت نشد. لطفاً مسیر $git_bash_path را در کد کنترل کنید.']);
        exit;
    }

    $domain = trim($_POST['domain'] ?? '');
    // حذف *. از ابتدای دامنه برای پردازش درست در صورت وارد کردن توسط کاربر
    $clean_domain = str_replace('*.', '', $domain);

    if (empty($clean_domain)) {
        echo json_encode(['status' => 'error', 'message' => 'نام دامنه نمی‌تواند خالی باشد.']);
        exit;
    }
    // مرحله اول: درخواست اولیه برای دریافت رکورد TXT
    if ($_POST['action'] === 'request') {
        // اضافه کردن سوییچ --home برای اجبار به استفاده از مسیر شما
        $cmd = "\"{$git_bash_path}\" --login -c \"cd '{$acme_dir}' && ./acme.sh --issue --server letsencrypt --home '{$acme_dir}' -d '{$clean_domain}' -d '*.{$clean_domain}' --dns --yes-I-know-dns-manual-mode-enough-go-ahead-please\" 2>&1";

        exec($cmd, $output, $return_var);
        $result = implode("\n", $output);

        preg_match_all('/TXT value:\s*\'([^\']+)\'/i', $result, $matches);

        if (!empty($matches[1])) {
            echo json_encode([
                'status' => 'success',
                'step' => 1,
                'txt_records' => $matches[1],
                'host' => '_acme-challenge.' . $clean_domain,
                'raw' => $result
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'رکوردهای TXT یافت نشدند.', 'raw' => $result]);
        }
        exit;
    }

    // مرحله دوم: تایید، تمدید اجباری و صدور نهایی
    if ($_POST['action'] === 'verify') {
        // اضافه کردن سوییچ --home برای اجبار به استفاده از مسیر شما در مرحله دوم
        $cmd = "\"{$git_bash_path}\" --login -c \"cd '{$acme_dir}' && ./acme.sh --renew --server letsencrypt --home '{$acme_dir}' -d '{$clean_domain}' -d '*.{$clean_domain}' --yes-I-know-dns-manual-mode-enough-go-ahead-please --force\" 2>&1";

        exec($cmd, $output, $return_var);
        $result = implode("\n", $output);

        // آرایه‌ای از مسیرهای احتمالی برای خواندن هوشمند فایل‌ها (هم مسیر کاربری هم مسیر سیستم۳۲)
        $possible_folders = [
            $acme_dir . '/' . $clean_domain . '_ecc',
            $acme_dir . '/' . $clean_domain,
            'C:/Windows/System32/config/systemprofile/.acme.sh/' . $clean_domain . '_ecc',
            'C:/Windows/System32/config/systemprofile/.acme.sh/' . $clean_domain
        ];

        $cert_folder = '';
        foreach ($possible_folders as $folder) {
            if (is_dir($folder)) {
                $cert_folder = $folder;
                break;
            }
        }

        if (!empty($cert_folder)) {
            $cert = @file_get_contents($cert_folder . '/' . $clean_domain . '.cer') ?: '';
            $key = @file_get_contents($cert_folder . '/' . $clean_domain . '.key') ?: '';
            $ca = @file_get_contents($cert_folder . '/ca.cer') ?: '';
            $fullchain = @file_get_contents($cert_folder . '/fullchain.cer') ?: '';

            if ($cert && $key) {
                echo json_encode([
                    'status' => 'success',
                    'step' => 2,
                    'cert' => $cert,
                    'key' => $key,
                    'ca' => $ca,
                    'fullchain' => $fullchain,
                    'raw' => $result
                ]);
                exit;
            }
        }

        echo json_encode(['status' => 'error', 'message' => 'تایید نهایی با خطا مواجه شد یا فایل‌ها یافت نشدند.', 'raw' => $result]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACME Let's Encrypt Studio</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;700&display=swap');

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: radial-gradient(circle at center, #0f172a 0%, #020617 100%);
        }

        .cyber-glow {
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.15;
            }

            50% {
                opacity: 0.35;
            }
        }
    </style>
</head>

<body class="text-slate-200 min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden">

    <div
        class="absolute top-10 left-10 w-72 h-72 bg-indigo-600 rounded-full blur-[120px] animate-pulse-slow pointer-events-none">
    </div>
    <div
        class="absolute bottom-10 right-10 w-96 h-96 bg-purple-600 rounded-full blur-[150px] animate-pulse-slow pointer-events-none">
    </div>

    <div class="w-full max-w-4xl bg-slate-900/80 backdrop-blur-xl rounded-2xl p-6 md:p-8 cyber-glow z-10">

        <div class="flex items-center justify-between border-b border-slate-800 pb-6 mb-6">
            <div class="flex items-center gap-4">
                <div
                    class="bg-indigo-600/20 p-3 rounded-xl border border-indigo-500/30 text-indigo-400 text-2xl animate-pulse">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-white tracking-wide">Let's Encrypt Wildcard Generator
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">مدیریت مستقیم acme.sh با سرور رسمی لِتس‌انکریپت</p>
                </div>
            </div>
            <div class="text-left">
                <span class="text-xs bg-slate-800 text-slate-300 px-3 py-1.5 rounded-full border border-slate-700">
                    <i class="fa-solid fa-server text-emerald-400 ml-1"></i> Localhost
                </span>
            </div>
        </div>

        <?php if (!$bash_exists): ?>
            <div
                class="bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl p-4 text-sm mb-6 flex items-start gap-3">
                <i class="fa-solid fa-triangle-exclamation mt-1 text-lg"></i>
                <div>
                    <strong class="block mb-1">خطا: Git Bash پیدا نشد!</strong>
                    مسیر مشخص شده برای `bash.exe` درست نیست.
                </div>
            </div>
        <?php endif; ?>

        <form id="sslForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">نام دامنه</label>
                <div class="relative rounded-xl shadow-sm">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <input type="text" id="domain" name="domain" required placeholder="domain.com"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl py-3 pr-11 pl-4 text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 font-mono text-left tracking-wider">
                </div>
            </div>

            <button type="button" id="btnRequest"
                class="w-full bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white font-medium py-3 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 cursor-pointer">
                <i class="fa-solid fa-paper-plane"></i>
                <span>مرحله اول: اجرای Issue و دریافت TXT رکوردها</span>
            </button>
        </form>

        <div id="loader" class="hidden my-8 p-6 bg-slate-950 rounded-xl border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs text-indigo-400 font-mono animate-pulse">Running acme.sh script on Let's Encrypt
                    server...</span>
                <i class="fa-solid fa-circle-notch animate-spin text-indigo-500 text-lg"></i>
            </div>
            <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden">
                <div class="bg-indigo-500 h-full w-1/2 animate-[loading_1.5s_infinite_ease-in-out] rounded-full"></div>
            </div>
            <style>
                @keyframes loading {
                    0% {
                        transform: translateX(200%);
                    }

                    100% {
                        transform: translateX(-100%);
                    }
                }
            </style>
        </div>

        <div id="dnsSection" class="hidden mt-8 space-y-4 border-t border-slate-800 pt-6">
            <div
                class="bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-xl p-4 text-sm flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation mt-1 text-lg"></i>
                <div>
                    رکوردهای زیر را در بخش DNS دامنه خود به صورت دو رکورد جداگانه تعریف کنید. پس از اعمال، روی دکمه صدور
                    نهایی کلیک کنید.
                </div>
            </div>

            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-4 font-mono text-sm text-left">
                <div>
                    <span class="text-slate-500 block text-xs font-sans text-right mb-1">نوع رکورد (Type):</span>
                    <span
                        class="bg-slate-900 text-indigo-400 px-3 py-1 rounded border border-indigo-500/20 text-xs">TXT</span>
                </div>
                <div>
                    <span class="text-slate-500 block text-xs font-sans text-right mb-1">نام میزبان (Host /
                        Name):</span>
                    <div class="flex items-center justify-between bg-slate-900 p-2 rounded border border-slate-800">
                        <span id="dnsHost" class="text-slate-300"></span>
                        <button onclick="copyToClipboard('dnsHost')"
                            class="text-xs text-slate-500 hover:text-white px-2 py-1 bg-slate-950 rounded border border-slate-800 transition-all cursor-pointer"><i
                                class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
                <div>
                    <span class="text-slate-500 block text-xs font-sans text-right mb-1">مقادیر رکورد (Values):</span>
                    <div id="dnsValuesContainer" class="space-y-2"></div>
                </div>
            </div>

            <button type="button" id="btnVerify"
                class="w-full bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99] text-white font-medium py-3 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 cursor-pointer">
                <i class="fa-solid fa-circle-check"></i>
                <span>مرحله دوم: اجرای Renew و ساخت گواهی نهایی</span>
            </button>
        </div>

        <div id="resultSection" class="hidden mt-8 space-y-6 border-t border-slate-800 pt-6">
            <div
                class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl p-4 text-center font-bold">
                <i class="fa-solid fa-circle-check ml-2"></i> گواهی با موفقیت صادر و فایل‌ها ذخیره شدند.
            </div>

            <div class="grid grid-cols-1 gap-4 font-mono text-xs">
                <div class="space-y-1">
                    <div class="flex justify-between items-center font-sans">
                        <label class="text-slate-400 font-medium">کلید خصوصی (Private Key / .key)</label>
                        <button onclick="copyToClipboard('resKey')"
                            class="text-indigo-400 hover:text-indigo-300 transition-all cursor-pointer"><i
                                class="fa-regular fa-copy ml-1"></i>کپی</button>
                    </div>
                    <textarea id="resKey" readonly rows="6"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-emerald-500 focus:outline-none text-left"></textarea>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center font-sans">
                        <label class="text-slate-400 font-medium">گواهینامه (Certificate / .cer)</label>
                        <button onclick="copyToClipboard('resCert')"
                            class="text-indigo-400 hover:text-indigo-300 transition-all cursor-pointer"><i
                                class="fa-regular fa-copy ml-1"></i>کپی</button>
                    </div>
                    <textarea id="resCert" readonly rows="6"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-indigo-400 focus:outline-none text-left"></textarea>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center font-sans">
                        <label class="text-slate-400 font-medium">فول چِین (Full Chain / fullchain.cer)</label>
                        <button onclick="copyToClipboard('resFull')"
                            class="text-indigo-400 hover:text-indigo-300 transition-all cursor-pointer"><i
                                class="fa-regular fa-copy ml-1"></i>کپی</button>
                    </div>
                    <textarea id="resFull" readonly rows="6"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-slate-400 focus:outline-none text-left"></textarea>
                </div>
            </div>
        </div>

        <div class="mt-8">
            <button onclick="document.getElementById('consoleLog').classList.toggle('hidden')"
                class="text-xs text-slate-500 hover:text-slate-300 transition-all cursor-pointer flex items-center gap-1">
                <i class="fa-solid fa-terminal"></i> نمایش/پنهان کردن کنسول لاگ خام ترمینال (Terminal Output)
            </button>
            <pre id="consoleLog"
                class="hidden mt-2 p-4 bg-black rounded-xl border border-slate-800 text-slate-400 font-mono text-[11px] text-left overflow-x-auto max-h-60 whitespace-pre-wrap"
                style="direction: ltr;"></pre>
        </div>

    </div>

    <script>
        const btnRequest = document.getElementById('btnRequest');
        const btnVerify = document.getElementById('btnVerify');
        const loader = document.getElementById('loader');
        const dnsSection = document.getElementById('dnsSection');
        const resultSection = document.getElementById('resultSection');
        const consoleLog = document.getElementById('consoleLog');

        btnRequest.addEventListener('click', async () => {
            const domain = document.getElementById('domain').value;
            if (!domain) return alert('لطفاً نام دامنه را وارد کنید.');

            setLoading(true);
            dnsSection.classList.add('hidden');
            resultSection.classList.add('hidden');

            try {
                const response = await fetch('ssl.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=request&domain=${encodeURIComponent(domain)}`
                });
                const data = await response.json();
                if (data.raw) { consoleLog.textContent = data.raw; }

                if (data.status === 'success' && data.step === 1) {
                    document.getElementById('dnsHost').textContent = data.host;
                    const valuesContainer = document.getElementById('dnsValuesContainer');
                    valuesContainer.innerHTML = '';

                    data.txt_records.forEach((val, index) => {
                        valuesContainer.innerHTML += `
                            <div class="flex items-center justify-between bg-slate-900 p-2 rounded border border-slate-800">
                                <span id="dnsVal_${index}" class="text-amber-400">${val}</span>
                                <button type="button" onclick="copyToClipboard('dnsVal_${index}')" class="text-xs text-slate-500 hover:text-white px-2 py-1 bg-slate-950 rounded border border-slate-800 transition-all cursor-pointer"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        `;
                    });
                    dnsSection.classList.remove('hidden');
                } else {
                    alert('خطا در مرحله اول. برای جزییات بیشتر باکس ترمینال پایین صفحه را باز کنید.');
                }
            } catch (err) {
                alert('خطایی در ارتباط با سرور رخ داد.');
            } finally {
                setLoading(false);
            }
        });

        btnVerify.addEventListener('click', async () => {
            const domain = document.getElementById('domain').value;
            setLoading(true);

            try {
                const response = await fetch('ssl.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=verify&domain=${encodeURIComponent(domain)}`
                });
                const data = await response.json();
                if (data.raw) { consoleLog.textContent = data.raw; }

                if (data.status === 'success' && data.step === 2) {
                    document.getElementById('resKey').value = data.key;
                    document.getElementById('resCert').value = data.cert;
                    document.getElementById('resFull').value = data.fullchain;
                    resultSection.classList.remove('hidden');
                    resultSection.scrollIntoView({ behavior: 'smooth' });
                } else {
                    alert('خطا در احراز هویت. مطمئن شوید هر دو رکورد کاملاً ست شده باشند. خروجی ترمینال را چک کنید.');
                }
            } catch (err) {
                alert('خطا در ارتباط با سرور.');
            } finally {
                setLoading(false);
            }
        });

        function setLoading(state) {
            if (state) {
                loader.classList.remove('hidden');
                btnRequest.disabled = true; btnVerify.disabled = true;
                btnRequest.classList.add('opacity-50'); btnVerify.classList.add('opacity-50');
            } else {
                loader.classList.add('hidden');
                btnRequest.disabled = false; btnVerify.disabled = false;
                btnRequest.classList.remove('opacity-50'); btnVerify.classList.remove('opacity-50');
            }
        }

        function copyToClipboard(elementId) {
            const el = document.getElementById(elementId);
            const value = el.tagName === 'TEXTAREA' || el.tagName === 'INPUT' ? el.value : el.textContent;
            navigator.clipboard.writeText(value).then(() => alert('با موفقیت کپی شد!')).catch(() => alert('خطا در کپی.'));
        }
    </script>
</body>

</html>
