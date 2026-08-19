<script lang="ts">
    /**
     * أيقونة المساعد الذكي — عملات متراكمة + شرارة.
     *
     * تدمج معنيين: الفلوس (العملات) والذكاء الاصطناعي (الشرارة).
     * الشرارة **مملوءة** والعملات **مفرّغة** — التباين يخلّي الشرارة تُقرأ
     * حتى عند حجم 16px.
     *
     * مرسومة على شبكة 24×24 بنفس سماكة خط أيقونات lucide (1.75) فتنسجم
     * مع باقي أيقونات التطبيق تماماً.
     */
    let { class: className = 'size-5' }: { class?: string } = $props();
</script>

<svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    class={className}
    aria-hidden="true"
>
    <!-- العملات المتراكمة -->
    <ellipse cx="9.5" cy="6.2" rx="6" ry="2.7" />
    <path d="M3.5 6.2v5.2c0 1.5 2.7 2.7 6 2.7s6-1.2 6-2.7V6.2" />
    <path d="M3.5 11.4v5.2c0 1.5 2.7 2.7 6 2.7s6-1.2 6-2.7v-2" />
    <!-- الشرارة — مملوءة -->
    <path
        d="M19.7 2.6l.72 1.78 1.78.72-1.78.72-.72 1.78-.72-1.78-1.78-.72 1.78-.72z"
        fill="currentColor"
        stroke="none"
    />
</svg>
