<script lang="ts">
    /**
     * الإعدادات — صفحة واحدة بخمس مجموعات، بلا زر «حفظ».
     *
     * كل مفتاح يُرسل تغييره فور لمسه ويرجع بـ`toast`. زر الحفظ في شاشة
     * إعدادات جوال يعني أن نصف التغييرات تضيع: المستخدم يبدّل مفتاحاً ثم
     * يرجع بالإيماءة ولا يمرّ على الزر أصلاً.
     *
     * كل مبلغ من `AmountSheet`، وكل يوم-من-الشهر من `DayOfMonthPicker`،
     * وكل تأكيد لا رجعة فيه من `SheetShell`.
     */
    import { Link, router } from '@inertiajs/svelte';
    import Bell from 'lucide-svelte/icons/bell';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ChartNoAxesColumn from 'lucide-svelte/icons/chart-no-axes-column';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Download from 'lucide-svelte/icons/download';
    import Gauge from 'lucide-svelte/icons/gauge';
    import Globe from 'lucide-svelte/icons/globe';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Monitor from 'lucide-svelte/icons/monitor';
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Type from 'lucide-svelte/icons/type';
    import Vault from 'lucide-svelte/icons/vault';
    import Wallet from 'lucide-svelte/icons/wallet';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DayOfMonthPicker from '@/components/ui/DayOfMonthPicker.svelte';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import ToggleRow from '@/components/ui/ToggleRow.svelte';
    import { formatAmount } from '@/lib/format';
    import {
        applyFontScale,
        applyLocale,
        updateAppearance,
    } from '@/lib/theme.svelte';
    import type { Appearance } from '@/lib/theme.svelte';

    interface SettingsProps {
        settings: {
            name: string;
            email: string;
            monthly_income: number;
            salary_day: number;
            monthly_savings_target: number;
            locale: string;
            theme: string;
            font_scale: string;
            notify_due: boolean;
            notify_budget: boolean;
            notify_salary: boolean;
        };
        salaryMonth: { key: string; label: string; range: string };
    }

    let { settings, salaryMonth }: SettingsProps = $props();

    let income = $state(settings.monthly_income);
    let salaryDay = $state(settings.salary_day);
    let savingsTarget = $state(settings.monthly_savings_target);
    let theme = $state(settings.theme as Appearance);
    let locale = $state(settings.locale);
    let fontScale = $state(settings.font_scale);
    let notifyDue = $state(settings.notify_due);
    let notifyBudget = $state(settings.notify_budget);

    let salaryDaySheet = $state(false);
    let deleteSheet = $state(false);
    let deleteConfirm = $state('');
    let deleting = $state(false);

    /** كل تغيير يُرسل وحده — لا نجمع الحقول ولا ننتظر زراً. */
    function save(payload: Parameters<typeof router.patch>[1]) {
        router.patch('/settings/preferences', payload, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    // ── لوح المبلغ المشترك ────────────────────────────────────────────
    let sheet = $state({
        open: false,
        value: 0,
        title: '',
        subtitle: '',
        apply: (_halalas: number) => {},
    });

    function askAmount(options: {
        value: number;
        title: string;
        subtitle?: string;
        apply: (halalas: number) => void;
    }) {
        sheet = {
            open: true,
            value: options.value,
            title: options.title,
            subtitle: options.subtitle ?? '',
            apply: options.apply,
        };
    }

    const THEMES: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: 'فاتح', icon: Sun },
        { value: 'dark', label: 'داكن', icon: Moon },
        { value: 'system', label: 'تلقائي', icon: Monitor },
    ];

    const LOCALES = [
        { value: 'ar', label: 'عربي' },
        { value: 'en', label: 'English' },
    ];

    // الكلمة الكاملة لا الحرف: «ص» تحتمل صغير وصامت وصفحة، والكلمة لا تحتمل
    // إلا معناها. ثلاثة أزرار تتّسع للكلمة، فلا عذر للاختصار.
    const FONT_SCALES = [
        { value: 'sm', label: 'صغير' },
        { value: 'md', label: 'متوسط' },
        { value: 'lg', label: 'كبير' },
    ];

    function pickTheme(value: Appearance) {
        theme = value;
        updateAppearance(value);
        save({ theme: value });
    }

    function pickLocale(value: string) {
        locale = value;
        applyLocale(value as 'ar' | 'en');
        save({ locale: value });
    }

    function pickFontScale(value: string) {
        fontScale = value;
        applyFontScale(value as 'sm' | 'md' | 'lg');
        save({ font_scale: value });
    }

    function confirmDelete() {
        deleting = true;
        router.delete('/settings/data', {
            data: { confirm: deleteConfirm },
            onFinish: () => (deleting = false),
            onSuccess: () => (deleteSheet = false),
        });
    }
