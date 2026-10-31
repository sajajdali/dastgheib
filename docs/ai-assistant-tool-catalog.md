# کاتالوگ ابزارهای دستیار هوش مصنوعی کلینیک

**وضعیت:** طرح فنی بر اساس بررسی کد موجود، ۳۰ اکتبر ۲۰۲۶. سیزده ابزار خواندنی پیاده‌سازی شده‌اند: `find_doctors` (معادل `resolve_doctor` این طرح)، `count_doctors`، `count_patients`، `count_staff`، `count_inventory_items`، `count_appointments`، `list_appointments`، `list_followups`، `list_service_followups`، `search_patients`، `get_financial_metric`، `get_doctor_revenue` (آوردهٔ پزشکان از گزارش عملکرد موجود) و `open_clinic_page` (معادل `navigate_to_view`). جزئیات اجرا و محدودیت‌ها در [ثبت پیشرفت](ai-assistant-progress.md) آمده است. سایر ردیف‌های این سند پیشنهاد آینده‌اند.

## هدف و محدوده بررسی

دستیار باید سؤال فارسی، متن یا ویس، را به درخواست‌های **خواندنی و مجاز** از داده‌های همین کلینیک تبدیل کند و پاسخ را به شکل عدد، فهرست/جدول، یا لینک داخلیِ صفحه‌ای با فیلتر مناسب برگرداند. این کاتالوگ بر پایه مسیرهای `backend/routes/api.php`، کنترلرها، سرویس‌های گزارش، مدل‌ها، تنظیمات مجوز و صفحه‌های `Frontend/src/components` تهیه شده است. پنل مرکزی مدیریت مستأجرها، صورتحساب اشتراک و فروشگاه مرکزی در دستیار عملیاتی کلینیک قرار نمی‌گیرند؛ آن‌ها دامنه احراز هویت دیگری در `backend/routes/web.php` دارند.

### وضعیت‌های این سند

- **آماده برای آداپتور:** مدل، سرویس یا API خواندنی موجود است؛ هنوز باید قرارداد ابزار، کنترل دسترسی و شکل پاسخ مخصوص دستیار ساخته شود.
- **نیازمند پرس‌وجوی اختصاصی:** داده موجود است، ولی API فعلی برای سؤال طبیعی، فیلتر، صفحه‌بندی یا شمارش مناسب نیست.
- **نیازمند تصمیم محصول:** پیش از پیاده‌سازی، معنی شاخص یا سطح دسترسی باید مشخص شود.

**هیچ API فعلی را نباید بدون کنترل مجدد مجوز و کمینه‌سازی خروجی مستقیماً به مدل داد.** اجرای ابزار در بک‌اند Laravel و بعد از شناسایی tenant از دامنه انجام می‌شود؛ مدل فقط نام ابزار و پارامترهای اعتبارسنجی‌شده را پیشنهاد می‌دهد.

## فهرست ابزارهای خواندنی پیشنهادی

نام‌ها، قرارداد پیشنهادی برای نسخه آینده هستند، نه نام endpoint موجود. همه تاریخ‌های ورودی و خروجی کاربرمحور شمسی `YYYY-MM-DD`، با «امروز» در منطقه زمانی `Asia/Tehran` باشند. `limit` حداکثر ۵۰ و فهرست‌ها صفحه‌بندی شوند. ستون «مجوز» حداقل پیشنهادی است و باید در بک‌اند بررسی شود.

