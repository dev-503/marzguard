# Headless React client (اختیاری)

این پوشه نسخه **React/TypeScript** کامپوننتهایی است که در رابط ورود استفاده میشوند (`PhoneInput`، `Input`، `Field`). مرزگارد به صورت پیشفرض از Vue 3 بدون مرحله Build استفاده میکند، ولی اگر فرانتاند شما Next.js / React است، میتوانید مستقیماً از این فایلها استفاده کنید و با REST API مرزگارد (`/wp-json/marzgard/v1`) ارتباط برقرار کنید.

## فایلها
- `lib/persian.ts` — توابع عادیسازی، اعتبارسنجی، قالببندی ۳-۳-۴ و تشخیص اپراتور
- `lib/utils.ts` — `@/lib/utils` (تابع `cn` بدون وابستگی خارجی)
- `components/Input.tsx` — ورودی گروهبندیشده با `startAddon` / `endAddon` / `error`، `Label` و `Field`
- `components/PhoneInput.tsx` — اینپوت موبایل ایران با پیشوند ثابت `+98` و علامت تأیید

## نمونه استفاده

```tsx
'use client';
import * as React from 'react';
import { PhoneInput } from '@/components/PhoneInput';

export function LoginForm() {
  const [phone, setPhone] = React.useState('');
  return (
    <form onSubmit={(e) => { e.preventDefault(); /* POST /otp/send */ }}>
      <PhoneInput value={phone} onChange={(digits, valid) => setPhone(digits)} />
      <button type="submit" disabled={!/^9\d{9}$/.test(phone)}>ورود</button>
    </form>
  );
}
```

> نیاز به `lucide-react` برای آیکون Check و تنظیم `@/*` path alias در `tsconfig.json` دارد.
