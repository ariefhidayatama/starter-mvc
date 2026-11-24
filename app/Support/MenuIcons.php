<?php

namespace App\Support;

/**
 * Daftar ikon (nama komponen lucide-react) yang boleh dipilih untuk menu.
 *
 * Kenapa di-whitelist? Nilai ikon disimpan di database lalu dipetakan
 * ke komponen React. Daftar tetap = validasi mudah, dan bundle JavaScript
 * tidak perlu memuat ±1.500 ikon lucide.
 *
 * WAJIB sama dengan kunci di resources/js/lib/menu-icons.ts
 * (dijaga oleh test MenuManagementTest).
 */
final class MenuIcons
{
    public const NAMES = [
        'LayoutGrid', 'House', 'Package', 'Boxes', 'FolderOpen', 'Tag',
        'Store', 'ShoppingCart', 'Receipt', 'CreditCard', 'Truck', 'ChartColumn',
        'Users', 'ShieldCheck', 'KeyRound', 'ListTree', 'Settings', 'Wrench',
        'Bell', 'Mail', 'Phone', 'MessageSquare', 'Info', 'CircleHelp', 'LifeBuoy',
        'FileText', 'BookOpen', 'Newspaper', 'Image', 'Calendar', 'Globe', 'Link', 'Plug',
        'MapPin', 'Building2', 'Briefcase', 'Star', 'Heart',
    ];
}