| اولویت | ابزار پیشنهادی و سؤال نمونه | پارامترها و خروجی | منبع موجود؛ وضعیت | مجوز پیشنهادی |
| --- | --- | --- | --- | --- |
| ۱ | `resolve_doctor`؛ «دکتر احمدی» کدام پزشک است؟ | نام → شناسه/نام دقیق؛ در تشابه نام سؤال تکمیلی | `GET /api/doctors`، `Doctor`؛ آماده برای آداپتور | `appointments.view` یا `resources.view` |
| ۱ | `count_appointments`؛ «امروز چند نوبت داریم؟» | بازه، پزشک، وضعیت، بخش → تعداد و تفکیک وضعیت | `Appointment`، `AppointmentController::getAppointments`؛ نیازمند پرس‌وجوی شمارش مستقل | `appointments.view` |
| ۱ | `list_appointments`؛ «نوبت‌های امروز دکتر احمدی را بیار» | بازه، پزشک، وضعیت، بخش، صفحه → ردیف‌های محدود با تاریخ/ساعت/وضعیت و شناسه | `GET /api/appointments` فعلاً ماه کامل را برمی‌گرداند؛ نیازمند API فیلتر و صفحه‌بندی | `appointments.view`؛ شماره تلفن فقط با مجوز مربوط |
| ۱ | `get_appointment_summary`؛ «چند نفر آمدند یا کنسل کردند؟» | بازه/پزشک → تفکیک وضعیت و درصد با مخرج مشخص | `ClinicReportService::statusCounts` و `/api/clinic-report/cancellation-rate`؛ آماده برای آداپتور | `appointments.view` یا مجوز گزارش مناسب |
| ۱ | `get_financial_metric`؛ «درآمد این ماه چقدر است؟» | بازه، شاخص صریح، پزشک اختیاری → مبلغ، واحد و تعریف محاسبه | `ClinicReportService::dashboard`، `Expense`، پرداخت نوبت؛ **نیازمند تصمیم محصول و آداپتور سبک** | درآمد نوبت: `appointments.income`؛ مالی/سود: `reports.financial` |
| ۱ | `list_service_followups`؛ «پیگیری‌های عقب‌افتاده را بیار» | وضعیت، سررسید از/تا، خدمت، صفحه → جدول و تعداد | `GET /api/service-followups` فقط وضعیت می‌گیرد و همه را یکجا برمی‌گرداند؛ نیازمند فیلتر/صفحه‌بندی | `followups.view` |
| ۱ | `navigate_to_view`؛ «این‌ها را در جدول وقت‌دهی نشان بده» | صفحه و فیلترِ تأییدشده → فرمان UI داخلی | `App.vue::changePage`، `Time.vue`، `gozaresh.vue`؛ نیازمند قرارداد انتقال فیلتر به صفحه | همان مجوز صفحه و ابزار داده |
| ۲ | `search_patients`؛ «پرونده خانم ... را پیدا کن» | نام، شماره پرونده، تلفن یا کد ملی → حداکثر چند نتیجه کمینه | `GET /api/patients/search`، سقف ۲۵ نتیجه دارد؛ آماده برای آداپتور | `patients.view`؛ نمایش تلفن فقط با `patients.view_phone` و سیاست پنهان‌سازی |
| ۲ | `get_patient_overview`؛ «این بیمار آخرین بار کی مراجعه کرد؟» | شناسه بیمار → نمای خلاصه پرونده/تاریخچه نوبت | `Patient`، `AppointmentController::patientHistory`، جست‌وجوی پرونده؛ نیازمند ترکیب امن | `patients.view` + `appointments.view` برای تاریخچه |
| ۲ | `get_upcoming_birthdays`؛ «تولدهای هفته بعد» | تعداد روز تا ۳۰ → فهرست محدود | `GET /api/patients/upcoming-birthdays`؛ آماده برای آداپتور | `patients.view` |
| ۲ | `get_wallet_or_debt`؛ «مانده کیف پول/بدهی بیمار چقدر است؟» | شناسه بیمار → مانده، بدهی، تاریخ محاسبه | `Patient` و `GET /api/patients/{patient}/wallet/transactions`؛ نیازمند مجوز مالی روشن | `patients.wallet` یا مجوز مالی مشخص؛ صرف `patients.view` کافی نیست |
| ۲ | `build_report_preview`؛ «گزارش نوبت‌های ماه با پزشک و مبلغ» | منبع، بازه، فیلترها، ستون‌های مجاز → پیش‌نمایش محدود/فرمان باز کردن گزارش‌ساز | `config/dynamic_reports.php` و `DynamicReportController`؛ نیازمند آداپتور و انتقال فیلتر به UI | `reports.view` + مجوز هر ستون حساس |
| ۲ | `get_report_kpi`؛ «نرخ کنسلی، تعداد مراجعین، سود» | شاخص مجاز و بازه → عدد با روش محاسبه | `ClinicReportService::dashboard` و `GET /api/reports/dashboard`؛ آماده برای آداپتور سبک | زیرمجوز گزارش مربوط؛ سود/هزینه با `reports.financial` |
| ۲ | `get_report_widget`؛ «پزشکان برتر یا خدمات پرفروش» | نوع ویجت، بازه → نتیجه یا وضعیت تازه‌سازی | `ReportWidgetController` برای ۱۷ ویجت؛ snapshotها موجودند؛ نیازمند قرارداد تازگی داده | `reports.view` + مجوز موضوعی (`reports.doctors`، `reports.marketing` و غیره) |
| ۲ | `search_campaign_followups`؛ «کمپین‌های فعال و پیگیری‌های آن» | نام/وضعیت/بازه → خلاصه کمپین و ردیف‌های محدود | `CampaignController`، `Campaign::ui_payload` و صفحه `flwup.vue`؛ نیازمند استخراج/پرس‌وجوی اختصاصی | `followups.view`؛ هزینه کمپین با `followups.campaign_cost` |
| ۲ | `get_inventory_status`؛ «کدام کالاها کم‌موجودند؟» | بخش، آستانه، نام → موجودی/حداقل موجودی | `InventoryController::index`، `Inventory`، `InventoryAddonDefinition`؛ نیازمند آداپتور سبک | `inventory.view`؛ قیمت تمام‌شده با `inventory.cost` |
| ۲ | `get_inventory_movements`؛ «گردش این کالا» | شناسه کالا، بازه، صفحه → تغییرات موجودی | `GET /api/inventory/{inventory}/movements` بازه و `limit` دارد اما صفحه‌بندی ندارد؛ نیازمند تکمیل برای صفحات بعد | `inventory.view` |
| ۲ | `get_expense_summary`؛ «هزینه تبلیغات این ماه چقدر شد؟» | بازه، نوع، دسته → جمع و تفکیک | `ExpenseController` و `/api/clinic-report/expenses`؛ آماده برای آداپتور | `bills.view` و برای تحلیل مالی `reports.financial` |
| ۲ | `get_service_catalog`؛ «چه خدماتی داریم؟» | نام/بخش → خدمت، بخش، قیمت قابل‌نمایش | `Inventory`، `InventorySection`، `GET /api/inventory/context`، `GET /api/service-tags`؛ نیازمند نمای کمینه | `services.view` یا `inventory.view` |
| ۳ | `get_staff_attendance`؛ «کارکرد ماهانه دکتر ...» | منبع، ماه → روزها/ساعت‌ها | `AttendanceMonthController::index`؛ آماده برای آداپتور، با محدودیت خود/مدیر | `attendance.view`؛ مشاهده دیگران فقط با `attendance.manage` |
| ۳ | `get_payroll_summary`؛ «تسویه پزشک در مهر» | منبع، ماه → حقوق/پورسانت/کسورات | `PayrollReportController::show`؛ آماده برای آداپتور حساس | `payroll.view`؛ گزارش شخصی از `personal_report.view` |
| ۳ | `get_ticket_summary`؛ «تیکت‌های فوری باز» | وضعیت، مسئول، اولویت، صفحه → فهرست | `TicketController::index` فعلاً همه را می‌گیرد؛ نیازمند فیلتر/صفحه‌بندی | `tickets.view` |
| ۳ | `get_satisfaction_summary`؛ «رضایت بیماران این ماه» | بازه → شاخص و تعداد پاسخ | `SatisfactionController` و ویجت رضایت `ClinicReportService`؛ آماده برای آداپتور | `reports.view`؛ پاسخ فردی با `patients.view` و مجوز روشن |
| ۳ | `get_photo_metadata`؛ «چند جفت عکس قبل و بعد کامل داریم؟» | بازه، خدمت، زاویه → شمارش/فراداده | `PhotoComparisonController::index`؛ فعلاً همه تصاویر را بارگذاری می‌کند؛ نیازمند پرس‌وجوی شمارشی سبک | `photos.view`؛ تصویر/مشخصات بیمار فقط در سطح مجاز و با رعایت رضایت استفاده |
| ۳ | `get_beauty_followups`؛ «موارد باز زیبایار» | بازه، وضعیت → تعداد/فهرست محدود | `BeautyAnnotationController::index`؛ نیازمند آداپتور و فیلتر مناسب | `beauty.view` |
| ۳ | `get_calendar_events`؛ «فردا تعطیل است؟» | ماه/تاریخ → رویداد و وضعیت تعطیلی | `CalendarController::index`؛ آماده برای آداپتور | کاربر واردشده در tenant |
| ۳ | `search_activity_logs`؛ «چه کسی این نوبت را تغییر داد؟» | موضوع، کاربر، بازه، صفحه → رویدادهای محدود | `ActivityLogController::index` صفحه‌بندی و فیلتر دارد؛ آماده برای آداپتور | `activity_logs.view`؛ پنهان‌سازی شماره تلفن حفظ شود |
| ۳ | `get_storage_status`؛ «فضای باقی‌مانده چقدر است؟» | بدون ورودی → مصرف/سقف | `GET /api/storage/status`؛ آماده برای آداپتور | وضعیت عمومی؛ جزئیات مدیریتی فقط نقش مدیر |
| ۳ | `get_booking_availability`؛ «چه ساعتی برای این خدمت خالی است؟» | خدمت، پزشک/منبع، تاریخ → بازه‌های قابل رزرو | تنظیمات ظرفیت، منابع، استثناها و رزروهای خدمت در `InventoryBooking*` و `AppointmentServiceBooking` موجود است؛ نیازمند منطق اختصاصی یکسان با وقت‌دهی | `appointments.view`؛ بدون ثبت نوبت |
| ۳ | `get_automatic_sms_status`؛ «برای این نوبت چه پیامک خودکاری تنظیم شده؟» | شناسه نوبت → وضعیت سناریو/مرحله، بدون متن و شماره حساس غیرمجاز | `AppointmentAutomaticSmsService` داده را به نوبت تزیین می‌کند؛ نیازمند نمای خواندنی کمینه | `appointments.view`؛ سناریوهای مدیریتی فقط مدیر |
| ۳ | `get_patient_media_metadata`؛ «این پرونده چند عکس دارد؟» | شناسه بیمار، نوع/تاریخ → تعداد و فراداده پوشه‌ها | `PatientMediaController::index` فایل و پوشه را کامل برمی‌گرداند؛ نیازمند شمارش سبک و مجوز صریح | `patients.view` + `photos.view`، بدون ارسال خود عکس به مدل |

