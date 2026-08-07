<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Services\ShsmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompletionSmsController extends Controller
{
    public function __construct(private ShsmsService $sms)
    {
    }

    private function smsTemplates()
    {
        return collect(json_decode((string) AppSetting::getByKey('sms_templates', '[]'), true));
    }

    private function activeTemplate(string $category): string
    {
        $matched = $this->smsTemplates()
            ->first(fn($item) => ($item['category'] ?? '') === $category && ($item['active'] ?? true));
        $template = $matched['content'] ?? (string) AppSetting::getByKey('sms_'.$category, '');

        $template = trim((string) $template);
        if ($template === '') {
            throw new \RuntimeException('نام الگوی SHSMS در تنظیمات تعریف نشده است.');
        }

        return $template;
    }

    private function sendTemplateSms(string $recipient, string $template, array $params): void
    {
        $this->sms->sendTemplate($recipient, $template, $params);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'types' => ['required','array','min:1'], 'types.*' => ['in:referral_credit,treatment_care,payment_link,welcome'],
            'patient_phone' => ['required','string','max:30'], 'patient_name' => ['nullable','string','max:255'],
            'referrer_phone' => ['nullable','string','max:30'], 'referral_amount' => ['nullable','numeric','min:0'],
            'payment_link' => ['nullable','string','max:2000'], 'payment_amount' => ['nullable','numeric','min:0'],
            'reference' => ['required','string','max:190'],
        ]);
        if (!config('services.shsms.endpoint') || !config('services.shsms.token')) {
            return response()->json(['message'=>'اتصال SHSMS تنظیم نشده است. مقادیر SHSMS_ENDPOINT و SHSMS_API_TOKEN را در env وارد کنید.'], 422);
        }
        $results = [];
        foreach ($data['types'] as $type) {
            try {
                $recipient = $type === 'referral_credit' ? ($data['referrer_phone'] ?? '') : $data['patient_phone'];
                if (!$recipient) throw new \RuntimeException('شماره موبایل گیرنده وارد نشده است.');
                $referrer = $type === 'referral_credit' ? Patient::where('phone',$recipient)->first() : null;
                if ($type === 'referral_credit' && !$referrer) throw new \RuntimeException('پرونده معرف پیدا نشد.');
                if ($type === 'payment_link' && empty($data['payment_link'])) throw new \RuntimeException('لینک پرداخت ساخته نشده است.');
                $amount = $type === 'payment_link'
                    ? (float) ($data['payment_amount'] ?? 0)
                    : (float) ($data['referral_amount'] ?? 0);
                if ($type === 'referral_credit' && $amount <= 0) throw new \RuntimeException('مبلغ واریز معرف مشخص نشده است.');
                $balance = $referrer ? $referrer->wallet_balance + $amount : 0;
                $template = $this->activeTemplate($type);
                $link = $type === 'payment_link'
                    ? (string) ($data['payment_link'] ?? '')
                    : (string) config('services.shsms.treatment_link');
                $this->sendTemplateSms($recipient, $template, $this->paramsFor($type, [
                    'name' => $data['patient_name'] ?? '',
                    'amount' => number_format($amount),
                    'balance' => number_format($balance),
                    'link' => $link,
                ]));
                if ($referrer) {
                    DB::transaction(function() use($referrer,$amount,$data) {
                        $description = 'referral-reward:'.$data['reference'];
                        if (!$referrer->walletTransactions()->where('description',$description)->exists()) $referrer->walletTransactions()->create(['type'=>'deposit','amount'=>$amount,'description'=>$description]);
                    });
                    $balance = $referrer->fresh()->wallet_balance;
                }
                $results[$type] = ['success'=>true,'balance'=>$balance,'sent_at'=>now()->format('Y-m-d H:i:s')];
            } catch (\Throwable $e) { $results[$type] = ['success'=>false,'message'=>$e->getMessage()]; }
        }
        return response()->json(['results'=>$results]);
    }

    public function sendPaymentLink(Request $request)
    {
        $data = $request->validate([
            'patient_phone' => ['required', 'string', 'max:30'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'payment_link' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->sendTemplateSms($data['patient_phone'], $this->activeTemplate('payment_link'), $this->paramsFor('payment_link', [
                'name' => $data['patient_name'] ?? '',
                'link' => $data['payment_link'],
                'amount' => number_format((float) ($data['amount'] ?? 0)),
            ]));

            return response()->json([
                'success' => true,
                'sent_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function sendAppointment(Request $request)
    {
        $data = $request->validate([
            'types' => ['required', 'array', 'min:1'], 'types.*' => ['in:appointment,info'],
            'patient_phone' => ['required', 'string', 'max:30'], 'patient_name' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'string', 'max:50'], 'time' => ['nullable', 'string', 'max:20'],
            'doctors' => ['nullable', 'array', 'max:2'], 'doctors.*' => ['string', 'max:255'],
            'consultant' => ['nullable', 'string', 'max:255'],
        ]);
        if (!config('services.shsms.endpoint') || !config('services.shsms.token')) {
            return response()->json(['message'=>'اتصال سامانه پیامک تنظیم نشده است.'], 422);
        }
        $results = [];
        foreach ($data['types'] as $type) {
            try {
                $this->sendTemplateSms($data['patient_phone'], $this->activeTemplate($type), $this->paramsFor($type, [
                    'name' => $data['patient_name'] ?? '',
                    'date' => $data['date'] ?? '',
                    'time' => $data['time'] ?? '',
                    'doctor' => implode('، ', $data['doctors'] ?? []),
                    'consultant' => $data['consultant'] ?? '',
                    'clinic' => (string) AppSetting::getByKey('clinic_name', ''),
                ]));
                $results[$type] = ['success'=>true, 'sent_at'=>now()->format('Y-m-d H:i:s')];
            } catch (\Throwable $e) { $results[$type] = ['success'=>false, 'message'=>$e->getMessage()]; }
        }
        return response()->json(['results'=>$results]);
    }

    private function paramsFor(string $type, array $values): array
    {
        return match($type) {
            'appointment' => [$values['name'] ?? '', $values['date'] ?? '', $values['time'] ?? '', $values['doctor'] ?? '', $values['clinic'] ?? ''],
            'info' => [$values['name'] ?? '', $values['date'] ?? '', $values['time'] ?? '', $values['doctor'] ?? '', $values['consultant'] ?? '', $values['clinic'] ?? ''],
            'welcome' => [$values['name'] ?? '', (string) AppSetting::getByKey('clinic_name', '')],
            'referral_credit' => [$values['name'] ?? '', $values['amount'] ?? '', $values['balance'] ?? ''],
            'treatment_care' => [$values['name'] ?? '', $values['link'] ?? ''],
            'payment_link' => [$values['name'] ?? '', $values['link'] ?? '', $values['amount'] ?? ''],
            default => array_values($values),
        };
    }
}
