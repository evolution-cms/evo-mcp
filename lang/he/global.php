<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'גישה ל-eMCP',
    'permission_manage' => 'ניהול שרתי MCP',
    'permission_dispatch' => 'הרצת משימות MCP אסינכרוניות',
    'menu_tokens' => 'אסימוני MCP',
    'errors' => [
        'forbidden' => 'אסור',
        'scope_denied' => 'ה-scope נדחה',
        'server_not_found' => 'השרת לא נמצא',
        'invalid_payload' => 'payload לא תקין',
    ],
    'menu_settings' => 'הגדרות MCP',
    'settings' => [
        'title' => 'הגדרות MCP',
        'intro' => 'מתגים אלה חלים על כל האתר ונשמרים ב-:path. אפשרויות אחרות (scopes, מגבלות, רשימות מותרות) נערכות ישירות בקובץ זה.',
        'save' => 'שמירת הגדרות',
        'saved' => 'ההגדרות נשמרו.',
        'error_write' => 'לא ניתן לשמור את :path. ודאו ששרת האינטרנט יכול לכתוב לקובץ.',
        'fields' => [
            'enable' => ['label' => 'הפעלת eMCP', 'hint' => 'מתג ראשי לכל נקודות הקצה של MCP.'],
            'mode_internal' => ['label' => 'נקודת קצה במנהל', 'hint' => 'MCP דרך הסשן של המנהל.'],
            'mode_api' => ['label' => 'נקודת קצה API', 'hint' => 'נקודת קצה עם אימות טוקן לסוכנים חיצוניים.'],
            'require_scopes' => ['label' => 'דרישת scopes של הטוקן', 'hint' => 'כל קריאה חייבת להיות מכוסה על ידי ה-scopes של הטוקן שלה.'],
            'enable_write_tools' => ['label' => 'התרת כלי כתיבה', 'hint' => 'סוכנים יכולים ליצור ולשנות מסמכים ואלמנטים (evo.write.*). הטוקן צריך גם את ה-scope mcp:write.'],
            'self_service' => ['label' => 'דף שירות עצמי לטוקנים', 'hint' => 'משתמשים יוצרים את הטוקנים שלהם במנהל.'],
            'rate_limit' => ['label' => 'הגבלת קצב', 'hint' => 'מגביל את מספר הבקשות לדקה לכל משתמש.'],
            'audit' => ['label' => 'יומן ביקורת', 'hint' => 'רישום כל קריאת MCP ביומן הביקורת.'],
            'stream' => ['label' => 'תגובות בזרימה', 'hint' => 'התרת תגובות בזרימה (SSE).'],
        ],
    ],
];