### منبع قدیمی تماس‌ها

`GET /api/contacts` و مدل `Contact` نیز اطلاعات تماس/پیگیری دارند، اما `App.vue` مقدار `legacyLeadsEnabled` را فعلاً `false` گذاشته و صفحه قدیمی `Notif` نمایش داده نمی‌شود. برای این داده می‌توان در آینده `search_legacy_contacts` ساخت، ولی نباید آن را با پیگیری خدمات یا کمپین مخلوط کرد. endpoint فعلی همه ردیف‌ها را یکجا و بدون مجوز موضوعی صریح در route برمی‌گرداند؛ ابتدا باید تکلیف استفاده فعال از این منبع، مجوز و صفحه‌بندی روشن شود.

## چرا «ابزار» همان endpoint فعلی نیست؟

1. **نوبت:** `GET /api/appointments` فقط `month` می‌گیرد، ردیف‌های ماه را با جزئیات زیاد برمی‌گرداند و برای شمارش «امروز» یا فهرست صفحه‌بندی‌شده ابزار مناسبی نیست. ابزار جدید باید همان قاعده «نوبت واقعی» و حذف ردیف‌های آزمایشی `LOADTEST-%` در `AppointmentController::getAppointments` را حفظ کند؛ «نوبت» را با اسلات خالی یا ردیف انتقالی اشتباه نگیرد.
2. **پیگیری:** پیگیری خدمات در جدول `service_followups` است، اما پیگیری کمپین در `campaigns.ui_payload` نیز نگهداری می‌شود. سؤال «پیگیری‌ها» بدون تعیین دامنه، مبهم است؛ ابزار باید هر دو منبع را با نام روشن ارائه کند. endpoint فعلی پیگیری خدمات صفحه‌بندی ندارد.
3. **گزارش‌ساز:** فقط دو منبع رسمی `appointments` و `patients` دارد. در `config/dynamic_reports.php` فیلترهایی مثل تاریخ، پزشک، وضعیت، بخش، ناحیه، مبلغ، بدهی و روش پرداخت ثبت شده‌اند. ساخت گزارش در `DynamicReportController::store` یک رکورد ایجاد می‌کند و `GenerateDynamicReport::dispatchSync` اجرا می‌شود؛ برای هر سؤال کوتاه نباید گزارش سنگین بسازیم. پیش‌نمایش محدود و باز کردن فرم با فیلتر آماده، دو رفتار جدا باشند.
4. **گزارش‌های آماده:** ویجت‌ها snapshot دارند و ممکن است نتیجهٔ آخرین محاسبه باشند. دستیار باید زمان محاسبه را نشان دهد و اگر داده تازه نیست، محاسبهٔ کنترل‌شده یا اعلام کهنگی انجام دهد.
5. **درآمد:** در `ClinicReportService::dashboard`، `cash_collected` فعلاً برابر `recognized_revenue` قرار می‌گیرد. این شاخص فقط نوبت‌های «انجام شد» و تسویه‌شده را جمع می‌زند و با همه دریافتی‌های صندوق یا سود یکی فرض نمی‌شود. برای «درآمد» باید شاخص پیش‌فرض محصول مشخص شود؛ «فروش خدمت»، «وجوه وصول‌شده»، «هزینه» و «سود» عنوان و فرمول مستقل داشته باشند. مبلغ و واحد پول کنار پاسخ بیاید.
6. **پزشک و تاریخ:** نام پزشک در خود `Appointment` به صورت متن (`doctor`) ذخیره می‌شود، در حالی که فهرست پزشکان شناسه دارد. پس ابزار حل نام باید هم‌نامی، تغییر نام و نگاشت شناسه به متن نوبت را بررسی کند. تاریخ نوبت در `month` و `day_num` شمسی است؛ بازه‌های زمانی با این دو فیلد محاسبه شوند و زمان سیستم/سرور به‌تنهایی مبنای «امروز» نباشد.
7. **موجودی و زمان خالی:** وجود ظرفیت در تنظیمات خدمت به معنای خالی بودن قطعی نیست. سؤال «چه ساعت‌هایی خالی است؟» باید قواعد منبع، استثنا، رزروها و قفل‌های وقت‌دهی را کنار هم محاسبه کند. تا زمان داشتن یک سرویس مشترک با صفحه وقت‌دهی، این ابزار نباید پاسخ قطعی یا امکان رزرو پیشنهاد دهد.

