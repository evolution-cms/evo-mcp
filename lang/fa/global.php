<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'دسترسی به eMCP',
    'permission_manage' => 'مدیریت سرورهای MCP',
    'permission_dispatch' => 'اجرای ناهمزمان وظایف MCP',
    'menu_tokens' => 'توکن‌های MCP',
    'errors' => [
        'forbidden' => 'ممنوع',
        'scope_denied' => 'محدوده مجاز کافی نیست',
        'server_not_found' => 'سرور یافت نشد',
        'invalid_payload' => 'بار داده نامعتبر',
    ],
    'menu_settings' => 'تنظیمات MCP',
    'settings' => [
        'title' => 'تنظیمات MCP',
        'intro' => 'این کلیدها برای کل سایت اعمال می‌شوند و در :path ذخیره می‌شوند. گزینه‌های دیگر (scopeها، محدودیت‌ها، فهرست‌های مجاز) مستقیماً در همان فایل ویرایش می‌شوند.',
        'save' => 'ذخیره تنظیمات',
        'saved' => 'تنظیمات ذخیره شد.',
        'error_write' => 'ذخیره :path ممکن نشد. بررسی کنید که وب‌سرور اجازه نوشتن در آن را داشته باشد.',
        'fields' => [
            'enable' => ['label' => 'فعال‌سازی eMCP', 'hint' => 'کلید اصلی همه endpointهای MCP.'],
            'mode_internal' => ['label' => 'endpoint مدیریت', 'hint' => 'MCP از طریق نشست مدیریت.'],
            'mode_api' => ['label' => 'endpoint API', 'hint' => 'endpoint با احراز هویت توکن برای عامل‌های خارجی.'],
            'require_scopes' => ['label' => 'الزام scopeهای توکن', 'hint' => 'هر فراخوانی باید در scopeهای توکن خود پوشش داده شود.'],
            'enable_write_tools' => ['label' => 'اجازه ابزارهای نوشتن', 'hint' => 'عامل‌ها می‌توانند اسناد و عناصر را ایجاد و ویرایش کنند (evo.write.*). توکن به scope mcp:write نیز نیاز دارد.'],
            'self_service' => ['label' => 'صفحه سلف‌سرویس توکن', 'hint' => 'کاربران توکن‌های خود را در بخش مدیریت می‌سازند.'],
            'rate_limit' => ['label' => 'محدودیت نرخ درخواست', 'hint' => 'تعداد درخواست‌ها در دقیقه را برای هر کاربر محدود می‌کند.'],
            'audit' => ['label' => 'گزارش حسابرسی', 'hint' => 'هر فراخوانی MCP در گزارش حسابرسی ثبت شود.'],
            'stream' => ['label' => 'پاسخ‌های جریانی', 'hint' => 'اجازه پاسخ‌های جریانی (SSE).'],
        ],
    ],
];
