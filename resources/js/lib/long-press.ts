/**
 * ضغطة مطوّلة — اختصار للإجراء الأكثر تكراراً خلف نفس الزر.
 *
 * زر «+» يفتح قائمة بأربعة إجراءات، لكن ثلاثة من كل أربع ضغطات هي «مصروف».
 * الضغطة المطوّلة تقفز إليه مباشرة بلا أن تسرق الضغطة القصيرة من القائمة.
 *
 *   <button use:longPress={{ onHold: () => openExpense() }}>+</button>
 *
 * 500ms هو الحدّ المتعارف عليه في iOS وAndroid — أقصر منه يُطلق بالخطأ
 * أثناء التمرير، وأطول منه يبدو معلّقاً.
 */
export function longPress(
    node: HTMLElement,
    options: { onHold: () => void; duration?: number },
) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let fired = false;
    let current = options;

    function start() {
        fired = false;
        timer = setTimeout(() => {
            fired = true;
            timer = null;
            current.onHold();
        }, current.duration ?? 500);
    }

    function cancel() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    /**
     * بعد إطلاق الضغطة المطوّلة تصل `click` أيضاً — لولا هذا الحاجز لفُتح
     * اللوحان معاً: المصروف من الضغطة المطوّلة والقائمة من النقرة.
     */
    function onClick(event: MouseEvent) {
        if (!fired) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        fired = false;
    }

    node.addEventListener('pointerdown', start);
    node.addEventListener('pointerup', cancel);
    node.addEventListener('pointercancel', cancel);
    node.addEventListener('pointerleave', cancel);
    node.addEventListener('click', onClick, true);

    return {
        update(next: { onHold: () => void; duration?: number }) {
            current = next;
        },
        destroy() {
            cancel();
            node.removeEventListener('pointerdown', start);
            node.removeEventListener('pointerup', cancel);
            node.removeEventListener('pointercancel', cancel);
            node.removeEventListener('pointerleave', cancel);
            node.removeEventListener('click', onClick, true);
        },
    };
}