## ترکیب چند ابزار برای یک سؤال

| پرسش | زنجیره پیشنهادی | نمایش |
| --- | --- | --- |
| «امروز دکتر احمدی چند نوبت دارد؟» | `resolve_doctor` → `count_appointments` | کارت عدد با نام پزشک، تاریخ و وضعیت‌های مشمول |
| «نوبت‌های امروز را در جدول بیار» | `list_appointments` → `navigate_to_view` | جدول محدود در پنجره دستیار + دکمه باز کردن وقت‌دهی با تاریخ/فیلتر |
| «از نوبت‌های این ماه کدام‌ها پیگیری عقب‌افتاده دارند؟» | `list_service_followups` + اطلاعات نوبت مرتبط | جدول پیگیری با لینک به پیگیری/نوبت |
| «درآمد و تعداد مراجعه‌های هر پزشک این ماه» | حل شناسه پزشکان + شاخص مالی/تعداد به تفکیک پزشک | جدول با تعریف دقیق درآمد و بازه؛ در صورت نیاز ورود به گزارش |
| «گزارش بیماران شهر X که خدمت Y گرفته‌اند» | اعتبارسنجی فیلترها → `build_report_preview` | پیش‌نمایش محدود و دکمه ورود به گزارش‌ساز |

خروجی هر ابزار باید `source`, `period`, `filters_applied`, `calculated_at` و `items/count/amount` متناسب با نوع داده داشته باشد. ترکیب نتایج توسط سرور کنترل شود؛ مدل نباید عدد نهایی را از حافظه یا حدس بسازد.

