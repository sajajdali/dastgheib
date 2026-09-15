<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\User;
final class HeavyReportLoadSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->first();
        $sources=['اینستاگرام','معرفی دوستان','گوگل','مراجعه حضوری','تلگرام']; $cities=['تهران','کرج','اصفهان','شیراز','مشهد']; $areas=['صورت','گردن','دست','شکم','پا','زیر بغل']; $sections=['پوست','مو','لیزر','زیبایی']; $subs=['بوتاکس','ژل و فیلر','مزوتراپی','لیزر موهای زائد']; $methods=['نقدی','کارت‌خوان','کارت به کارت','چک','اقساط'];
        $doctor='پزشک تستی'; $consultant='مشاور تستی';
        for($batch=0;$batch<10000;$batch+=500){
            DB::transaction(function() use($batch,$sources,$cities,$areas,$sections,$subs,$methods,$doctor,$consultant,$admin){
                $now=now(); $patients=[];
                for($i=$batch;$i<$batch+500 && $i<10000;$i++){ $id=$i+1; $patients[]=['first_name'=>'مراجع تستی '.$id,'last_name'=>'بارگذاری','phone'=>'0999'.str_pad((string)$id,7,'0',STR_PAD_LEFT),'file_number'=>'LOADTEST-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),'gender'=>$id%2?'زن':'مرد','birth_date'=>sprintf('%d/%02d/%02d',1360+$id%25,1+$id%12,1+$id%28),'city'=>$cities[$id%count($cities)],'area'=>$areas[$id%count($areas)],'financial_status'=>['ضعیف','متوسط','خوب','عالی'][$id%4],'customer_level'=>['blue','silver','gold','problematic'][$id%4],'created_at'=>$now,'updated_at'=>$now]; }
                DB::table('patients')->insert($patients); $rows=DB::table('patients')->whereBetween('id',[DB::getPdo()->lastInsertId(),DB::getPdo()->lastInsertId()+499])->get();
                foreach($rows as $p){ for($j=1;$j<=5;$j++){ $n=$p->id*10+$j; $original=1000000+($n%20)*250000; $discount=$n%4===0?100000:0; $rows2=['month'=>sprintf('1405-%02d',1+$j),'day_num'=>1+$n%28,'lastname'=>$p->last_name,'gender'=>$p->gender,'phone'=>$p->phone,'file_number'=>$p->file_number,'status'=>['آمد','وقت داده شد','کنسل شد','پیگیری'][$n%4],'done'=>$n%3?'انجام شد':'انجام نشد','doctor'=>$doctor,'consultant'=>$consultant,'source'=>$sources[$n%count($sources)],'services'=>json_encode([['name'=>$subs[$n%count($subs)],'section'=>$sections[$n%count($sections)],'subsection'=>$subs[$n%count($subs)],'tags'=>[$areas[$n%count($areas)]],'cc'=>1+$n%5,'addons'=>[['name'=>'جانبی تستی '.($n%8+1),'cc'=>1]]]],JSON_UNESCAPED_UNICODE),'original_amount'=>$original,'discount'=>$discount,'amount'=>$original-$discount,'debt'=>$n%5===0?250000:0,'payment_method'=>$methods[$n%count($methods)],'payment_account'=>'حساب تستی '.($n%3+1),'created_at'=>$now,'updated_at'=>$now]; DB::table('appointments')->insert($rows2); } }
            });
            $this->command?->info('ساخت '.min($batch+500,10000).' از ۱۰۰۰۰ مراجع تستی');
        }
    }
}
