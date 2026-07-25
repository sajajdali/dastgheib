<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CompletionSmsController extends Controller
{
    private function smsTemplates()
    {
        return collect(json_decode((string) AppSetting::getByKey('sms_templates', '[]'), true));
    }

    private function sendSms(string $recipient, string $message): void
    {
        if (!config('services.shsms.endpoint') || !config('services.shsms.token')) {
            throw new \RuntimeException('اتصال SHSMS تنظیم نشده است. مقادیر SHSMS_ENDPOINT و SHSMS_TOKEN را در env وارد کنید.');
        }

        Http::withToken(config('services.shsms.token'))
            ->acceptJson()
            ->post(config('services.shsms.endpoint'), [
                'mobile' => $recipient,
                'message' => $message,
                'sender' => config('services.shsms.sender'),
            ])
            ->throw();
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
            return response()->json(['message'=>'اتصال SHSMS تنظیم نشده است. مقادیر SHSMS_ENDPOINT و SHSMS_TOKEN را در env وارد کنید.'], 422);
        }
        $templates = $this->smsTemplates();
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
                $content = $templates->first(fn($item) => ($item['category'] ?? '') === $type && ($item['active'] ?? true))['content'] ?? $this->defaultTemplate($type);
                $link = $type === 'payment_link'
                    ? (string) ($data['payment_link'] ?? '')
                    : (string) config('services.shsms.treatment_link');
                $message = strtr($content, ['{name}'=>$data['patient_name']??'', '{amount}'=>number_format($amount), '{balance}'=>number_format($balance), '{link}'=>$link]);
                $this->sendSms($recipient, $message);
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
            $templates = $this->smsTemplates();
            $content = $templates
                ->first(fn ($item) => ($item['category'] ?? '') === 'payment_link' && ($item['active'] ?? true))['content']
                ?? '{name} عزیز، لینک پرداخت نوبت شما: {link} مبلغ: {amount} تومان';

            $message = strtr($content, [
                '{name}' => $data['patient_name'] ?? '',
                '{link}' => $data['payment_link'],
                '{amount}' => number_format((float) ($data['amount'] ?? 0)),
                '{balance}' => '',
                '{date}' => '',
                '{time}' => '',
                '{doctor}' => '',
                '{clinic}' => '',
                '{code}' => '',
            ]);

            $this->sendSms($data['patient_phone'], $message);

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
        $templates = $this->smsTemplates();
        $results = [];
        foreach ($data['types'] as $type) {
            try {
                $content = $templates->first(fn($item) => ($item['category'] ?? '') === $type && ($item['active'] ?? true))['content']
                    ?? (string) AppSetting::getByKey('sms_'.$type, '');
                if (!trim($content)) throw new \RuntimeException('متن پیامک در تنظیمات تعریف نشده است.');
                $message = strtr($content, [
                    '{name}'=>$data['patient_name']??'', '{date}'=>$data['date']??'', '{time}'=>$data['time']??'',
                    '{doctor}'=>implode('، ', $data['doctors']??[]), '{consultant}'=>$data['consultant']??'',
                    '{clinic}'=>(string) AppSetting::getByKey('clinic_name',''), '{amount}'=>'', '{balance}'=>'', '{link}'=>'', '{code}'=>'',
                ]);
                $this->sendSms($data['patient_phone'], $message);
                $results[$type] = ['success'=>true, 'sent_at'=>now()->format('Y-m-d H:i:s')];
            } catch (\Throwable $e) { $results[$type] = ['success'=>false, 'message'=>$e->getMessage()]; }
        }
        return response()->json(['results'=>$results]);
    }

    private function defaultTemplate(string $type): string { return match($type) {
        'referral_credit'=>'{amount} تومان بابت معرفی به کیف پول شما واریز شد. موجودی فعلی: {balance} تومان.',
        'treatment_care'=>'توصیه‌های بعد از درمان: {link}',
        'payment_link'=>'{name} عزیز، لینک پرداخت نوبت شما: {link} مبلغ: {amount} تومان',
        default=>'{name} عزیز، از اعتماد شما سپاسگزاریم. به مجموعه ما خوش آمدید.'
    }; }
}