## ناوبری و نمایش در فرانت‌اند

- محل مناسب دکمه شناور، `Frontend/src/App.vue` بعد از احراز هویت است. `App.vue::changePage` هم مجوز صفحه و هم فعال بودن ماژول tenant را می‌سنجد؛ دستیار باید از همین مسیر ناوبری استفاده کند، نه تغییر مستقیم `currentPage`.
- `Time.vue` نمایش جدولی و تایم‌لاین و فیلترهای پزشک/بخش دارد. `App.vue` اکنون درخواست بازکردن تایم‌لاین با تاریخ را منتقل می‌کند؛ برای «جدول نوبت‌های امروز» باید قرارداد انتقال **view=table، تاریخ و فیلترها** اضافه شود.
- `gozaresh.vue` بین داشبورد و گزارش‌ساز جابه‌جا می‌شود، ولی prop/رویداد عمومی برای بازکردن گزارش‌ساز با فیلترهای دستیار ندارد. `ReportBuilder.vue` نیز باید ورودی فیلتر کنترل‌شده بپذیرد.
- `flwup.vue` و `ServiceFollowups.vue` برای نمایش نتیجهٔ پیگیری، ورودی جست‌وجو/فیلتر از دستیار می‌خواهند. اگر صفحه مقصد از قابلیت فعلی پشتیبانی نکند، جدول داخل پنجره دستیار نمایش داده شود و وعده بازشدن همان فیلتر داده نشود.
- کارت نتیجه و جدول، از داده ساختاریافته بک‌اند ساخته شوند؛ HTML، URL دلخواه یا کد تولیدشده توسط مدل اجرا نشود.

