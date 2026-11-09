<?php
namespace App\Http\Controllers;
use App\Models\ServiceFollowup;
use App\Models\Appointment;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class ServiceFollowupController extends Controller {
 public function index(Request $request) {
  $followups = ServiceFollowup::query()->when($request->status, fn($q,$s)=>$q->where('status',$s))->orderBy('due_date')->get();
  $patients = \App\Models\Patient::query()
    ->whereIn('phone', $followups->pluck('patient_phone')->filter()->unique())
    ->get(['id', 'first_name', 'last_name', 'phone', 'file_number', 'profile_photo_path', 'profile_thumbnail_path'])
    ->keyBy('phone');
  // نوبتی که پیگیری از آن آمده: برای نمایش «نوبتش چه روزی بوده» و باز کردنش در نوبت‌دهی.
  $appointments = \App\Models\Appointment::query()
    ->whereIn('id', $followups->pluck('appointment_id')->filter()->unique())
    ->get(['id', 'month', 'day_num', 'time', 'file_number'])
    ->keyBy('id');
  $appointmentFileNumbers = $appointments->map(fn ($appointment) => $appointment->file_number);
  $patientsByFileNumber = \App\Models\Patient::query()
    ->whereIn('file_number', $appointmentFileNumbers->filter()->unique())
    ->get(['id', 'first_name', 'last_name', 'phone', 'file_number', 'profile_photo_path', 'profile_thumbnail_path'])
    ->keyBy('file_number');

  $followups->each(function (ServiceFollowup $followup) use ($patients, $patientsByFileNumber, $appointmentFileNumbers, $appointments) {
    $appointment = $appointments->get($followup->appointment_id);
    $followup->setAttribute('appointment', $appointment && $appointment->month && $appointment->day_num ? [
      'id' => $appointment->id,
      'date' => sprintf('%s-%02d', $appointment->month, (int) $appointment->day_num),
      'time' => $appointment->time ? substr((string) $appointment->time, 0, 5) : null,
    ] : null);
    $patient = $patients->get($followup->patient_phone)
      ?? $patientsByFileNumber->get($appointmentFileNumbers->get($followup->appointment_id));
    $followup->setAttribute('patient', $patient ? [
      'id' => $patient->id,
      'first_name' => $patient->first_name,
      'last_name' => $patient->last_name,
      'phone' => $patient->phone,
      'file_number' => $patient->file_number,
      'avatar_url' => $patient->avatar_url,
    ] : null);
  });

  return $followups;
 }

 public function scheduleFromAppointment(Request $request, Appointment $appointment) {
  $data=$request->validate(['due_date'=>['required','date'],'reason'=>['nullable','string','max:1000']]);
  $services=collect($appointment->services ?: [])->filter(fn($s)=>trim((string)($s['name']??''))!=='');
  // وضعیت‌هایی مثل کنسلی و «پیگیری» ممکن است پیش از انتخاب خدمت ثبت شوند.
  // در این حالت نیز باید یک ردیف قابل اقدام در جدول پیگیری خدمات ایجاد شود.
  if ($services->isEmpty()) $services=collect([['name'=>'پیگیری نوبت']]);
  $result=DB::transaction(function() use($appointment,$services,$data){return $services->map(function($service) use($appointment,$data){$name=trim((string)$service['name']);$inventory=Inventory::where('name',$name)->first();$key='appointment-manual-followup|'.$appointment->id.'|'.sha1(mb_strtolower($name));$f=ServiceFollowup::where('source_key',$key)->first();$v=['appointment_id'=>$appointment->id,'inventory_id'=>$inventory?->id,'service_name'=>$name,'patient_name'=>$appointment->lastname,'patient_phone'=>$appointment->phone,'completed_at'=>$appointment->completed_at?:now(),'due_date'=>$data['due_date'],'followup_days'=>max(0,(int)($inventory?->followup_days??0)),'status'=>'pending','action_note'=>$data['reason']??null,'source_key'=>$key];if($f){$f->update($v);$f=$f->fresh();$f->setAttribute('replaced',true);return $f;} $f=ServiceFollowup::create($v);$f->setAttribute('replaced',false);return $f;})->values();});
  return response()->json(['followups'=>$result]);
 }
 public function update(Request $request, ServiceFollowup $serviceFollowup) {
  $data=$request->validate(['status'=>'required|in:pending,called,booked,declined,done','action_note'=>'nullable|string|max:2000']);
  $serviceFollowup->update([...$data,'actioned_at'=>now(),'actioned_by'=>$request->user()?->id]); return $serviceFollowup;
 }
}
