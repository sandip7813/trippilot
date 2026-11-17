<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pack_name', 64);
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('credits');
            $table->string('status', 16)->default('created');
            $table->string('razorpay_order_id', 64)->unique();
            $table->string('razorpay_payment_id', 64)->nullable()->unique();
            $table->unsignedInteger('amount_refunded_paise')->default(0);
            $table->unsignedInteger('credits_reversed')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreignId('credit_order_id')->nullable()->after('related_transaction_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_order_id');
        });

        Schema::dropIfExists('credit_orders');
    }
};
