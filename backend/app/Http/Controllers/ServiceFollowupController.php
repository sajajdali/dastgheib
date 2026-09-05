<?php
namespace App\Http\Controllers;
use App\Models\ServiceFollowup;
use Illuminate\Http\Request;
class ServiceFollowupController extends Controller {
 public function index(Request $request) {
  $followups = ServiceFollowup::query()->when($request->status, fn($q,$s)=>$q->where('status',$s))->orderBy('due_date')->get();
  $patients = \App\Models\Patient::query()
    ->whereIn('phone', $followups->pluck('patient_phone')->filter()->unique())
    ->get(['id', 'first_name', 'last_name', 'phone', 'file_number', 'profile_photo_path', 'profile_thumbnail_path'])
    ->keyBy('phone');
  $appointmentFileNumbers = \App\Models\Appointment::query()
    ->whereIn('id', $followups->pluck('appointment_id')->filter()->unique())
    ->pluck('file_number', 'id');
  $patientsByFileNumber = \App\Models\Patient::query()
    ->whereIn('file_number', $appointmentFileNumbers->filter()->unique())
    ->get(['id', 'first_name', 'last_name', 'phone', 'file_number', 'profile_photo_path', 'profile_thumbnail_path'])
    ->keyBy('file_number');

  $followups->each(function (ServiceFollowup $followup) use ($patients, $patientsByFileNumber, $appointmentFileNumbers) {
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

 public function update(Request $request, ServiceFollowup $serviceFollowup) {
  $data=$request->validate(['status'=>'required|in:pending,called,booked,declined,done','action_note'=>'nullable|string|max:2000']);
  $serviceFollowup->update([...$data,'actioned_at'=>now(),'actioned_by'=>$request->user()?->id]); return $serviceFollowup;
 }
}