</script>

<AppHead title="الإعدادات" />

<MobileHeader title="الإعدادات" subtitle={salaryMonth.label} />

<AmountSheet
    bind:open={sheet.open}
    bind:value={sheet.value}
    title={sheet.title}
    subtitle={sheet.subtitle}
    onSave={(halalas) => sheet.apply(halalas)}
/>

<!-- يوم نزول الراتب -->
<SheetShell
    bind:open={salaryDaySheet}
    title="يوم نزول الراتب"
    subtitle="شهرك يبدأ من هذا اليوم"
>
    <DayOfMonthPicker
        bind:value={salaryDay}
        showLastDay={false}
        hint="كل أرقام التطبيق تُحسب على شهر يبدأ يوم {salaryDay} لا يوم 1."
    />

    {#snippet footer()}
        <button
            type="button"
            onclick={() => (salaryDaySheet = false)}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إلغاء
        </button>
        <button
            type="button"
            onclick={() => {
                save({ salary_day: salaryDay });
                salaryDaySheet = false;
            }}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99]"
        >
            حفظ يوم {salaryDay}
        </button>
    {/snippet}
</SheetShell>

<!-- حذف كل البيانات -->
<SheetShell
    bind:open={deleteSheet}
    title="حذف كل بياناتك"
    subtitle="لا يمكن التراجع عن هذا"
