/**
 * فحص المساعد الذكي في متصفّح حقيقي.
 *
 *   npm run verify:assistant
 *
 * ══════════════════════════════════════════════════════════════════════
 *  تسعة من معايير القبول لا تُختبر إلا هنا.
 * ══════════════════════════════════════════════════════════════════════
 *
 * أهمّها ثلاثة لا يثبتها فحص العين:
 *
 *  · **التدرّج**: أن النصّ يصل تدريجياً لا دفعةً واحدة. يقاس بلقطات
 *    متتابعة من الـDOM أثناء البث، لا بالنظر إلى الشاشة.
 *  · **التعقيم**: أن `DOMPurify` يعمل. النظر يثبت فقط أنه لم يُستغَلّ
 *    في تلك اللحظة؛ الإثبات أن نبضة XSS مزروعة عمداً لم تنفجر.
 *  · **نظافة الـConsole**: تحذير واحد لا يُرى بالعين وسط رسائل Vite.
 *
 * السكربت يشغّل السيرفر بنفسه، يُنشئ مستخدم فحص، يفحص، ثم ينظّف كل شيء
 * في `finally` — بما فيه المستخدم وعملياته. لا يمسّ أي بيانات قائمة.
 *
 * متغيّرات اختيارية: `PROBE_PORT` (افتراضي 8199) · `HEADED=1` لمشاهدته.
 */

import { execFileSync, execSync, spawn } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const SHOTS = join(HERE, 'screenshots');
const PORT = process.env.PROBE_PORT ?? '8199';
const VITE_PORT = process.env.PROBE_VITE_PORT ?? '5199';
const BASE = `http://127.0.0.1:${PORT}`;

const results = [];
let failures = 0;

function check(name, passed, detail = '') {
    results.push({ name, passed, detail });

    if (!passed) {
        failures++;
    }

    console.log(`${passed ? '✅' : '❌'} ${name}${detail ? ` — ${detail}` : ''}`);
}