## قواعد مجوز، حریم خصوصی و ظرفیت

- middlewareهای `auth` و `tenant.subscription` روی API عملیاتی هستند و tenant در `routes/tenant.php` با دامنه راه‌اندازی می‌شود. شناسه tenant از درخواست کاربر تعیین شود، نه ورودی مدل. پنل مرکزی و tenantهای دیگر خارج از محدوده ابزارهای دستیار باشند.
- مجوز ابزار باید **در لحظه اجرا** در بک‌اند بررسی شود. مخفی بودن یک صفحه در منو کافی نیست. مجوز فیلد حساس نیز جداگانه لازم است: تلفن، کد ملی، مالی، عکس و اطلاعات پزشکی. متن و ویس و پاسخ مدل نیز کمینه‌سازی شوند.
- چند مسیر فعلی فقط مجوز کلی دارند: `/api/reports/dashboard` و `/api/report-builder/*` با `reports.view`، در حالی که شاخص‌ها/ستون‌های مالی دارند؛ `/api/patients/{patient}/media`، کیف پول، `/api/contacts` و چند endpoint دیگر در route مجوز موضوعی صریح ندارند. این‌ها **برای ابزار دستیار قابل استفاده مستقیم نیستند** و پیش از اتصال باید سیاست مجوزشان تعیین یا سخت‌گیرانه‌تر شوند.
- برای فهرست‌ها سقف ردیف، صفحه‌بندی، timeout، محدودیت بازه تاریخ، لاگ درخواست/ابزار/نتیجه و نرخ مصرف بگذاریم. مدل حق SQL خام، دسترسی آزاد به جدول‌ها یا فراخوانی endpointهای نوشتنی نداشته باشد.
- ثبت/تغییر نوبت، ارسال SMS، تغییر وضعیت پیگیری، برداشت کیف پول، حذف پرونده/عکس، تغییر حقوق یا انبار و تنظیمات، **ابزار خواندنی نیستند**. اگر در آینده فرمان عملیاتی اضافه شود، قرارداد جدا با پیش‌نمایش اثر، مجوز دقیق و تأیید صریح کاربر می‌خواهد.
- کلید OpenAI فقط روی سرور نگهداری شود. انتخاب مدل از فهرست مجاز مدیر کلینیک باشد. ویس ابتدا رونویسی شود و همان مسیر ابزارهای متنی را طی کند. پاسخ مبهم مثل «دکتر احمدی» با چند تطبیق یا «درآمد» بدون تعریف، پرسش روشن‌کننده برگرداند.

