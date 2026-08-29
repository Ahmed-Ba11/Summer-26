<script module lang="ts">
    export const layout = {
        title: 'أهلًا برجعتك',
        description: 'سجّل دخولك وتشوف وين وصل راتبك هذا الشهر',
    };
</script>

<script lang="ts">
    /**
     * تسجيل الدخول — مُعرَّب ومنقول إلى نمط بقيّة التطبيق.
     *
     * كانت الصفحة الوحيدة الباقية على قالب Laravel الافتراضي: «Email
     * address» و«Remember me» و«Forgot your password» في تطبيق عربي كلّه.
     * وهي أول صفحة يراها العائد، فتناقضها مع بقيّة الشاشات يقرأ كأنه خلل.
     *
     * ما تغيّر عن القالب:
     *   • كل نصّ بالعربية، والحقول بترتيب منطقي (`ms`/`me` لا `left`/`right`).
     *   • كل هدف لمس `min-h-11` على الأقل — الأزرار والروابط ومربّع التذكّر.
     *   • حواف `rounded-2xl` كما في الألواح والبطاقات، لا `rounded-md`.
     *   • رسالة `auth.failed` صارت لها ترجمة في `lang/ar/auth.php` بعد أن
     *     كانت تظهر خاماً بمفتاحها.
     */
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasskeyVerify from '@/components/PasskeyVerify.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { register } from '@/routes';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        status = '',
        canResetPassword,
    }: {
        status?: string;
        canResetPassword: boolean;
    } = $props();
</script>

<AppHead title="تسجيل الدخول" />

{#if status}
    <div
        class="rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-center text-[13px] font-medium text-success-text"
    >
        {status}
    </div>
{/if}

<PasskeyVerify
    label="ادخل بمفتاح المرور"
    loadingLabel="جارٍ التحقّق…"
    separator="أو بالبريد وكلمة المرور"
/>

<Form
    {...store.form()}
    resetOnSuccess={['password']}
    class="flex flex-col gap-5"
>
    {#snippet children({ errors, processing })}
        <div class="flex flex-col gap-1.5">
            <Label for="email" class="text-[12.5px] text-foreground/85">
                البريد الإلكتروني
            </Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                dir="ltr"
                placeholder="you@example.com"
                class="min-h-12 rounded-2xl text-start"
            />
            <InputError message={errors.email} />
        </div>

        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <Label for="password" class="text-[12.5px] text-foreground/85">
                    كلمة المرور
                </Label>
                {#if canResetPassword}
                    <TextLink
                        href={request()}
                        class="inline-flex min-h-11 items-center text-[12.5px] text-primary no-underline"
                    >
                        نسيت كلمة المرور؟
                    </TextLink>
                {/if}
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="كلمة المرور"
                class="min-h-12 rounded-2xl"
            />
            <InputError message={errors.password} />
        </div>

        <Label
            for="remember"
            class="inline-flex min-h-11 w-fit items-center gap-2.5 text-[13px] text-foreground/85"
        >
            <Checkbox id="remember" name="remember" />
            <span>خلّني مسجّل دخول</span>
        </Label>

        <button
            type="submit"
            disabled={processing}
            data-test="login-button"
            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground shadow-sm transition-transform active:scale-[.98] disabled:opacity-60"
        >
            {#if processing}<Spinner />{/if}
            تسجيل الدخول
        </button>

        <p class="text-center text-[12.5px] text-muted-foreground">
            ما عندك حساب؟
            <TextLink
                href={register()}
                class="font-semibold text-primary no-underline"
            >
                أنشئ حساباً
            </TextLink>
        </p>
    {/snippet}
</Form>