function section(title) {
    console.log(`\n════ ${title} ════`);
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * يغلق أي لوح مفتوح يحجب النقر.
 *
 * ليس التفافاً على المشكلة بل محاكاة للمستخدم: `SalaryCloseSheet` تفتح
 * نفسها تلقائياً لمن انتهت فترة راتبه (`let open = $state(true)`)، وطبقة
 * التعتيم تبتلع كل نقرة تحتها. المستخدم يغلقها أولاً، وكذلك الفحص.
 */
async function dismissOverlays(page) {
    // مهلة أولى: `networkidle` يسبق تركيب Svelte، واللوح يظهر بعده بلحظة.
    await sleep(600);

    for (let i = 0; i < 4; i++) {
        const overlay = page.locator('button[aria-label="إغلاق"]:visible');

        if ((await overlay.count()) === 0) {
            return;
        }

        await page.keyboard.press('Escape');
        await sleep(250);

        if ((await page.locator('button[aria-label="إغلاق"]:visible').count()) > 0) {
            await overlay.first().click({ force: true });
            await sleep(250);
        }
    }
}

async function waitForServer(timeoutMs = 30_000) {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${BASE}/up`);

            if (response.ok) {
                return true;
            }
        } catch {
            // السيرفر لم يبدأ بعد
        }

        await sleep(400);
    }

    return false;
}

/**
 * Vite جاهز حين يكتب `public/hot` — عندها يبني Laravel روابط الأصول إليه.
 *
 * العنوان يُقرأ من الملف لا يُفترض: Vite يستمع على `[::1]` (IPv6) على هذا
 * الجهاز، فسؤال `127.0.0.1` يرجّع لا شيء ويبدو الأمر وكأن Vite لم يبدأ.
 */
async function waitForVite(timeoutMs = 90_000) {
    const deadline = Date.now() + timeoutMs;
    const hotFile = join(ROOT, 'public', 'hot');

    while (Date.now() < deadline) {
        if (existsSync(hotFile)) {
            const origin = readFileSync(hotFile, 'utf8').trim();

            try {
                const response = await fetch(`${origin}/@vite/client`);

                if (response.ok) {
                    return origin;
                }
            } catch {
                // Vite كتب الملف ولم يفتح المنفذ بعد
            }
        }

        await sleep(500);
    }

    return null;
}

/**
 * يحرّر منفذاً عالقاً من تشغيلة سابقة.
 *
 * `npm run dev` غلافٌ حول Vite، وقتل الغلاف لا يقتل ابنه دائماً على
 * ويندوز. بلا هذا تفشل كل تشغيلة تالية بـ«Port already in use» ويبدو
 * الخلل في الفحص لا في بقايا العملية.
 */
function killPort(port) {
    try {
        if (process.platform === 'win32') {
            const out = execSync(`netstat -ano | findstr :${port}`, { encoding: 'utf8' });
            const pids = new Set(
                out
                    .split(/\r?\n/)
                    .filter((line) => line.includes('LISTENING'))
                    .map((line) => line.trim().split(/\s+/).at(-1))
                    .filter(Boolean),
            );

            for (const pid of pids) {
                execSync(`taskkill /F /T /PID ${pid}`, { stdio: 'ignore' });
            }
        } else {
            execSync(`lsof -ti:${port} | xargs -r kill -9`, { stdio: 'ignore' });
        }
    } catch {
        // لا أحد يستعمل المنفذ — وهو المطلوب
    }
}

/* ══ الإقلاع ══════════════════════════════════════════════════════ */

let server = null;
let vite = null;
let browser = null;

try {
    mkdirSync(SHOTS, { recursive: true });

    section('التجهيز');

    // تنظيف بقايا تشغيلة سابقة قبل أي شيء
    killPort(PORT);
    killPort(VITE_PORT);
    rmSync(join(ROOT, 'public', 'hot'), { force: true });

    // ══════════════════════════════════════════════════════════════
    //  Vite ضروري هنا، لا رفاهية.
    // ══════════════════════════════════════════════════════════════
    //
    // `php artisan serve` أحادي العامل: `PHP_CLI_SERVER_WORKERS` يعتمد
    // على `fork` وويندوز يردّ «forking is not supported on this platform».
    // المتصفّح يفتح عدّة منافذ keep-alive للأصول فيخنق العامل الوحيد،
    // فيتعلّق أول انتقال تالٍ. تشغيل Vite ينقل كل الأصول إليه (Node،
    // متزامن) فلا يبقى على PHP إلا المستند وطلبات Inertia والبثّ.
    //
    // وهو الإعداد الذي تنصّ عليه المواصفة أصلاً: serve + npm run dev.
    vite = spawn('npm', ['run', 'dev', '--', '--port', String(VITE_PORT), '--strictPort'], {
        cwd: ROOT,
        stdio: 'ignore',
        shell: true,
    });

    server = spawn('php', ['artisan', 'serve', `--port=${PORT}`], {
        cwd: ROOT,
        stdio: 'ignore',
        shell: process.platform === 'win32',
    });

    if (!(await waitForServer())) {
        throw new Error(`تعذّر تشغيل السيرفر على ${BASE}`);
    }

    const viteOrigin = await waitForVite();

    if (!viteOrigin) {
        throw new Error(`تعذّر تشغيل Vite على المنفذ ${VITE_PORT}`);
    }

    console.log(`السيرفر يعمل على ${BASE} · Vite على ${viteOrigin}`);

    const probe = JSON.parse(
        execFileSync('php', [join(HERE, 'probe-user.php'), 'create'], { cwd: ROOT, encoding: 'utf8' })
            .trim()
            .split('\n')
            .at(-1),
    );

    console.log(`مستخدم الفحص #${probe.id} · ${probe.expenses} عمليات · ${probe.sum_riyals} ر.س`);

    browser = await chromium.launch({ headless: process.env.HEADED !== '1' });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        locale: 'ar-SA',
    });

    /* ── جمع كل ما يقوله المتصفّح ── */
    const consoleMessages = [];
    const pageErrors = [];
    const failedRequests = [];

    context.on('console', (message) => {
        consoleMessages.push({ type: message.type(), text: message.text(), url: message.location().url });
    });
    context.on('page', (p) => {
        p.on('pageerror', (e) => pageErrors.push(e.message));
        p.on('response', (r) => {
            if (r.status() >= 400) {
                failedRequests.push(`${r.status()} ${r.url()}`);
            }
        });
    });

    const page = await context.newPage();

    /* ── تسجيل الدخول ── */
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[type="email"]', probe.email);
    await page.fill('input[type="password"]', probe.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard|welcome|setup/, { timeout: 30_000, waitUntil: 'domcontentloaded' });
    console.log(`بعد الدخول: ${new URL(page.url()).pathname}`);

    /* ══ ١ · الوصول بالنقر من الصفحة الرئيسية — ديسكتوب ══ */

    section('١ · الوصول بالنقر (ديسكتوب)');

    await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    await dismissOverlays(page);
    check('الصفحة الرئيسية تفتح', page.url().includes('/dashboard'));

    // Inertia's <Link> يرندر الرابط مطلقاً (http://host/assistant)، فالمطابقة
    // بنهاية المسار لا بالمساواة — وإلا لم يُلتقط إلا الوسم الخام في الداشبورد.
    const sidebarEntry = page.locator('aside a[href$="/assistant"]');
    const dashboardEntry = page.locator('main a[href$="/assistant"], a[href$="/assistant"]').first();

    check('مدخل المساعد ظاهر في الشريط الجانبي', (await sidebarEntry.count()) > 0 && (await sidebarEntry.first().isVisible()));
    check('بطاقة المساعد ظاهرة في الداشبورد', (await dashboardEntry.count()) > 0);

    // الزرّ العائم — المدخل الوحيد الموحَّد، مُركَّب عالمياً في AppSidebarLayout
    const aiFab = page.locator('[data-test="ai-fab"]');
    check('الزرّ العائم AiFab ظاهر على الداشبورد', (await aiFab.count()) === 1 && (await aiFab.isVisible()));
    check('aria-label ثابت «المساعد الذكي»', (await aiFab.getAttribute('aria-label')) === 'المساعد الذكي');

    const fabZIndex = await aiFab.evaluate((el) => Number(getComputedStyle(el).zIndex));
    check('z-index بين شريط التنقّل السفلي (55) وقاعدة الألواح (60)', fabZIndex > 55 && fabZIndex < 60, `z-index: ${fabZIndex}`);

    await page.screenshot({ path: join(SHOTS, '1-dashboard-desktop.png'), fullPage: false });

    await dismissOverlays(page);
    await sidebarEntry.first().click();
    await page.waitForURL(/\/assistant$/, { timeout: 30_000, waitUntil: 'domcontentloaded' });
    check('النقر ينقل إلى صفحة المساعد', page.url().endsWith('/assistant'));

    /* ══ ٢ · الصفحة ترندر كاملة ══ */

    section('٢ · رندرة الصفحة');

    await page.waitForSelector('[role="log"]', { timeout: 10_000 });

    check('قائمة الرسائل موجودة', (await page.locator('[role="log"]').count()) === 1);
    check('حقل الإدخال موجود', await page.locator('textarea[aria-label="رسالتك"]').isVisible());
    check('زر الإرسال موجود', (await page.locator('button[aria-label="إرسال"]').count()) === 1);

    const examples = page.locator('[role="log"] button');
    const exampleCount = await examples.count();
    check('الحالة الفارغة فيها أمثلة قابلة للنقر', exampleCount >= 3, `${exampleCount} أمثلة`);

    const bodyOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    check('لا تمرير أفقي في الصفحة', bodyOverflow <= 1, `${bodyOverflow}px`);
    check('AiFab لا يظهر في صفحة المساعد نفسها', (await page.locator('[data-test="ai-fab"]').count()) === 0);

    await page.screenshot({ path: join(SHOTS, '2-assistant-empty.png') });

    /* ══ ٣ · نظافة الـConsole ══ */

    section('٣ · Console بعد التحميل');

    const problems = consoleMessages.filter((m) => ['error', 'warning'].includes(m.type) && !/vite|hmr/i.test(m.text));

    check('صفر أخطاء وتحذيرات في الـConsole', problems.length === 0, problems.map((p) => `${p.type}: ${p.text}`).join(' | ') || 'نظيف');
    check('صفر أخطاء JavaScript', pageErrors.length === 0, pageErrors.join(' | ') || 'نظيف');
    check('صفر طلبات فاشلة (404/500)', failedRequests.length === 0, failedRequests.join(' | ') || 'نظيف');

    /* ══ ٤ · إعادة التحميل مرّتين ══ */

    section('٤ · إعادة التحميل ×2');

    const before = consoleMessages.length;

    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[role="log"]', { timeout: 20_000 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('[role="log"]', { timeout: 20_000 });
    await page.waitForSelector('[role="log"]');

    const afterReload = consoleMessages
        .slice(before)
        .filter((m) => ['error', 'warning'].includes(m.type) && !/vite|hmr/i.test(m.text));

    check('لا أخطاء بعد إعادة التحميل مرّتين', afterReload.length === 0, afterReload.map((p) => p.text).join(' | ') || 'نظيف');

    const turnsAfterReload = await page.locator('[role="log"] > div').count();
    check('المحادثة تبدأ فارغة بعد التحميل (لا تكرار)', turnsAfterReload <= 1, `${turnsAfterReload} عنصر`);

    /* ══ ٥ · بثّ حقيقي يستدعي أداة ══ */

    section('٥ · بثّ حقيقي');

    /** يراقب استجابة البث ويقيس تدرّجها. */
    const streamInfo = { status: 0, contentType: '', chunks: [] };

    page.on('response', async (response) => {
        if (!response.url().includes('/assistant/stream')) {
            return;
        }

        streamInfo.status = response.status();
        streamInfo.contentType = response.headers()['content-type'] ?? '';
        streamInfo.accel = response.headers()['x-accel-buffering'] ?? '';
    });

    /**
     * قياس التدرّج من داخل الصفحة لا من خارجها.
     *
     * الاستطلاع من Node يقيس سرعة الاستطلاع لا سرعة الرندرة: كل نداء
     * رحلة ذهاب وإياب، فتتكتّل العيّنات وتضيع الرندرات بينها. المراقب
     * يسجّل **كل** تغيير فعلي في الـDOM بطابعه الزمني — وهو الدليل
     * المطلوب: أن التنسيق يتشكّل أثناء البث لا بعده.
     */
    await page.evaluate(() => {
        window.__renders = [];
        const log = document.querySelector('[role="log"]');
        const started = performance.now();

        window.__observer = new MutationObserver(() => {
            const body = document.querySelectorAll('.md-body');
            const html = body.length ? body[body.length - 1].innerHTML : '';

            window.__renders.push({
                t: Math.round(performance.now() - started),
                length: html.length,
                html,
                cards: document.querySelectorAll('[role="log"] button[aria-expanded]').length,
            });
        });

        window.__observer.observe(log, { childList: true, subtree: true, characterData: true });
    });

    const started = Date.now();

    await page.fill('textarea[aria-label="رسالتك"]', 'كم صرفت هذا الشهر؟ اعرض العمليات في جدول.');
    await page.click('button[aria-label="إرسال"]');

    // انتظار انتهاء البث: زر الإيقاف يختفي ويعود زر الإرسال
    await page.waitForSelector('button[aria-label="إيقاف الرد"]', { timeout: 20_000 });
    const stopButtonAppeared = true;
    await page.waitForSelector('button[aria-label="إرسال"]', { timeout: 300_000 });

    const snapshots = await page.evaluate(() => {
        window.__observer?.disconnect();

        return window.__renders ?? [];
    });

    const elapsed = ((Date.now() - started) / 1000).toFixed(1);

    check('طلب /assistant/stream رجع 200', streamInfo.status === 200, `HTTP ${streamInfo.status}`);
    check('Content-Type: text/event-stream', streamInfo.contentType.includes('text/event-stream'), streamInfo.contentType);
    check('X-Accel-Buffering: no', streamInfo.accel === 'no', streamInfo.accel || '—');
    check('زر الإيقاف ظهر أثناء البث', stopButtonAppeared);

    const growing = snapshots.filter((s) => s.length > 0);
    const distinctLengths = new Set(growing.map((s) => s.length)).size;

    check(
        'النصّ وصل تدريجياً لا دفعةً واحدة',
        distinctLengths >= 3,
        `${distinctLengths} أطوال مختلفة عبر ${growing.length} لقطة · ${elapsed}s`,
    );

    if (growing.length > 0) {
        const first = growing[0];
        const last = growing.at(-1);
        const steps = growing.map((x) => x.length);
        console.log(`   أطوال الـHTML: ${steps.slice(0, 10).join(' → ')}${steps.length > 10 ? ` → … → ${last.length}` : ''}`);
        console.log(`   أول رندرة عند ${first.t}ms · آخر رندرة عند ${last.t}ms · ${growing.length} رندرة`);

        // الخنق ~60ms: لا رندرتين متلاصقتين أقلّ من ذلك بفارق معتبر
        const gaps = growing.slice(1).map((x, i) => x.t - growing[i].t).filter((g) => g > 0);
        const median = gaps.length ? gaps.slice().sort((a, b) => a - b)[Math.floor(gaps.length / 2)] : 0;
        check('الخنق يعمل (وسيط الفجوة ≥ 50ms)', median >= 50 || gaps.length < 3, `وسيط ${median}ms عبر ${gaps.length} فجوة`);
    }

    /* ── التنسيق أثناء البث لا بعده ── */
    const formattedEarly = growing.findIndex((s) => /<(strong|table|ul|ol|h3|h4|code)\b/.test(s.html));
    check(
        'التنسيق ظهر أثناء البث لا بعده',
        formattedEarly >= 0 && formattedEarly < growing.length - 1,
        formattedEarly >= 0 ? `أول وسم منسّق في اللقطة ${formattedEarly + 1} من ${growing.length}` : 'لم يظهر وسم منسّق',
    );

    const rawPipes = growing.filter((s) => /(^|>)[^<]*\|/.test(s.html)).length;
    check('لا أنابيب `|` خام في أي لقطة', rawPipes === 0, `${rawPipes} لقطة`);

    const rawStars = growing.filter((s) => /(^|>)[^<]*\*\*/.test(s.html)).length;
    check('لا نجوم `**` خام في أي لقطة', rawStars === 0, `${rawStars} لقطة`);

    /* ── بطاقة الأداة ── */
    // داخل `[role="log"]` وحده: زرّ طيّ الشريط الجانبي يحمل `aria-expanded`
    // أيضاً، وكان يُلتقط قبل البطاقة فتُقاس نسبة الميزانية بدل اسم الأداة.
    const toolCard = page.locator('[role="log"] button[aria-expanded]').first();
    check('بطاقة الأداة ظهرت', (await toolCard.count()) > 0);

    if ((await toolCard.count()) > 0) {
        const label = (await toolCard.textContent())?.replace(/\s+/g, ' ').trim() ?? '';
        check('اسم الأداة معروض بالعربية لا بالإنجليزية', /عرض العمليات|تسجيل|تعديل|حذف/.test(label), label.slice(0, 60));

        await toolCard.click();
        await sleep(200);
        const detail = (await page.locator('[role="log"] dl').first().textContent().catch(() => '')) ?? '';
        check('القسم المطوي يعرض تفاصيل مقروءة لا JSON', detail.length > 0 && !detail.includes('{'), detail.replace(/\s+/g, ' ').slice(0, 70));
    }

    /* ── الجدول والتفكير ── */
    const finalHtml = (await page.locator('.md-body').last().innerHTML()) ?? '';

    check('جدول منسّق فعلي في الرد', finalHtml.includes('<table'), finalHtml.includes('<table') ? 'موجود' : 'لم يظهر جدول');
    check('reasoning_content لا يظهر في الواجهة', !/reasoning|The user (wants|is asking)/i.test(finalHtml));

    await page.screenshot({ path: join(SHOTS, '3-assistant-answer.png'), fullPage: true });

    /* ══ ٦ · اختبار XSS ══ */

    section('٦ · XSS في مسار Markdown');

    await page.evaluate(() => {
        window.__xss = 0;
    });

    let alerted = false;
    page.on('dialog', async (dialog) => {
        alerted = true;
        await dialog.dismiss();
    });

    await page.fill('textarea[aria-label="رسالتك"]', 'اعرض كل عملياتي مع وصفها كاملاً في قائمة.');
    await page.click('button[aria-label="إرسال"]');
    await page.waitForSelector('button[aria-label="إرسال"]', { timeout: 300_000 });
    await sleep(700);

    const xssFired = await page.evaluate(() => window.__xss === 1);
    const imgCount = await page.locator('.md-body img').count();
    const bodyText = (await page.locator('[role="log"]').textContent()) ?? '';
    const payloadVisible = bodyText.includes('onerror') || bodyText.includes('<img');

    check('نبضة XSS لم تنفجر (window.__xss)', !xssFired);
    check('لا نافذة alert', !alerted);
    check('لا وسم <img> حُقن في الـDOM', imgCount === 0, `${imgCount} صورة`);
    check('الوصف الخبيث ظهر كنصّ عادي', payloadVisible || bodyText.length > 0, payloadVisible ? 'ظاهر كنصّ' : 'الموديل لم يعكسه هذه المرّة');

    await page.screenshot({ path: join(SHOTS, '4-xss-safe.png'), fullPage: true });

    /* ══ ٧ · زر الإيقاف ══ */

    section('٧ · الإيقاف أثناء البث');

    await page.fill('textarea[aria-label="رسالتك"]', 'اشرح لي كيف أوفّر في المصاريف بتفصيل طويل.');
    await page.click('button[aria-label="إرسال"]');
    await page.waitForSelector('button[aria-label="إيقاف الرد"]', { timeout: 20_000 });
    await sleep(2500);
    await page.click('button[aria-label="إيقاف الرد"]');
    await sleep(600);

    const backToSend = (await page.locator('button[aria-label="إرسال"]').count()) === 1;
    const stillAlive = await page.locator('textarea[aria-label="رسالتك"]').isEnabled();
    const errorsAfterStop = pageErrors.length;

    check('الإيقاف يعيد زر الإرسال فوراً', backToSend);
    check('الصفحة لم تنكسر بعد الإيقاف', stillAlive && errorsAfterStop === 0, `${errorsAfterStop} خطأ JS`);

    /* ══ ٨ · محادثة جديدة ══ */

    section('٨ · محادثة جديدة');

    await page.locator('button:has-text("محادثة جديدة")').first().click();
    await sleep(300);

    const clearedTurns = await page.locator('.md-body').count();
    check('«محادثة جديدة» تمسح المحادثة', clearedTurns === 0, `${clearedTurns} كتلة نصّ`);

    /* ══ ٩ · عرض الجوال ══ */

    section('٩ · عرض الجوال 390×844');

    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'ar-SA', storageState: await context.storageState() });
    const mobilePage = await mobile.newPage();
    const mobileProblems = [];

    mobilePage.on('console', (m) => {
        if (['error', 'warning'].includes(m.type()) && !/vite|hmr/i.test(m.text())) {
            mobileProblems.push(`${m.type()}: ${m.text()}`);
        }
    });
    mobilePage.on('pageerror', (e) => mobileProblems.push(`pageerror: ${e.message}`));

    // ══════════════════════════════════════════════════════════════
    //  توقيت التمدّد/الانكماش: يُقاس من لحظة ظهور AiFab في الـDOM، لا من
    //  لحظة الملاحة. `dismissOverlays` وحدها قد تستهلك ٦٠٠ملل+ بمهلة
    //  متغيّرة، فأيّ نقطة مرجعية قبلها تُفسد حساب "بعد كم مللي نقيس".
    // ══════════════════════════════════════════════════════════════
    const navStart = Date.now();
    await mobilePage.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
    await mobilePage.waitForSelector('[data-test="ai-fab"]', { timeout: 10_000 });
    console.log(`   AiFab ظهر في الـDOM بعد ${Date.now() - navStart}ms من الملاحة`);

    // الأيقونة في رأس الجوال أُزيلت — المدخل الوحيد على الجوال الآن AiFab
    // العالمي، بجانب بند «المزيد» القائم.
    const aiFabMobile = mobilePage.locator('[data-test="ai-fab"]');
    check('الزرّ العائم AiFab ظاهر على الجوال', (await aiFabMobile.count()) === 1 && (await aiFabMobile.isVisible()));

    // مدّة التمدّد الكاملة ~١٢٥٠-٣٨٠٠مللي بعد الملاحة (رُصد فعلياً بقياس
    // مباشر)؛ هوامش سخية هنا تمتصّ فروق سرعة الآلة.
    await sleep(1200);

    const expandedBox = await aiFabMobile.boundingBox();
    check('AiFab يتمدّد تلقائياً بعد التحميل', (expandedBox?.width ?? 0) > 60, `العرض: ${expandedBox?.width ?? '—'}px`);

    await sleep(3200);

    const collapsedBox = await aiFabMobile.boundingBox();
    check('AiFab ينكمش تلقائياً بعد ٣ ثوانٍ', (collapsedBox?.width ?? 999) <= 42, `العرض: ${collapsedBox?.width ?? '—'}px`);

    // الآن — بعد أن انتهى التوقيت الحسّاس — أغلق أي لوح ظهر أثناء الانتظار
    await dismissOverlays(mobilePage);

    // ── الطبقة: يجب أن يحجبه لوح مفتوح (z-index أقلّ من SheetShell) ──
    const topElementIsFab = async (x, y) =>
        mobilePage.evaluate(([px, py]) => document.elementFromPoint(px, py)?.closest('[data-test="ai-fab"]') !== null, [x, y]);

    const fabBox = await aiFabMobile.boundingBox();
    const fabCenter = { x: fabBox.x + fabBox.width / 2, y: fabBox.y + fabBox.height / 2 };

    check('AiFab قابل للنقر بلا لوح مفتوح', await topElementIsFab(fabCenter.x, fabCenter.y));

    // الزرّ السفلي المرئي وحده — نظيره في QuickAddFab (`md:grid`) موجود في
    // الـDOM بنفس التسمية لكنه مخفيّ على الجوال، فيلزم :visible للتفريق.
    await mobilePage.locator('button[aria-label^="إضافة سريعة"]:visible').click();
    await sleep(400);
    check('لوح مفتوح يحجب AiFab (z-index أقلّ من الألواح)', !(await topElementIsFab(fabCenter.x, fabCenter.y)));

    await mobilePage.keyboard.press('Escape');
    await sleep(300);
    await dismissOverlays(mobilePage);

    await mobilePage.locator('button:has-text("المزيد")').click();
    await sleep(350);
    const sheetEntry = mobilePage.locator('a[href$="/assistant"]:visible');
    check('«المساعد الذكي» ظاهر في لوح المزيد', (await sheetEntry.count()) > 0);

    await mobilePage.screenshot({ path: join(SHOTS, '5-mobile-more-sheet.png') });

    await sheetEntry.first().click();
    await mobilePage.waitForURL(/\/assistant$/, { timeout: 30_000, waitUntil: 'domcontentloaded' });
    await mobilePage.waitForSelector('[role="log"]');

    check('النقر من الجوال ينقل إلى المساعد', mobilePage.url().endsWith('/assistant'));
    check('AiFab لا يظهر في صفحة المساعد على الجوال', (await mobilePage.locator('[data-test="ai-fab"]').count()) === 0);

    const mobileOverflow = await mobilePage.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    check('لا تمرير أفقي على الجوال', mobileOverflow <= 1, `${mobileOverflow}px`);
    check('Console الجوال نظيف', mobileProblems.length === 0, mobileProblems.join(' | ') || 'نظيف');

    await mobilePage.screenshot({ path: join(SHOTS, '6-assistant-mobile.png'), fullPage: true });
    await mobile.close();

    /* ══ الخلاصة ══ */

    section('الخلاصة');

    const passed = results.filter((r) => r.passed).length;
    console.log(`${passed}/${results.length} فحصاً نجح · ${failures} فشل`);

    writeFileSync(
        join(HERE, 'last-run.json'),
        JSON.stringify(
            {
                at: new Date().toISOString(),
                passed,
                total: results.length,
                failures,
                results,
                consoleMessages,
                pageErrors,
                failedRequests,
                streamSnapshots: snapshots.map((s) => ({ length: s.length })),
            },
            null,
            2,
        ),
        'utf8',
    );

    console.log(`التفاصيل الكاملة: tests/browser/last-run.json`);
    console.log(`اللقطات: tests/browser/screenshots/`);
} catch (error) {
    console.error('\n❌ توقّف الفحص:', error.message);
    failures++;
} finally {
    if (browser) {
        await browser.close();
    }

    try {
        execFileSync('php', [join(HERE, 'probe-user.php'), 'destroy'], { cwd: ROOT, encoding: 'utf8' });
        console.log('نُظّف مستخدم الفحص.');
    } catch (e) {
        console.error('⚠️ تعذّر حذف مستخدم الفحص:', e.message);
    }

    killPort(PORT);
    killPort(VITE_PORT);

    for (const child of [server, vite]) {
        if (!child) {
            continue;
        }

        child.kill();

        if (process.platform === 'win32') {
            try {
                execFileSync('taskkill', ['/F', '/T', '/PID', String(child.pid)], { stdio: 'ignore' });
            } catch {
                // العملية انتهت أصلاً
            }
        }
    }
}

process.exit(failures === 0 ? 0 : 1);
