/**
 * خريطة أيقونات الفئات — بديل الإيموجي.
 *
 * لماذا لا إيموجي؟
 *  - يختلف شكله بين ويندوز/أندرويد/آيفون، فيكسر اتساق الواجهة.
 *  - لا يتلوّن مع الثيم ولا يعمل في الوضع الداكن.
 *  - يقرأه قارئ الشاشة باسمه الإنجليزي الكامل ("hamburger") فيربك المستخدم.
 *
 * البديل: أيقونة lucide داخل دائرة بلون الفئة بشفافية ١٢٪.
 *
 * عمود `categories.icon` في قاعدة البيانات يخزّن **اسم المفتاح** من هذه الخريطة
 * (مثل "utensils")، وليس رمز إيموجي.
 */

import Banknote from 'lucide-svelte/icons/banknote';
import BookOpen from 'lucide-svelte/icons/book-open';
import Briefcase from 'lucide-svelte/icons/briefcase';
import Bus from 'lucide-svelte/icons/bus';
import Car from 'lucide-svelte/icons/car';
import Cat from 'lucide-svelte/icons/cat';
import Coffee from 'lucide-svelte/icons/coffee';
import CreditCard from 'lucide-svelte/icons/credit-card';
import Dumbbell from 'lucide-svelte/icons/dumbbell';
import Ellipsis from 'lucide-svelte/icons/ellipsis';
import Fuel from 'lucide-svelte/icons/fuel';
import Gamepad2 from 'lucide-svelte/icons/gamepad-2';
import Gift from 'lucide-svelte/icons/gift';
import GraduationCap from 'lucide-svelte/icons/graduation-cap';
import HeartPulse from 'lucide-svelte/icons/heart-pulse';
import House from 'lucide-svelte/icons/house';
import Laptop from 'lucide-svelte/icons/laptop';
import Phone from 'lucide-svelte/icons/phone';
import Pill from 'lucide-svelte/icons/pill';
import Plane from 'lucide-svelte/icons/plane';
import Repeat from 'lucide-svelte/icons/repeat';
import Receipt from 'lucide-svelte/icons/receipt';
import Scissors from 'lucide-svelte/icons/scissors';
import Shirt from 'lucide-svelte/icons/shirt';
import ShoppingCart from 'lucide-svelte/icons/shopping-cart';
import Utensils from 'lucide-svelte/icons/utensils';
import Wifi from 'lucide-svelte/icons/wifi';
import Zap from 'lucide-svelte/icons/zap';
import FileText from 'lucide-svelte/icons/file-text';

export type IconKey = keyof typeof ICONS;

export const ICONS = {
    utensils: Utensils,
    coffee: Coffee,
    'shopping-cart': ShoppingCart,
    car: Car,
    bus: Bus,
    fuel: Fuel,
    house: House,
    zap: Zap,
    wifi: Wifi,
    phone: Phone,
    'heart-pulse': HeartPulse,
    pill: Pill,
    dumbbell: Dumbbell,
    'graduation-cap': GraduationCap,
    'book-open': BookOpen,
    'gamepad-2': Gamepad2,
    plane: Plane,
    gift: Gift,
    shirt: Shirt,
    scissors: Scissors,
    cat: Cat,
    laptop: Laptop,
    briefcase: Briefcase,
    'credit-card': CreditCard,
    banknote: Banknote,
    repeat: Repeat,
    receipt: Receipt,
    'file-text': FileText,
    ellipsis: Ellipsis,
} as const;

/** الأيقونات المعروضة في منتقي الأيقونات عند إنشاء فئة مخصّصة. */
export const ICON_PICKER: IconKey[] = Object.keys(ICONS) as IconKey[];

/** أسماء عربية للأيقونات — تُستخدم كـ aria-label وكتلميح في المنتقي. */
export const ICON_LABELS: Record<IconKey, string> = {
    utensils: 'طعام',
    coffee: 'قهوة',
    'shopping-cart': 'تسوّق',
    car: 'سيارة',
    bus: 'مواصلات',
    fuel: 'وقود',
    house: 'سكن',
    zap: 'كهرباء',
    wifi: 'إنترنت',
    phone: 'جوال',
    'heart-pulse': 'صحة',
    pill: 'دواء',
    dumbbell: 'رياضة',
    'graduation-cap': 'تعليم',
    'book-open': 'كتب',
    'gamepad-2': 'ترفيه',
    plane: 'سفر',
    gift: 'هدايا',
    shirt: 'ملابس',
    scissors: 'عناية',
    cat: 'حيوان أليف',
    laptop: 'إلكترونيات',
    briefcase: 'عمل',
    'credit-card': 'أقساط',
    banknote: 'نقد',
    repeat: 'اشتراكات',
    receipt: 'فاتورة',
    'file-text': 'مستند',
    ellipsis: 'أخرى',
};

/** الفئات الافتراضية عند إنشاء حساب جديد — الاسم واللون والأيقونة. */
export const DEFAULT_CATEGORIES = [
    { name: 'طعام', icon: 'utensils', color: 'var(--chart-1)' },
    { name: 'مواصلات', icon: 'car', color: 'var(--chart-2)' },
    { name: 'ترفيه', icon: 'gamepad-2', color: 'var(--chart-3)' },
    { name: 'صحة', icon: 'heart-pulse', color: 'var(--chart-4)' },
    { name: 'تعليم', icon: 'graduation-cap', color: 'var(--chart-5)' },
    { name: 'تسوّق', icon: 'shopping-cart', color: 'var(--chart-6)' },
    { name: 'أخرى', icon: 'ellipsis', color: 'var(--chart-7)' },
] as const;

/**
 * ألوان البالتة الفئوية بالترتيب الثابت.
 * ⚠️ الترتيب ليس تجميلياً — هو آلية الأمان ضد عمى الألوان.
 * لا تدوّره، ولا تولّد لوناً ثامناً؛ الفئة الثامنة تنطوي تحت «أخرى».
 */
export const CATEGORY_PALETTE = [
    '#2a78d6',
    '#eb6834',
    '#1baf7a',
    '#eda100',
    '#e87ba4',
    '#008300',
    '#4a3aa7',
] as const;

export function iconFor(key: string | null | undefined) {
    return ICONS[(key ?? 'ellipsis') as IconKey] ?? Ellipsis;
}
