<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\Expense; use Illuminate\Http\Request;
class ExpenseController extends Controller {
 public function index(){return Expense::query()->with('campaign:id,name')->latest('occurred_on')->get();}
 public function store(Request $r){$data=$this->validated($r);$data['created_by']=$r->user()->id;return response()->json(Expense::create($data),201);}
 public function update(Request $r,Expense $expense){$expense->update($this->validated($r));return $expense->fresh('campaign');}
 public function destroy(Expense $expense){$expense->delete();return response()->noContent();}
 private function validated(Request $r):array{return $r->validate(['occurred_on'=>['required','regex:/^1[34]\\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\\d|3[01])$/'],'category'=>['required','string','max:40'],'title'=>['required','string','max:255'],'amount'=>['required','numeric','min:0'],'type'=>['required','in:expense,income,receivable,payable'],'payment_status'=>['required','in:paid,unpaid,pending'],'party'=>['nullable','string','max:255'],'invoice_number'=>['nullable','string','max:255'],'payment_method'=>['nullable','string','max:80'],'campaign_id'=>['nullable','exists:campaigns,id'],'note'=>['nullable','string']]);}
}
