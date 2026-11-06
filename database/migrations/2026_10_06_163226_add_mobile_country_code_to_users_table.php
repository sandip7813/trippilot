<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_country_code', 8)->nullable()->after('email');
        });

        DB::table('users')->whereNotNull('mobile_number')->select('id', 'mobile_number')->orderBy('id')->cursor()->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update($this->normalizeIndianMobileNumber((string) $user->mobile_number));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_country_code');
        });
    }

    /**
     * Mobile numbers were previously free text, so strip formatting and split
     * out the Indian dial code where it can be recognised. Anything else is
     * kept as digits only for the user to correct from their profile.
     *
     * @return array{mobile_country_code: string|null, mobile_number: string|null}
     */
    private function normalizeIndianMobileNumber(string $mobileNumber): array
    {
        $digits = preg_replace('/\D/', '', $mobileNumber);

        $nationalNumber = match (true) {
            strlen($digits) === 10 => $digits,
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            strlen($digits) === 12 && str_starts_with($digits, '91') => substr($digits, 2),
            default => null,
        };

        if ($nationalNumber !== null) {
            return ['mobile_country_code' => '+91', 'mobile_number' => $nationalNumber];
        }

        return ['mobile_country_code' => null, 'mobile_number' => $digits !== '' ? $digits : null];
    }
};
