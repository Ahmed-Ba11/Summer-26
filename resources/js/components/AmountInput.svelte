<script lang="ts">
    /**
     * حقل إدخال المبلغ الموحّد.
     *
     * يعرض بالريالات ويرسل بالهللات — هذا يمنع خطأ الضرب المزدوج في ١٠٠
     * الذي يتكرّر عند تعدّد نماذج الإدخال.
     *
     * الاستخدام:
     *   let amount = $state(0);        // بالهللات
     *   <AmountInput bind:value={amount} label="المبلغ" />
     */
    let {
        value = $bindable(0), // بالهللات
        label = 'المبلغ',
        placeholder = '0.00',
        error = '',
        autofocus = false,
        id = 'amount',
    }: {
        value?: number;
        label?: string;
        placeholder?: string;
        error?: string;
        autofocus?: boolean;
        id?: string;
    } = $props();

    // نص العرض بالريالات — منفصل عن القيمة الحقيقية بالهللات
    let text = $state(value ? (value / 100).toString() : '');

    function onInput(e: Event) {
        const raw = (e.target as HTMLInputElement).value;
        // يُقبل رقم موجب بمنزلتين عشريتين كحد أقصى
        const clean = raw.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1');
        const parts = clean.split('.');
        text = parts.length > 1 ? `${parts[0]}.${parts[1].slice(0, 2)}` : clean;

        const n = parseFloat(text);
        value = Number.isFinite(n) ? Math.round(n * 100) : 0;
    }
</script>

<div>
    <label for={id} class="mb-1.5 block text-[12.5px] font-medium">{label}</label>

    <div
        class="flex items-center overflow-hidden rounded-lg border bg-card transition-[box-shadow,border-color] focus-within:ring-3 focus-within:ring-ring/20 {error
            ? 'border-destructive'
            : 'border-input focus-within:border-ring'}"
    >
        <input
            {id}
            type="text"
            inputmode="decimal"
            dir="ltr"
            value={text}
            oninput={onInput}
            {placeholder}
            {autofocus}
            aria-invalid={!!error}
            aria-describedby={error ? `${id}-error` : undefined}
            class="min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-xl font-semibold text-foreground tabular-nums outline-none placeholder:font-normal placeholder:text-muted-foreground"
            style="text-align: start"
        />
        <span
            class="grid self-stretch place-items-center border-s border-border px-3.5 text-[13px] text-muted-foreground"
        >
            ر.س
        </span>
    </div>

    {#if error}
        <p id="{id}-error" class="mt-1 text-[11.5px] text-destructive">{error}</p>
    {/if}
</div>
