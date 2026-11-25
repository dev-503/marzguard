/** Persian mobile number helpers — shared by the React headless client. */

const FA_MAP: Record<string, string> = {
  '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
  '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
};

const AR_MAP: Record<string, string> = {
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
  '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
};

export function toLatinDigits(value: string): string {
  return value
    .replace(/[۰-۹]/g, (c) => FA_MAP[c] ?? c)
    .replace(/[٠-٩]/g, (c) => AR_MAP[c] ?? c);
}

/** Strip everything except digits, remove a leading zero, cap at 10 digits. */
export function normalizeIranMobile(value: string): string {
  let s = toLatinDigits(value).replace(/\D/g, '');
  if (s.length === 11 && s.startsWith('0')) s = s.slice(1);
  if (s.length > 10) s = s.slice(0, 10);
  return s;
}

/** 10 digits starting with 9. */
export function isIranMobile(digits: string): boolean {
  return /^9\d{9}$/.test(digits);
}

/** «9123456789» → «912 345 6789» */
export function formatIranMobile(digits: string): string {
  if (!digits || digits.length <= 3) return digits;
  if (digits.length <= 6) return `${digits.slice(0, 3)} ${digits.slice(3)}`;
  return `${digits.slice(0, 3)} ${digits.slice(3, 6)} ${digits.slice(6, 10)}`;
}

/** Detect the Iranian operator from the 3-digit prefix. */
export function mobileOperator(digits: string): string {
  const prefix = digits.slice(0, 3);
  if (!/^9\d{2}$/.test(prefix)) return '';
  if (/^9(1\d|3[0-3])$/.test(prefix)) return 'همراه اول';
  if (/^92[0-2]$/.test(prefix)) return 'رایتل';
  if (/^9(3[5-9]|9[0-6])$/.test(prefix)) return 'ایرانسل';
  return 'سایر اپراتورها';
}
