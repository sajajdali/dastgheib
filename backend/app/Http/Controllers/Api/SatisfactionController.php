<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SatisfactionAnswer;
use App\Models\SatisfactionInvitation;
use App\Models\SatisfactionResponse;
use App\Models\Patient;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SatisfactionController extends Controller
{
    public static function defaultForm(): array
    {
        $options = collect([
            ['value'=>'excellent','label'=>'عالی','score'=>5], ['value'=>'good','label'=>'خوب','score'=>4],
            ['value'=>'average','label'=>'متوسط','score'=>3], ['value'=>'bad','label'=>'بد','score'=>2],
            ['value'=>'weak','label'=>'ضعیف','score'=>1],
        ])->map(fn ($option) => [...$option, 'active'=>true])->all();
        $question = fn ($id, $title, $type='rating', $required=true) => compact('id','title','type','required') + ['options'=>$type === 'rating' ? $options : []];

        return [
            'title'=>'نظرسنجی کلینیک',
            'intro_text'=>'از اینکه ما را برای مراقبت از سلامتتان انتخاب کردید، صمیمانه سپاسگزاریم. با پاسخ به چند سؤال کوتاه به ما کمک می‌کنید بهتر شویم.',
            'completion_text'=>'پاسخ‌های شما با موفقیت ثبت شد. از همراهی شما سپاسگزاریم.',
            'questions'=>[
                $question('staff','طرز برخورد پرسنل چطور بود ؟'),
                $question('doctor','طرز برخورد پزشک چطور بود ؟'),
                $question('cleanliness','نظافت مجموعه چطور بود ؟'),
                $question('environment','آیا محیط مجموعه آرامبخش بود ؟'),
                $question('return','آیا مجدد ما را انتخاب میکنید ؟'),
                $question('description','توضیحات:','textarea',false),
            ],
        ];
    }

    public static function form(): array
    {
        $raw = AppSetting::getByKey('satisfaction_form_settings', '');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($decoded) && ! empty($decoded['questions']) ? $decoded : self::defaultForm();
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'=>['required','string','max:150'],
            'intro_text'=>['nullable','string','max:3000'], 'completion_text'=>['nullable','string','max:3000'],
            'questions'=>['required','array','min:1','max:50'], 'questions.*.id'=>['required','string','max:100'],
            'questions.*.title'=>['required','string','max:500'], 'questions.*.type'=>['required','in:rating,textarea,text'],
            'questions.*.required'=>['required','boolean'], 'questions.*.options'=>['present','array','max:10'],
            'questions.*.options.*.value'=>['required','string','max:50'], 'questions.*.options.*.label'=>['required','string','max:100'],
            'questions.*.options.*.active'=>['required','boolean'], 'questions.*.options.*.score'=>['nullable','integer','min:1','max:5'],
        ]);
        AppSetting::updateOrCreate(['key'=>'satisfaction_form_settings'], ['value'=>json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return response()->json(['message'=>'فرم رضایت‌مندی ذخیره شد.','satisfaction_form'=>$data]);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $invite = SatisfactionInvitation::where('token', $token)->first();
        abort_unless($invite, 404, 'لینک رضایت‌مندی معتبر نیست.');
        abort_if($invite->expires_at?->isPast(), 410, 'مهلت پاسخ‌گویی به این فرم تمام شده است.');
        if (! $invite->opened_at) $invite->update(['opened_at'=>now()]);
        $form = $invite->form_snapshot;
        $form['title'] = self::form()['title'] ?? 'نظرسنجی کلینیک';
        $form['clinic_name'] = (string) AppSetting::getByKey('company_name', AppSetting::getByKey('clinic_name', ''));
        $logo = (string) AppSetting::getByKey('company_logo', '');
        $form['logo_url'] = $logo === '' ? '' : (parse_url($logo, PHP_URL_PATH) ?: $logo);
        return response()->json(['form'=>$form,'answered'=>filled($invite->answered_at)]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $invite = SatisfactionInvitation::where('token', $token)->firstOrFail();
        abort_if($invite->expires_at?->isPast(), 410, 'مهلت پاسخ‌گویی به این فرم تمام شده است.');
        abort_if($invite->answered_at, 409, 'پاسخ این فرم قبلاً ثبت شده است.');
        $answers = $request->validate(['answers'=>['required','array'],'answers.*.id'=>['required','string'],'answers.*.value'=>['nullable','string','max:2000']])['answers'];
        $questions = collect($invite->form_snapshot['questions'] ?? [])->keyBy('id');
        foreach ($questions as $question) {
            $answer = collect($answers)->firstWhere('id', $question['id']);
            if (($question['required'] ?? false) && (! is_array($answer) || blank($answer['value'] ?? null))) {
                abort(422, 'پاسخ سؤال‌های اجباری الزامی است.');
            }
            if (($question['type'] ?? '') === 'rating' && filled($answer['value'] ?? null)) {
                $allowed = collect($question['options'] ?? [])->where('active', true)->pluck('value');
                abort_unless($allowed->contains($answer['value']), 422, 'یکی از گزینه‌های انتخاب‌شده معتبر نیست.');
            }
        }
        DB::transaction(function () use ($invite, $answers, $questions) {
            $response = SatisfactionResponse::create(['patient_id'=>$invite->patient_id,'appointment_id'=>$invite->appointment_id,'answered_on'=>now()->format('Y-m-d')]);
            foreach ($answers as $answer) {
                $question = $questions->get($answer['id']); if (! $question) continue;
                $option = collect($question['options'] ?? [])->firstWhere('value', $answer['value']);
                SatisfactionAnswer::create([
                    'satisfaction_response_id'=>$response->id, 'question_key'=>$question['id'], 'question_label'=>$question['title'],
                    'question_type'=>$question['type'], 'option_key'=>$option['value'] ?? null,
                    'answer_value'=>($question['type'] ?? '') === 'rating' ? ($option['label'] ?? null) : (is_scalar($answer['value']) ? (string) $answer['value'] : null),
                    'score'=>$option['score'] ?? null,
                ]);
            }
            $invite->update(['answered_at'=>now()]);
        });
        return response()->json(['message'=>$invite->form_snapshot['completion_text'] ?? 'پاسخ شما ثبت شد.']);
    }

    public function patientResponses(Patient $patient): JsonResponse
    {
        $appointmentIds = collect();
        if ($patient->file_number || $patient->phone) {
            $appointmentIds = Appointment::query()->where(function ($query) use ($patient) {
                if ($patient->file_number) {
                    $query->where('file_number', $patient->file_number);
                }
                if ($patient->phone) {
                    $patient->file_number
                        ? $query->orWhere('phone', $patient->phone)
                        : $query->where('phone', $patient->phone);
                }
            })->pluck('id');
        }
        $responses = SatisfactionResponse::query()
            ->with(['answers:id,satisfaction_response_id,question_key,question_label,question_type,option_key,answer_value,score','appointment:id,month,day_num,completed_at,doctor'])
            ->where(function ($query) use ($patient, $appointmentIds) {
                $query->where('patient_id', $patient->id);
                if ($appointmentIds->isNotEmpty()) $query->orWhereIn('appointment_id', $appointmentIds);
            })->latest('id')->get();
        $invites = SatisfactionInvitation::whereIn('appointment_id', $responses->pluck('appointment_id')->filter())->get()->keyBy('appointment_id');
        $defaultLabels = ['excellent'=>'عالی','good'=>'خوب','average'=>'متوسط','bad'=>'بد','weak'=>'ضعیف'];
        return response()->json($responses->map(function ($response) use ($invites, $defaultLabels) {
            $invitation = $invites->get($response->appointment_id);
            $snapshot = $invitation?->form_snapshot ?? [];
            $questions = collect($snapshot['questions'] ?? [])->keyBy('id');
            $answers = $response->answers->map(function ($answer) use ($questions, $defaultLabels) {
                $question = $questions->get($answer->question_key, []);
                $option = collect($question['options'] ?? [])->firstWhere('value', $answer->option_key);
                $display = $answer->question_type === 'rating'
                    ? ($option['label'] ?? $answer->answer_value ?? $defaultLabels[$answer->option_key] ?? '—')
                    : ($answer->answer_value ?: '—');
                $options = collect($question['options'] ?? [])
                    ->where('active', true)
                    ->map(fn ($item) => [
                        'value' => $item['value'] ?? null,
                        'label' => $item['label'] ?? null,
                        'score' => $item['score'] ?? null,
                    ])->values();
                return [
                    'id'=>$answer->id,
                    'question_key'=>$answer->question_key,
                    'question'=>$answer->question_label,
                    'type'=>$answer->question_type,
                    'option_key'=>$answer->option_key,
                    'options'=>$options,
                    'answer'=>$display,
                    'score'=>$answer->score,
                ];
            })->values();
            $averageScore = $response->answers->whereNotNull('score')->avg('score');
            return [
                'id'=>$response->id,'answered_on'=>$response->answered_on,'created_at'=>$response->created_at?->toIso8601String(),
                'form_number'=>$invitation?->id ?? $response->id,
                'submitted_at'=>$invitation?->answered_at?->toIso8601String() ?? $response->created_at?->toIso8601String(),
                'appointment_id'=>$response->appointment_id,'appointment'=>$response->appointment,
                'average_score'=>$averageScore === null ? null : round((float) $averageScore, 1),
                'answers'=>$answers,
            ];
        })->values());
    }
}
