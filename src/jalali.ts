export class Jalali {
  private static readonly WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
  private static readonly MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

  private static toJalaliParts(gy: number, gm: number, gd: number): [number, number, number] {
    const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd;
    for (let i = 0; i < gm - 1; i++) {
      days += gDaysInMonth[i];
    }
    if (gm > 2 && (gy % 4 === 0 && (gy % 100 !== 0 || gy % 400 === 0))) {
      days++;
    }

    let jy = -1595 + (33 * Math.floor(days / 12053));
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }

    let jm = 0;
    for (let i = 0; i < 11 && days >= jDaysInMonth[i]; i++) {
      days -= jDaysInMonth[i];
      jm++;
    }
    const jd = days + 1;

    return [jy, jm + 1, jd];
  }

  public static digits(value: string | number): string {
    const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    let str = String(value);
    for (let i = 0; i < 10; i++) {
      str = str.split(en[i]).join(fa[i]);
    }
    return str;
  }

  public static format(dateInput?: Date | string | null, includeTime = false): string {
    if (!dateInput) return '—';
    const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(date.getTime())) return String(dateInput);

    const gy = date.getFullYear();
    const gm = date.getMonth() + 1;
    const gd = date.getDate();

    const [jy, jm, jd] = this.toJalaliParts(gy, gm, gd);
    const monthName = this.MONTHS[jm - 1];

    let result = `${this.digits(jd)} ${monthName} ${this.digits(jy)}`;
    if (includeTime) {
      const h = String(date.getHours()).padStart(2, '0');
      const m = String(date.getMinutes()).padStart(2, '0');
      result += ` ساعت ${this.digits(h)}:${this.digits(m)}`;
    }
    return result;
  }

  public static formatNumber(num: number): string {
    return this.digits(new Intl.NumberFormat('en-US').format(num));
  }
}
