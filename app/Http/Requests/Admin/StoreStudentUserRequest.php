<?php
namespace App\Http\Requests\Admin;
use App\Support\StudentAccountInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreStudentUserRequest extends FormRequest {
    public function authorize(): bool {return $this->user()?->role_code==='admin' && $this->user()?->is_active;}
    protected function prepareForValidation(): void {
        $values=[];foreach(['username','full_name','grade_name','school_name','guardian_phone'] as $f) if(is_string($this->input($f))) $values[$f]=trim($this->input($f));
        $this->merge($values);
    }
    public function rules(): array {return StudentAccountInput::rules()+['role_code'=>['sometimes',Rule::in(['student'])],'is_active'=>['sometimes',Rule::in([true,1,'1'])]];}
    public function messages(): array {return StudentAccountInput::messages();}
}
