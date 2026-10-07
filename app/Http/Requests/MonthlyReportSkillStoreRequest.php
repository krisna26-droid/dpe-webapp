<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyReportSkillStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Schema::hasTable('monthly_reports')) {
                        return;
                    }

                    $exists = DB::table('monthly_reports')->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The selected report id is invalid.');
                    }
                },
            ],
            'skill_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Schema::hasTable('learning_skills')) {
                        return;
                    }

                    $exists = DB::table('learning_skills')->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The selected skill id is invalid.');
                    }
                },
            ],
            'trend' => [
                'nullable',
                'string',
            ],
            'description' => [
                'required',
                'string',
            ],
        ];
    }
}