>
    <div class="flex flex-col gap-3 py-1">
        <div
            class="flex items-start gap-2.5 rounded-2xl border border-destructive/25 bg-destructive/8 p-3"
        >
            <TriangleAlert
                class="mt-px size-[18px] shrink-0 text-destructive"
                stroke-width="1.9"
            />
            <p class="text-[11.5px] leading-relaxed text-foreground/85">
                ستُحذف مصاريفك ودخلك والتزاماتك وميزانياتك وأهدافك الادخارية
                نهائياً. حسابك يبقى، ويبدأ الإعداد من أوّله.
            </p>
        </div>

        <label class="flex flex-col gap-1.5">
            <span class="text-[11.5px] text-muted-foreground">
                اكتب <b class="font-semibold text-foreground">{settings.name}</b
                > للتأكيد
            </span>
            <input
                type="text"
                bind:value={deleteConfirm}
                autocomplete="off"
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] outline-none focus:border-destructive"
            />
        </label>
    </div>

    {#snippet footer()}
        <button
            type="button"
            disabled={deleting}
            onclick={() => (deleteSheet = false)}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            إلغاء
        </button>
        <button
            type="button"
            disabled={deleting || deleteConfirm.trim() !== settings.name.trim()}
            onclick={confirmDelete}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl bg-destructive text-[14.5px] font-semibold text-white transition-transform active:scale-[.99] disabled:opacity-45"
        >
            {deleting ? 'جارٍ الحذف…' : 'احذف كل شيء'}
        </button>
    {/snippet}
</SheetShell>

<div class="mx-auto w-full max-w-2xl p-3 md:p-6">
    <div class="flex flex-col gap-3 md:gap-5">
        <!-- ═══ الحساب ═══ -->
        <section
            class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
        >
            <div class="flex items-center gap-3">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-[15px] font-semibold text-primary-foreground"
                >
                    {settings.name?.[0] ?? 'م'}
                </span>
                <div class="min-w-0 flex-1">
                    <b class="block truncate text-[14px] font-semibold"
                        >{settings.name}</b
                    >
                    <span
                        class="block truncate text-[11.5px] text-muted-foreground tabular-nums"
                    >
                        راتب {formatAmount(income)} ر.س · يوم {salaryDay}
                    </span>
                </div>
            </div>

            <div class="mt-2.5 flex flex-col border-t border-border pt-1">
                <Link
                    href="/settings/profile"
                    class="flex min-h-11 items-center gap-2.5 text-[13.5px] no-underline"
                >
                    <span class="min-w-0 flex-1 truncate">اسمك وبريدك</span>
                    <span
                        class="shrink-0 truncate text-[11.5px] text-muted-foreground"
                        >{settings.email}</span
                    >
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
                <Link
                    href="/settings/security"
                    class="flex min-h-11 items-center gap-2.5 border-t border-border text-[13.5px] no-underline"
                >
                    <KeyRound
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1"
                        >كلمة المرور والتحقّق بخطوتين</span
                    >
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
            </div>
        </section>

        <!-- ═══ صورتك المالية ═══ -->
        <section
            class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
        >
            <h2 class="text-[14px] font-semibold">صورتك المالية</h2>
            <p class="mt-0.5 text-[11.5px] text-muted-foreground">
                هذه الأرقام تبني كل حساب في التطبيق.
            </p>

            <div class="mt-2.5 flex flex-col">
                <button
                    type="button"
                    onclick={() =>
                        askAmount({
                            value: income,
                            title: 'الراتب الشهري',
                            subtitle: 'المبلغ الذي ينزل حسابك كل شهر',
                            apply: (halalas) => {
                                income = halalas;
                                save({ monthly_income: halalas });
                            },
                        })}
                    class="flex min-h-11 items-center gap-2.5 border-b border-border text-start transition-transform active:scale-[.99]"
                >
                    <Wallet
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1 text-[13.5px]"
                        >الراتب الشهري</span
                    >
                    <span
                        class="shrink-0 text-[14px] font-semibold tabular-nums"
                    >
                        {formatAmount(income)}
                        <span
                            class="text-[11.5px] font-normal text-muted-foreground"
                            >ر.س</span
                        >
                    </span>
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </button>

                <button
                    type="button"
                    onclick={() => (salaryDaySheet = true)}
                    class="flex min-h-11 items-center gap-2.5 border-b border-border text-start transition-transform active:scale-[.99]"
                >
                    <CalendarDays
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1 text-[13.5px]"
                        >يوم نزول الراتب</span
                    >
                    <span
                        class="shrink-0 text-[14px] font-semibold tabular-nums"
                        >{salaryDay}</span
                    >
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </button>

                <button
                    type="button"
                    onclick={() =>
                        askAmount({
                            value: savingsTarget,
                            title: 'هدف الادخار الشهري',
                            subtitle: 'يُحجز أول الشهر لا آخره',
                            apply: (halalas) => {
                                savingsTarget = halalas;
                                save({ monthly_savings_target: halalas });
                            },
                        })}
                    class="flex min-h-11 items-center gap-2.5 text-start transition-transform active:scale-[.99]"
                >
                    <Vault
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1 text-[13.5px]"
                        >هدف الادخار الشهري</span
                    >
                    <span
                        class="shrink-0 text-[14px] font-semibold tabular-nums"
                    >
                        {formatAmount(savingsTarget)}
                        <span
                            class="text-[11.5px] font-normal text-muted-foreground"
                            >ر.س</span
                        >
                    </span>
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </button>
            </div>
        </section>

        <!-- ═══ المظهر واللغة ═══ -->
        <section
            class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
        >
            <h2 class="text-[14px] font-semibold">المظهر واللغة</h2>

            <div class="mt-2.5 flex flex-col gap-3">
                <div>
                    <span class="text-[11.5px] text-muted-foreground"
                        >المظهر</span
                    >
                    <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                        {#each THEMES as option (option.value)}
                            {@const Icon = option.icon}
                            <button
                                type="button"
                                aria-pressed={theme === option.value}
                                onclick={() => pickTheme(option.value)}
                                class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-2 text-[12.5px] transition-colors {theme ===
                                option.value
                                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                                    : 'border-input text-foreground/85'}"
                            >
                                <Icon class="size-[17px]" stroke-width="1.9" />
                                {option.label}
                            </button>
                        {/each}
                    </div>
                    {#if theme === 'system'}
                        <p class="mt-1.5 text-[11.5px] text-muted-foreground">
                            «تلقائي» يتبع مظهر جهازك — يصير داكناً معه ويرجع
                            فاتحاً معه.
                        </p>
                    {/if}
                </div>

                <div>
                    <span
                        class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground"
                    >
                        <Globe class="size-3.5" stroke-width="1.9" /> اللغة
                    </span>
                    <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                        {#each LOCALES as option (option.value)}
                            <button
                                type="button"
                                aria-pressed={locale === option.value}
                                onclick={() => pickLocale(option.value)}
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border px-2 text-[12.5px] transition-colors {locale ===
                                option.value
                                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                                    : 'border-input text-foreground/85'}"
                            >
                                {option.label}
                            </button>
                        {/each}
                    </div>
                    {#if locale === 'en'}
                        <p class="mt-1.5 text-[11.5px] text-muted-foreground">
                            الاتجاه والتواريخ والأرقام تتبع الإنجليزية — نصوص
                            الواجهة عربية حتى تكتمل الترجمة.
                        </p>
                    {/if}
                </div>

                <div>
                    <span
                        class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground"
                    >
                        <Type class="size-3.5" stroke-width="1.9" /> حجم الخط
                    </span>
                    <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                        {#each FONT_SCALES as option (option.value)}
                            <button
                                type="button"
                                aria-pressed={fontScale === option.value}
                                onclick={() => pickFontScale(option.value)}
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border px-2 transition-colors {option.value ===
                                'sm'
                                    ? 'text-[12.5px]'
                                    : option.value === 'md'
                                      ? 'text-[14px]'
                                      : 'text-[16px]'} {fontScale ===
                                option.value
                                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                                    : 'border-input text-foreground/85'}"
                            >
                                {option.label}
                            </button>
                        {/each}
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ التنبيهات والأمان ═══ -->
        <section
            class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
        >
            <h2 class="text-[14px] font-semibold">التنبيهات والأمان</h2>

            <div class="mt-1.5 flex flex-col">
                <ToggleRow
                    bind:checked={notifyDue}
                    icon={Bell}
                    label="تنبيهات الاستحقاق"
                    detail="تذكير قبل موعد كل التزام بثلاثة أيام."
                    onchange={(value) => save({ notify_due: value })}
                />
                <div class="border-t border-border"></div>
                <ToggleRow
                    bind:checked={notifyBudget}
                    icon={Gauge}
                    label="تنبيه تجاوز الميزانية عند 80٪"
                    detail="ينبّهك قبل التجاوز لا بعده — وقتها ما زال بالإمكان التعديل."
                    onchange={(value) => save({ notify_budget: value })}
                />
            </div>
        </section>

        <!-- ═══ بياناتك ═══ -->
        <section
            class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
        >
            <h2 class="text-[14px] font-semibold">بياناتك</h2>

            <div class="mt-2.5 flex flex-col">
                <!--
                    التقارير ليست إعداداً — مكانها صفحتها.
                    كان هنا صفّا تصدير (CSV وPDF)، فصار المستخدم يمرّ
                    بالإعدادات ليصل إلى تقاريره. الصفّ الآن يقود إلى الصفحة
                    نفسها حيث المدد والتصدير معاً.
                -->
                <a
                    href="/reports"
                    class="flex min-h-11 items-center gap-2.5 border-b border-border text-[13.5px] no-underline"
                >
                    <ChartNoAxesColumn
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1">تقاريرك — عرض وتصدير PDF</span>
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </a>

                <a
                    href="/settings/backup"
                    class="flex min-h-11 items-center gap-2.5 border-b border-border text-[13.5px] no-underline"
                >
                    <Download
                        class="size-[18px] shrink-0 text-muted-foreground"
                        stroke-width="1.9"
                    />
                    <span class="min-w-0 flex-1"
                        >نسخة احتياطية من كل بياناتك</span
                    >
                    <ChevronLeft
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </a>

                <button
                    type="button"
                    onclick={() => {
                        deleteConfirm = '';
                        deleteSheet = true;
                    }}
                    class="flex min-h-11 items-center gap-2.5 text-start text-[13.5px] text-destructive transition-transform active:scale-[.99]"
                >
                    <Trash2 class="size-[18px] shrink-0" stroke-width="1.9" />
                    <span class="min-w-0 flex-1">حذف كل البيانات</span>
                    <ChevronLeft class="size-4 shrink-0" />
                </button>
            </div>
        </section>
    </div>
</div>
