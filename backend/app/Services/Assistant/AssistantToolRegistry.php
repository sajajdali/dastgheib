<?php

namespace App\Services\Assistant;

use App\Models\User;

final class AssistantToolRegistry
{
    private const TOOLS = [
        'find_doctors' => ['permission' => 'appointments.view|resources.view|attendance.view', 'description' => 'List doctors registered in this clinic; pass an empty name to list all, or a name fragment to search. Use count_doctors for an exact total.'],
        'count_doctors' => ['permission' => 'appointments.view|resources.view|attendance.view', 'description' => 'Count all doctors registered in this clinic. Use for questions such as how many doctors or physicians the clinic has. Returns an exact database count, not the length of a truncated list.'],
        'count_patients' => ['permission' => 'patients.view', 'description' => 'Count all active patient files in this clinic. Use for questions such as how many patients or files the clinic has.'],
        'count_staff' => ['permission' => 'resources.view', 'description' => 'Count all staff members registered in this clinic. Use for questions such as how many staff or personnel the clinic has.'],
        'count_inventory_items' => ['permission' => 'inventory.view', 'description' => 'Count active inventory catalog items, including services and products. Do not call this the count of doctors or appointments.'],
        'count_appointments' => ['permission' => 'appointments.view', 'description' => 'Count real clinic appointments in a Jalali period; active means excluding cancelled, transferred and no-answer records.'],
        'list_appointments' => ['permission' => 'appointments.view', 'description' => 'List real clinic appointments with a Jalali date range and optional doctor/status filters.'],
        'list_service_followups' => ['permission' => 'followups.view', 'description' => 'List service followups due today, overdue, pending, or in a date range.'],
        'list_followups' => ['permission' => 'followups.view', 'description' => 'Search followup rows in the clinic campaigns by patient name and show due today, overdue, pending or all. Follow-up dates in returned rows are Gregorian.'],
        'search_patients' => ['permission' => 'patients.view', 'description' => 'Find patient files by name or file number. Return limited identifiers and names.'],
        'search_people' => ['permission' => 'patients.view|settings.users', 'description' => 'Find a named person across patient files and software users. Use for a full name such as Ali Mousavi. Return exact matches and buttons to open patient files; distinguish patient from software user.'],
        'preview_doctor_patients' => ['permission' => 'patients.view&reports.view', 'description' => 'Find distinct patient files with appointments attributed to a doctor in a Jalali date range. Return a bounded preview and a button to open the complete filtered report builder. Resolve ambiguous doctor names first. Use for list of patients of Dr X.'],
        'find_patient_photos' => ['permission' => 'patients.view&photos.view', 'description' => 'Find a patient by name or file number, then count and list their before, after, or all images with folder dates and angle labels. If names collide, return choices and ask which patient. Use for requests like show after photos of Ms Mousavi.'],
        'open_patient_result' => ['permission' => 'patients.view', 'description' => 'Return a verified button to open one exact patient file or one image in that file. Use after a search when the user selects a result by name, number, or position. A media_id requires photos.view.'],
        'query_clinic_records' => ['permission' => null, 'description' => 'Read, filter and count records from a permitted clinic dataset. Use for data questions beyond dedicated tools. Select a dataset and up to four field filters. Results are bounded and the total count is exact.'],
        'inspect_clinic_record' => ['permission' => null, 'description' => 'Inspect one exact record previously returned by query_clinic_records and show its permitted linked datasets with counts and interactive choices. Use after the user selects one of several matches.'],
        'get_financial_metric' => ['permission' => 'reports.financial', 'description' => 'Get a precisely named clinic financial KPI for a Jalali period: recognized revenue, expenses, or net profit according to the existing clinic report.'],
        'get_doctor_revenue' => ['permission' => 'reports.financial&reports.doctors', 'description' => 'Get attributed recognized revenue for each registered doctor from the existing doctor performance report. Use for questions like how much revenue each doctor generates. This is clinic revenue attributed to the doctor, not doctor salary, commission or take-home pay. If no period is specified, use this_month through today.'],
        'open_clinic_page' => ['permission' => null, 'description' => 'Offer a button to open the exact clinic page, settings section and nested tab requested by the user. Use for navigation, never as evidence of live data.'],
    ];

    public function allowed(User $user, string $name): bool
    {
        $tool = self::TOOLS[$name] ?? null;
        if ($tool === null) return false;
        if ($name === 'open_clinic_page') return (new AssistantNavigationCatalog)->allowed($user) !== [];
        if (in_array($name, ['query_clinic_records','inspect_clinic_record'], true)) return (new AssistantDataCatalog)->names($user) !== [];
        if ($tool['permission'] !== null) {
            foreach (explode('&', $tool['permission']) as $requiredGroup) {
                if (! collect(explode('|', $requiredGroup))->contains(fn (string $permission) => $user->can($permission))) return false;
            }
        }
        return true;
    }