## ترتیب پیشنهادی اجرا

1. **هسته:** API دستیار، احراز هویت/tenant، ثبت رخداد، کنترل مصرف، قرارداد پاسخ `metric | table | navigation | clarification` و اتصال OpenAI.
2. **نسخه اول:** شش ابزار اصلی `resolve_doctor`، `count_appointments`، `list_appointments`، `get_appointment_summary`، `list_service_followups` و `navigate_to_view`؛ سپس شاخص مالی پس از تصویب تعریف دقیق آن. متن و ویس کوتاه.
3. **نسخه دوم:** پرونده، گزارش‌ساز/ویجت‌ها، کمپین و انبار؛ انتقال فیلتر به صفحه مقصد و بررسی تاریخ تازگی snapshot.
4. **نسخه سوم:** حقوق، حضور و غیاب، عکس/زیبایار، رضایت، تیکت و سوابق فعالیت با مجوزهای دقیق‌تر و آزمون محرمانگی.

## مسیرهای کد بررسی‌شده

- API و tenant: `backend/routes/api.php`، `backend/routes/tenant.php`، `backend/app/Providers/TenancyServiceProvider.php`
- مجوزها: `backend/config/roles.php`، `backend/database/seeders/RolePermissionSeeder.php`، `Frontend/src/components/menu.vue`، `Frontend/src/App.vue`
- نوبت و مالی: `backend/app/Http/Controllers/AppointmentController.php`، `backend/app/Services/ClinicReportService.php`، `backend/app/Http/Controllers/Api/ClinicReportController.php`
- گزارش: `backend/config/dynamic_reports.php`، `backend/app/Http/Requests/StoreDynamicReportRequest.php`، `backend/app/Http/Controllers/Api/DynamicReportController.php`، `backend/app/Http/Controllers/Api/ReportWidgetController.php`
- پیگیری/کمپین: `backend/app/Http/Controllers/ServiceFollowupController.php`، `backend/app/Http/Controllers/Api/CampaignController.php`، `Frontend/src/components/flwup.vue`
- سایر دامنه‌ها: کنترلرهای `PatientController`، `PatientMediaController`، `InventoryController`، `ExpenseController`، `AttendanceMonthController`، `PayrollReportController`، `PhotoComparisonController`، `BeautyAnnotationController`، `TicketController`، `CalendarController`، `ActivityLogController`، `AutomaticSmsScenarioController` و `ContactController`
- نمایش: `Frontend/src/components/Time.vue`، `Frontend/src/components/gozaresh.vue`، `Frontend/src/components/ReportBuilder.vue`، `Frontend/src/components/ServiceFollowups.vue`