    public function definitions(User $user): array
    {
        $definitions = [];
        foreach (self::TOOLS as $name => $tool) {
            if (! $this->allowed($user, $name)) continue;
            $description = $tool['description'];
            if ($name === 'query_clinic_records') $description .= ' Available datasets and filterable fields: '.(new AssistantDataCatalog)->schemaDescription($user);
            if ($name === 'open_clinic_page') $description .= ' Exact destinations: '.(new AssistantNavigationCatalog)->description($user);
            $definitions[] = [
                'type' => 'function',
                'name' => $name,
                'description' => $description,
                'strict' => true,
                'parameters' => $this->parameters($name, $user),
            ];
        }
        return $definitions;
    }

    private function parameters(string $name, User $user): array
    {
        $period = ['type' => 'string', 'enum' => ['today', 'this_month', 'custom'], 'description' => 'Jalali period. For custom provide from and to.'];
        $date = ['type' => ['string', 'null'], 'description' => 'Jalali YYYY-MM-DD or null.'];
        $doctor = ['type' => ['string', 'null'], 'description' => 'Doctor name or null.'];
        $limit = ['type' => 'integer', 'description' => 'Number of rows, from 1 to 50.'];
        $properties = match ($name) {
            'find_doctors' => ['name' => ['type' => 'string', 'description' => 'Doctor name or part of it.']],
            'count_doctors', 'count_patients', 'count_staff', 'count_inventory_items' => [],
            'count_appointments', 'list_appointments' => [
                'period' => $period, 'from' => $date, 'to' => $date, 'doctor' => $doctor,
                'status' => ['type' => 'string', 'enum' => ['active', 'all', 'scheduled', 'arrived', 'cancelled', 'no_answer', 'followup']],
                ...($name === 'list_appointments' ? ['limit' => $limit] : []),
            ],
            'list_service_followups' => [
                'scope' => ['type' => 'string', 'enum' => ['today', 'overdue', 'pending', 'all']],
                'query' => ['type' => ['string', 'null'], 'description' => 'Optional patient or service name to search.'],
                'limit' => $limit,
            ],
            'list_followups' => [
                'scope' => ['type' => 'string', 'enum' => ['today', 'overdue', 'pending', 'all']],
                'query' => ['type' => ['string', 'null'], 'description' => 'Optional patient name to search.'],
                'limit' => $limit,
            ],
            'search_patients' => ['query' => ['type' => 'string', 'description' => 'Patient name or file number.']],
            'search_people' => ['query' => ['type' => 'string', 'description' => 'Full or partial person name, at least two characters.']],
            'preview_doctor_patients' => [
                'doctor' => ['type' => 'string', 'description' => 'Doctor name or distinctive fragment.'],
                'period' => ['type' => 'string', 'enum' => ['all', 'today', 'this_month', 'custom'], 'description' => 'Jalali date range; all means all history through today.'],
                'from' => $date, 'to' => $date,
            ],
            'find_patient_photos' => [
                'patient' => ['type'=>'string','description'=>'Patient full or partial name; use empty string if file_number is known.'],
                'file_number' => ['type'=>['string','null'],'description'=>'Exact file number after the patient was selected, or null.'],
                'stage' => ['type'=>'string','enum'=>['before','after','all']],
            ],
            'open_patient_result' => [
                'file_number' => ['type'=>'string','description'=>'Exact patient file number returned by a previous search.'],
                'media_id' => ['type'=>['integer','null'],'description'=>'Exact image ID returned by find_patient_photos, or null to open the patient file.'],
            ],
            'query_clinic_records' => [
                'dataset' => ['type'=>'string','enum'=>(new AssistantDataCatalog)->names($user)],
                'filters' => ['type'=>'array','description'=>'Zero to four AND filters. Use [] for all records.',
                    'items'=>['type'=>'object','properties'=>[
                        'field'=>['type'=>'string','enum'=>(new AssistantDataCatalog)->fields($user)],
                        'operator'=>['type'=>'string','enum'=>['equals','contains','gte','lte']],
                        'value'=>['type'=>'string','description'=>'The database value; dates use stored format.'],
                    ],'required'=>['field','operator','value'],'additionalProperties'=>false]],
                'limit' => $limit,
            ],
            'inspect_clinic_record' => [
                'dataset' => ['type'=>'string','enum'=>(new AssistantDataCatalog)->names($user)],
                'id' => ['type'=>'integer','description'=>'Exact record ID returned by query_clinic_records.'],
            ],
            'get_financial_metric' => [
                'period' => $period, 'from' => $date, 'to' => $date,
                'metric' => ['type' => 'string', 'enum' => ['recognized_revenue', 'expenses', 'net_profit']],
            ],
            'get_doctor_revenue' => [
                'period' => $period, 'from' => $date, 'to' => $date, 'doctor' => $doctor,
            ],
            'open_clinic_page' => [
                'page' => ['type' => 'string', 'enum' => array_keys((new AssistantNavigationCatalog)->allowed($user))],
                'date' => $date,
                'doctor' => $doctor,
            ],
        };

        return [
            'type' => 'object',
            'properties' => $properties === [] ? (object) [] : $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
